<?php

namespace App\Modules\PartnerApi\Http\Controllers;

use App\Models\VehicleHold;
use App\Modules\PartnerApi\Domain\Auth\Scopes;
use App\Modules\PartnerApi\Domain\Stock\PartnerPricing;
use App\Modules\PartnerApi\Domain\Stock\PartnerScope;
use App\Modules\PartnerApi\Http\Middleware\AuthenticatePartner;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerAccessToken;
use App\Modules\PartnerApi\Models\PartnerClient;
use App\Modules\PartnerApi\Support\PartnerApiException;
use App\Modules\PartnerApi\Support\StrictInput;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use App\Services\Holds\VehicleUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Holds for selling partners (SRS section 7, Amendment 01): reserve a car
 * before taking a buyer's money. Listing partners and test keys cannot hold.
 */
class HoldController
{
    public function __construct(private HoldService $holds) {}

    public function store(Request $request, string $stockNo): JsonResponse
    {
        [$client, $partner] = $this->sellingPartner($request);
        StrictInput::only($request, ['buyer_ref', 'destination_country']);

        $data = Validator::make($request->all(), [
            'buyer_ref' => ['required', 'string', 'max:120'],
            'destination_country' => ['nullable', 'string', 'size:2', 'alpha'],
        ])->validate();

        $key = $request->header('Idempotency-Key');
        if ($key !== null && (strlen($key) > 120 || $key === '')) {
            throw new PartnerApiException(400, 'invalid_idempotency_key', 'Idempotency-Key must be 1 to 120 characters.');
        }

        // Out of scope looks exactly like unknown (FR-STK-02).
        $vehicle = PartnerScope::visible($partner)->where('vehicles.stock_no', $stockNo)->first()
            ?? throw new PartnerApiException(404, 'not_found', 'Not found.');

        try {
            $hold = $this->holds->claim($vehicle, new HoldClaim(
                channel: VehicleHold::CHANNEL_PARTNER,
                buyerRef: $data['buyer_ref'],
                partner: $partner,
                idempotencyKey: $key,
                destinationCountry: isset($data['destination_country']) ? strtoupper($data['destination_country']) : null,
            ));
        } catch (VehicleUnavailable $e) {
            throw match ($e->reason) {
                VehicleUnavailable::HOLD_LIMIT => new PartnerApiException(409, 'hold_limit_reached', 'You already hold as many cars as your contract allows.',
                    "Release a hold you no longer need. Your limit is {$partner->max_active_holds}."),
                default => new PartnerApiException(409, 'vehicle_unavailable', 'This vehicle is not available.',
                    'Another buyer holds it, or it was sold. Do not take payment.'),
            };
        }

        // Same holder asking again for a car it already holds: the existing hold, 200.
        $status = $hold->wasRecentlyCreated ? 201 : 200;

        return new JsonResponse(['data' => $this->present($hold->fresh(['vehicle']), $partner)], $status);
    }

    public function index(Request $request): JsonResponse
    {
        [, $partner] = $this->sellingPartner($request);
        StrictInput::only($request, []);

        $holds = VehicleHold::with('vehicle')->where('partner_id', $partner->id)
            ->where(fn ($query) => $query->where('state', VehicleHold::STATE_ACTIVE)->orWhere('created_at', '>=', now()->subDays(30)))
            ->orderByDesc('created_at')->limit(200)->get();

        return new JsonResponse(['data' => $holds->map(fn (VehicleHold $hold) => $this->present($hold, $partner))->values()->all()]);
    }

    public function destroy(Request $request, string $holdId): JsonResponse
    {
        [, $partner] = $this->sellingPartner($request);
        StrictInput::only($request, []);

        $hold = VehicleHold::with('vehicle')->where('public_id', $holdId)->where('partner_id', $partner->id)->first()
            ?? throw new PartnerApiException(404, 'not_found', 'Not found.');

        if (! $hold->isActive()) {
            throw new PartnerApiException(409, 'hold_not_active', 'This hold has already ended.', 'Its state is '.$hold->state.'.');
        }

        $hold = $this->holds->release($hold, 'Released by partner');

        return new JsonResponse(['data' => $this->present($hold->fresh(['vehicle']), $partner)]);
    }

    /** @return array{0: PartnerClient, 1: Partner} */
    private function sellingPartner(Request $request): array
    {
        /** @var PartnerAccessToken $token */
        $token = $request->attributes->get(AuthenticatePartner::TOKEN);
        $client = $token->client;
        $partner = $client->partner;

        if (! $partner->isSelling()) {
            throw new PartnerApiException(403, 'holds_not_available_for_partner', 'Holds are not available for your account.',
                'Listing partners send buyers to Toco and never hold cars.');
        }
        if ($client->environment !== PartnerClient::ENV_LIVE) {
            throw new PartnerApiException(403, 'holds_not_available_for_test_keys', 'Test keys cannot hold cars.',
                'A hold reserves a real car. Use a live key.');
        }
        if (! $token->hasScope(Scopes::STOCK_HOLD)) {
            throw new PartnerApiException(403, 'insufficient_scope', 'This token does not allow that action.',
                'Needs the stock:hold scope.', extra: ['required_scope' => Scopes::STOCK_HOLD]);
        }

        return [$client, $partner];
    }

    /** @return array<string, mixed> */
    private function present(VehicleHold $hold, Partner $partner): array
    {
        $amount = $hold->price_snapshot !== null && $partner->price_mode !== PartnerPricing::ON_REQUEST
            ? PartnerPricing::apply((float) $hold->price_snapshot, $partner) : null;

        return [
            'hold_id' => $hold->public_id,
            'stock_no' => $hold->vehicle?->stock_no,
            'state' => $hold->state,
            'buyer_ref' => $hold->buyer_ref,
            'destination_country' => $hold->destination_country,
            'price' => ['amount' => $amount, 'currency' => $hold->currency, 'basis' => 'FOB'],
            'created_at' => $hold->created_at?->toIso8601ZuluString(),
            'expires_at' => $hold->expires_at?->toIso8601ZuluString(),
            'ended_at' => $hold->ended_at?->toIso8601ZuluString(),
        ];
    }
}

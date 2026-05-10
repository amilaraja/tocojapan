<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Country;
use App\Models\Quote;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $quotes = $request->user()->quotes()
            ->with(['vehicle', 'country', 'port'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Quote $q) => self::serialize($q));

        return ApiResponse::ok($quotes->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_slug' => ['nullable', 'string', 'exists:vehicles,slug'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'port_id' => ['nullable', 'integer', 'exists:ports,id'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:4000'],
        ]);

        if (! empty($data['country_id']) && ! empty($data['port_id'])) {
            $belongs = Country::find($data['country_id'])
                ?->ports()->where('id', $data['port_id'])->exists();
            if (! $belongs) {
                return ApiResponse::error('Selected port does not belong to that country.', 422);
            }
        }

        $vehicle = ! empty($data['vehicle_slug']) ? Vehicle::where('slug', $data['vehicle_slug'])->first() : null;

        $quote = $request->user()->quotes()->create([
            'vehicle_id' => $vehicle?->id,
            'country_id' => $data['country_id'] ?? null,
            'port_id' => $data['port_id'] ?? null,
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'submitted',
            'last_customer_reply_at' => now(),
        ]);

        if (! empty($data['message'])) {
            $quote->messages()->create([
                'user_id' => $request->user()->id,
                'body' => $data['message'],
                'is_internal' => false,
            ]);
        }

        return ApiResponse::created(self::serialize($quote->fresh(['vehicle', 'country', 'port', 'messages'])));
    }

    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeOwner($request, $quote);
        $quote->load(['vehicle', 'country', 'port', 'messages.user']);

        return ApiResponse::ok(self::serialize($quote, withMessages: true));
    }

    public function reply(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeOwner($request, $quote);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $quote->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_internal' => false,
        ]);

        $quote->update(['last_customer_reply_at' => now()]);

        return ApiResponse::created(self::serialize($quote->fresh(['vehicle', 'country', 'port', 'messages.user']), withMessages: true));
    }

    /**
     * @return array<string, mixed>
     */
    private static function serialize(Quote $q, bool $withMessages = false): array
    {
        $out = [
            'id' => $q->id,
            'reference' => $q->reference,
            'status' => $q->status,
            'vehicle' => $q->vehicle ? [
                'slug' => $q->vehicle->slug,
                'title' => $q->vehicle->title,
                'ref_no' => $q->vehicle->ref_no,
            ] : null,
            'destination' => [
                'country' => $q->country?->name,
                'port' => $q->port?->name,
            ],
            'contact' => [
                'name' => $q->contact_name,
                'email' => $q->contact_email,
                'phone' => $q->contact_phone,
            ],
            'price_quoted' => $q->price_quoted ? (float) $q->price_quoted : null,
            'cif_total' => $q->cif_total ? (float) $q->cif_total : null,
            'currency' => $q->currency,
            'valid_until' => $q->valid_until?->toDateString(),
            'created_at' => $q->created_at?->toIso8601String(),
            'last_admin_reply_at' => $q->last_admin_reply_at?->toIso8601String(),
        ];

        if ($withMessages) {
            $out['messages'] = $q->messages->where('is_internal', false)->values()->map(fn ($m) => [
                'id' => $m->id,
                'from_admin' => $m->user_id !== $q->user_id,
                'author' => $m->user->name,
                'body' => $m->body,
                'created_at' => $m->created_at->toIso8601String(),
            ])->all();
        }

        return $out;
    }

    private function authorizeOwner(Request $request, Quote $quote): void
    {
        if ($quote->user_id !== $request->user()->id) {
            throw new AuthorizationException('You do not have access to this quote.');
        }
    }
}

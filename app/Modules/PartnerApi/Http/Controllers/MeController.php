<?php

namespace App\Modules\PartnerApi\Http\Controllers;

use App\Modules\PartnerApi\Http\Middleware\AuthenticatePartner;
use App\Modules\PartnerApi\Models\PartnerAccessToken;
use App\Modules\PartnerApi\Support\StrictInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Who am I": lets a partner check its keys and see its contracted limits
 * while integrating. Needs a valid token, no particular scope.
 */
class MeController
{
    public function __invoke(Request $request): JsonResponse
    {
        StrictInput::only($request, []);

        /** @var PartnerAccessToken $token */
        $token = $request->attributes->get(AuthenticatePartner::TOKEN);
        $client = $token->client;
        $partner = $client->partner;

        return new JsonResponse([
            'partner' => [
                'name' => $partner->name,
                'type' => $partner->type,
                'attribution_code' => $partner->attribution_code,
                'contract_ends_on' => $partner->contract_ends_on?->toDateString(),
            ],
            'client' => [
                'client_id' => $client->client_id,
                'label' => $client->label,
                'environment' => $client->environment,
            ],
            'token' => [
                'scopes' => $token->scopes,
                'expires_at' => $token->expires_at->toIso8601ZuluString(),
            ],
            'limits' => [
                'requests_per_minute' => $partner->rate_per_minute,
                'requests_per_day' => $partner->rate_per_day,
                'images_per_day' => $partner->image_daily_limit,
                'max_image_width' => $partner->image_max_width,
            ] + ($partner->isSelling() ? [
                'max_active_holds' => $partner->max_active_holds,
                'hold_hours' => $partner->hold_hours,
            ] : []),
        ]);
    }
}

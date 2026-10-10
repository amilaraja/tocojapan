<?php

namespace App\Modules\PartnerApi\Http\Controllers;

use App\Modules\PartnerApi\Domain\Usage\Quota;
use App\Modules\PartnerApi\Domain\Usage\UsageReport;
use App\Modules\PartnerApi\Http\Middleware\AuthenticatePartner;
use App\Modules\PartnerApi\Support\StrictInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /usage: the partner's own usage, read-only (FR-USE-01). */
class UsageController
{
    public function __invoke(Request $request): JsonResponse
    {
        StrictInput::only($request, []);
        $partner = $request->attributes->get(AuthenticatePartner::TOKEN)->client->partner;
        $report = UsageReport::for($partner);

        return new JsonResponse(['data' => [
            'today' => [
                'requests' => Quota::usedToday($partner, 'api'),
                'requests_limit' => Quota::dailyLimit($partner, 'api'),
                'images' => Quota::usedToday($partner, 'image'),
                'images_limit' => Quota::dailyLimit($partner, 'image'),
                'errors' => $report->errorsToday(),
                'resets_in_seconds' => Quota::secondsToReset(),
            ],
            'last_sync_at' => $report->lastSyncAt()?->toIso8601ZuluString(),
            'days' => $report->days(30),
        ]]);
    }
}

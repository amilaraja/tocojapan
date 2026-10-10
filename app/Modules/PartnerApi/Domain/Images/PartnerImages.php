<?php

namespace App\Modules\PartnerApi\Domain\Images;

use App\Models\Vehicle;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerClient;
use App\Modules\PartnerApi\Support\PartnerApi;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Photo links for partner responses (FR-IMG-02/03). Each photo has a stable
 * id (so a partner knows which files it already has) and a signed URL that
 * stays the same within an hour, so list responses and ETags stay stable.
 */
class PartnerImages
{
    public const DELIVERY_REHOST = 'rehost';

    public const DELIVERY_HOSTED = 'hosted';

    /** Phase 1 serves watermarked images only. */
    public const MODE_APPLIED = 'watermarked';

    /** Largest width we hold (the "gallery" conversion). */
    public const SOURCE_WIDTH = 1280;

    /** @return list<array{id: string, url: string, width: int}> */
    public static function forVehicle(Vehicle $vehicle, PartnerClient $client): array
    {
        $partner = $client->partner;
        $width = min((int) $partner->image_max_width, self::SOURCE_WIDTH);
        $exp = self::expiry($partner);
        $profile = self::profile($partner);

        return $vehicle->getMedia('photos')->map(fn (Media $media) => [
            'id' => self::photoId($partner, $media),
            'url' => PartnerApi::baseUrl().'/img/'.ImageToken::make([
                'cid' => $client->id, 'mid' => $media->id, 'p' => $profile, 'w' => $width, 'exp' => $exp,
            ]),
            'width' => $width,
        ])->values()->all();
    }

    /** Variant profile: mode + ref pill + width + settings version (§9.3). */
    public static function profile(Partner $partner): string
    {
        return 'wm'.($partner->image_ref_pill ? '1' : '0').'-'.$partner->image_max_width.'-v'.$partner->scope_version;
    }

    /** Token lifetime: 1 h for re-host, 24 h for Toco-hosted (§9.1), aligned to the hour. */
    public static function expiry(Partner $partner): int
    {
        $ttl = ($partner->image_delivery ?? self::DELIVERY_REHOST) === self::DELIVERY_HOSTED ? 86400 : 3600;
        $hour = (int) (ceil(time() / 3600) * 3600);

        return $hour + $ttl;
    }

    public static function photoId(Partner $partner, Media $media): string
    {
        return 'ph_'.substr(hash_hmac('sha256', $partner->id.':'.$media->id, (string) config('app.key')), 0, 20);
    }
}

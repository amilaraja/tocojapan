<?php

namespace App\Modules\PartnerApi\Http\Controllers;

use App\Models\Vehicle;
use App\Modules\PartnerApi\Domain\Images\ImageToken;
use App\Modules\PartnerApi\Domain\Images\ImageVariants;
use App\Modules\PartnerApi\Domain\Images\PartnerImages;
use App\Modules\PartnerApi\Domain\Stock\PartnerScope;
use App\Modules\PartnerApi\Domain\Usage\Quota;
use App\Modules\PartnerApi\Models\PartnerClient;
use App\Modules\PartnerApi\Models\PartnerRequestLog;
use App\Modules\PartnerApi\Support\PartnerApi;
use App\Modules\PartnerApi\Support\PartnerApiException;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /img/{token}: the anti-hot-linking control (SRS §9.2). The token is
 * the only credential. Checks in order: signature and key, expiry, client
 * and partner active, settings unchanged, photo still in scope, quota. Every
 * failure except the quota is a bare 404, so the endpoint cannot be probed.
 */
class ImageController
{
    public function __invoke(Request $request, string $token): BinaryFileResponse
    {
        $request->attributes->set(PartnerApi::KIND, PartnerRequestLog::KIND_IMAGE);

        $claims = ImageToken::parse($token) ?? throw self::notFound();

        $client = PartnerClient::with('partner')->find($claims['cid']);
        if (! $client || ! $client->isActive()) {
            throw self::notFound();
        }
        $request->attributes->set(PartnerApi::CLIENT, $client);
        $partner = $client->partner;

        // A suspended, revoked or out-of-contract partner, or one whose photo
        // settings changed since the link was signed, gets nothing (§8.7, §9.4).
        if ($partner->accessDenialCode() !== null || $claims['p'] !== PartnerImages::profile($partner)) {
            throw self::notFound();
        }

        $media = Media::find($claims['mid']);
        if (! $media || $media->collection_name !== 'photos' || $media->model_type !== (new Vehicle)->getMorphClass()) {
            throw self::notFound();
        }

        $vehicle = Vehicle::withTrashed()->find($media->model_id);
        if (! $vehicle || ! PartnerScope::isVisible($partner, $vehicle)) {
            throw self::notFound();
        }

        Quota::ensureDaily($partner, 'image');

        $path = ImageVariants::path($media, $claims['p'], min((int) $claims['w'], (int) $partner->image_max_width)) ?? throw self::notFound();
        Quota::countDaily($partner, 'image');

        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => 'image/webp',
            'X-Robots-Tag' => 'noindex, noimageindex',
            'X-Content-Type-Options' => 'nosniff',
        ], public: false, autoEtag: false, autoLastModified: false);

        // Re-host: never cached on the way. Toco-hosted: cacheable until the link expires (§9.4).
        if (($partner->image_delivery ?? PartnerImages::DELIVERY_REHOST) === PartnerImages::DELIVERY_HOSTED) {
            $response->headers->set('Cache-Control', 'public, max-age='.max(0, (int) $claims['exp'] - time()).', immutable');
        } else {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    private static function notFound(): PartnerApiException
    {
        return new PartnerApiException(404, 'not_found', 'Not found.');
    }
}

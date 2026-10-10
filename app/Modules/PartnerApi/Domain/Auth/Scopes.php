<?php

namespace App\Modules\PartnerApi\Domain\Auth;

use App\Modules\PartnerApi\Models\Partner;

/**
 * Token scopes (SRS §8.2, Amendment 01): stock:hold only for selling partners.
 */
class Scopes
{
    public const STOCK_READ = 'stock:read';

    public const CIF_READ = 'cif:read';

    public const USAGE_READ = 'usage:read';

    public const STOCK_HOLD = 'stock:hold';

    /** @return list<string> */
    public static function grantable(Partner $partner): array
    {
        $scopes = [self::STOCK_READ, self::CIF_READ, self::USAGE_READ];

        if ($partner->isSelling()) {
            $scopes[] = self::STOCK_HOLD;
        }

        return $scopes;
    }

    /**
     * Scopes for a new token: everything grantable, or the requested subset.
     * Returns null when the request names a scope the partner cannot have.
     *
     * @return list<string>|null
     */
    public static function resolve(Partner $partner, ?string $requested): ?array
    {
        $grantable = self::grantable($partner);

        if ($requested === null || trim($requested) === '') {
            return $grantable;
        }

        $asked = array_values(array_unique(preg_split('/\s+/', trim($requested))));

        return array_diff($asked, $grantable) === [] ? $asked : null;
    }
}

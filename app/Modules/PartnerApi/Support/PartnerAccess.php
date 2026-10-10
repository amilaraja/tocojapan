<?php

namespace App\Modules\PartnerApi\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * UI brief section 1: everything in the Partner Network group needs the
 * partners.admin permission. checkPermissionTo() returns false (instead of
 * throwing) before the permission is seeded.
 */
class PartnerAccess
{
    public const ADMIN = 'partners.admin';

    public static function canAdmin(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && method_exists($user, 'checkPermissionTo')
            && $user->checkPermissionTo(self::ADMIN);
    }
}

<?php

namespace App\Modules\Mailer\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * TOC-GEN-002: the two Mailer permissions, granted through the existing
 * roles (mailer_admin, mailer_marketer) on the Users screen.
 *
 * checkPermissionTo() returns false (instead of throwing) when the
 * permission rows have not been seeded yet.
 */
class MailerAccess
{
    public const ADMIN = 'mailer.admin';

    public const MARKETER = 'mailer.marketer';

    public const ROLE_ADMIN = 'mailer_admin';

    public const ROLE_MARKETER = 'mailer_marketer';

    public static function canUse(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && method_exists($user, 'checkPermissionTo')
            && ($user->checkPermissionTo(self::ADMIN) || $user->checkPermissionTo(self::MARKETER));
    }

    public static function isAdmin(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && method_exists($user, 'checkPermissionTo')
            && $user->checkPermissionTo(self::ADMIN);
    }
}

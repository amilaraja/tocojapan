<?php

namespace App\Modules\Mailer\Filament\Clusters;

use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/** Buyer database (TOC-BUY-007 to 009): Mailer Admins and Marketers. */
class Buyers extends Cluster
{
    protected static ?string $slug = 'mailer/buyers';

    protected static ?string $navigationLabel = 'Buyers';

    protected static ?string $clusterBreadcrumb = 'Buyers';

    protected static string|\UnitEnum|null $navigationGroup = 'Mailer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return MailerAccess::canUse();
    }
}

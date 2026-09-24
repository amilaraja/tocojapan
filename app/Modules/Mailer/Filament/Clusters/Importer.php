<?php

namespace App\Modules\Mailer\Filament\Clusters;

use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/** S9 Importer section: Mailer Admins only (TOC-GEN-002). */
class Importer extends Cluster
{
    protected static ?string $slug = 'mailer/importer';

    protected static ?string $navigationLabel = 'Importer';

    protected static ?string $clusterBreadcrumb = 'Importer';

    protected static string|\UnitEnum|null $navigationGroup = 'Mailer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 40;

    public static function canAccess(): bool
    {
        return MailerAccess::isAdmin();
    }
}

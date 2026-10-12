<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Modules\Crm\Domain\Reports;
use App\Modules\Crm\Filament\Concerns\HasPeriod;
use App\Modules\Crm\Support\CrmAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Per staff member: load, speed, SLA, follow-ups, results (CRM-DSH-003). */
class TeamOverview extends Page
{
    use HasPeriod;

    protected string $view = 'crm::filament.team';

    protected static ?string $slug = 'crm/team';

    protected static ?string $title = 'Team overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return CrmAccess::isAdmin() || CrmAccess::isTeamLead();
    }

    public function rows()
    {
        return Reports::team($this->periodFrom(), $this->periodTo());
    }
}

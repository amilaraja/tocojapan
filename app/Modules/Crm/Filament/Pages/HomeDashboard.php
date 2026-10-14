<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Modules\Crm\CrmServiceProvider;
use App\Modules\Crm\Support\CrmAccess;
use Filament\Pages\Dashboard;

/**
 * The admin home page. CRM staff who are not site admins land on the CRM Dashboard
 * instead (client change 2026-10-14); everyone else sees the normal admin dashboard.
 */
class HomeDashboard extends Dashboard
{
    public function mount(): void
    {
        $user = auth()->user();
        if (CrmServiceProvider::enabled() && CrmAccess::canUse($user) && ! $user->hasAnyRole(['super_admin', 'admin'])) {
            $this->redirect(CrmDashboard::getUrl());
        }
    }
}

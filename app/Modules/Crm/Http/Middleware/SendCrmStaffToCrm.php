<?php

namespace App\Modules\Crm\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Admin panel login check that sends CRM-only staff to their /crm workspace
 * instead of a 403 page (CR-TOC-02-01). Replaces Filament's Authenticate on /admin.
 */
class SendCrmStaffToCrm extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();
        if ($user && ! $user->canAccessPanel(Filament::getPanel('admin')) && $user->canAccessPanel(Filament::getPanel('crm'))) {
            throw new HttpResponseException(redirect('/crm'));
        }

        parent::authenticate($request, $guards);
    }
}

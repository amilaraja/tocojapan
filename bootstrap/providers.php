<?php

use App\Providers\AppServiceProvider;
use App\Modules\Mailer\MailerServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    MailerServiceProvider::class,
];

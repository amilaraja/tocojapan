<?php

use App\Providers\AppServiceProvider;
use App\Modules\Crm\CrmServiceProvider;
use App\Modules\JctFeed\JctFeedServiceProvider;
use App\Modules\Mailer\MailerServiceProvider;
use App\Modules\PartnerApi\PartnerApiServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Modules\Crm\Filament\CrmPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CrmPanelProvider::class,
    MailerServiceProvider::class,
    PartnerApiServiceProvider::class,
    JctFeedServiceProvider::class,
    CrmServiceProvider::class,
];

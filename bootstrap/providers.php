<?php

use App\Providers\AppServiceProvider;
use App\Modules\Crm\CrmServiceProvider;
use App\Modules\JctFeed\JctFeedServiceProvider;
use App\Modules\Mailer\MailerServiceProvider;
use App\Modules\PartnerApi\PartnerApiServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    MailerServiceProvider::class,
    PartnerApiServiceProvider::class,
    JctFeedServiceProvider::class,
    CrmServiceProvider::class,
];

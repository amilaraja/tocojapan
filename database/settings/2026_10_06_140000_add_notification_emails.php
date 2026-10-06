<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Where staff alerts (contact form, quotes, spare parts, proforma) go.
        // Empty = contact_email. Must not be only the SMTP login itself, or
        // Gmail files the alerts under Sent instead of the inbox.
        $this->migrator->add('general.notification_emails', null);
    }

    public function down(): void
    {
        $this->migrator->delete('general.notification_emails');
    }
};

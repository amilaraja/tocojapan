<?php

namespace App\Modules\Mailer\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** TOC-IMP-009: sent once after 3 consecutive failed runs. */
class ImportFailingAlert extends Notification
{
    public function __construct(public int $failures, public string $lastError) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('TOCO Mailer: the inbox importer has failed '.$this->failures.' times in a row')
            ->line('The inbox importer could not finish its last '.$this->failures.' runs, so new enquiries are not reaching Brevo.')
            ->line('Last problem: '.$this->lastError)
            ->action('Open the run log', url('/admin/mailer/importer/run-log'))
            ->line('You will not get another email about this until the importer has worked again.');
    }
}

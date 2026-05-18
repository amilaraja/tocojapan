<?php

namespace App\Notifications;

use App\Models\ContactInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewContactInquiry extends Notification
{
    use Queueable;

    public function __construct(public ContactInquiry $inquiry) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->inquiry;

        $mail = (new MailMessage())
            ->subject('New website inquiry'.($i->subject ? ': '.$i->subject : ''))
            ->line("From: {$i->name} <{$i->email}>");

        if ($i->phone) {
            $mail->line("Phone: {$i->phone}");
        }

        return $mail
            ->line('Message:')
            ->line('"'.$i->message.'"')
            ->action('View in admin', url('/admin/contact-inquiries/'.$i->id))
            ->line('Submitted '.$i->created_at->toDayDateTimeString().' from '.$i->ip);
    }
}

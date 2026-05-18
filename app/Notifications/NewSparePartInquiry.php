<?php

namespace App\Notifications;

use App\Models\SparePartInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSparePartInquiry extends Notification
{
    use Queueable;

    public function __construct(public SparePartInquiry $inquiry) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->inquiry;

        return (new MailMessage())
            ->subject('New spare-part order inquiry from '.$i->name)
            ->line("From: {$i->name} <{$i->email}>")
            ->line('Phone: '.$i->phone.($i->country ? ' · '.$i->country : ''))
            ->line('Vehicle: '.trim(($i->year ? $i->year.' ' : '').$i->model_name))
            ->line('Chassis: '.($i->chassis_no ?: '—').' · Engine: '.($i->engine_model ?: '—'))
            ->line('Condition: '.($i->condition ?: '—').' · Shipping: '.($i->shipping_method ?: '—'))
            ->line('Parts requested:')
            ->line('"'.$i->parts_description.'"')
            ->action('View in admin', url('/admin/spare-part-inquiries/'.$i->id));
    }
}

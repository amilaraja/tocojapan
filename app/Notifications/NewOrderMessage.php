<?php

namespace App\Notifications;

use App\Models\OrderMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewOrderMessage extends Notification
{
    use Queueable;

    public function __construct(public OrderMessage $message, public bool $forAdmin) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->message->order;
        $preview = Str::limit($this->message->body ?: '(attachment only)', 200);

        $url = $this->forAdmin
            ? url('/admin/orders/'.$order->id)
            : url('/orders/'.$order->id);

        $who = $this->message->from_admin ? 'Toco team' : ($this->message->user->name ?? 'Customer');

        return (new MailMessage())
            ->subject("New message on order {$order->order_no}")
            ->greeting('Hi '.($notifiable->name ?? '').',')
            ->line("{$who} just sent a message on order {$order->order_no} ({$order->vehicle->title}):")
            ->line('"'.$preview.'"')
            ->action('View thread', $url);
    }
}

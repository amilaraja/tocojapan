<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public string $previousStatus) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = (new MailMessage())
            ->subject("Order {$this->order->order_no} — ".$this->order->statusLabel())
            ->greeting("Hi {$notifiable->name},")
            ->line("Your order {$this->order->order_no} for {$this->order->vehicle->title} is now {$this->order->statusLabel()}.");

        if ($this->order->shipped_at && $this->order->status === 'shipped') {
            $msg->line('Shipping documents and tracking details are available in your order messages.');
        }

        return $msg
            ->action('View order', url('/orders/'.$this->order->id))
            ->line('Thank you for choosing Toco Japan.');
    }
}

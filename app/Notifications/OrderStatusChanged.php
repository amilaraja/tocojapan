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

        if ($this->order->status === 'shipped' && $this->order->bl_number) {
            $msg->line('Shipping details:')
                ->line('• B/L number: '.$this->order->bl_number)
                ->line('• Vessel: '.$this->order->vessel_name.($this->order->voyage_no ? ' (voyage '.$this->order->voyage_no.')' : ''))
                ->line('• ETA: '.$this->order->eta_at?->format('d M Y'));
            if ($this->order->carrier_tracking_url) {
                $msg->line('Track vessel: '.$this->order->carrier_tracking_url);
            } else {
                $msg->line('Track the vessel live on MarineTraffic or VesselFinder by name.');
            }
        }

        return $msg
            ->action('View order', url('/orders/'.$this->order->id))
            ->line('Thank you for choosing Toco Japan.');
    }
}

<?php

namespace App\Notifications;

use App\Models\ProformaInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells sales a customer generated an LC proforma invoice. */
class NewProformaInvoice extends Notification
{
    use Queueable;

    public function __construct(public ProformaInvoice $invoice) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->invoice;
        $v = $i->snapshot;

        return (new MailMessage)
            ->subject("LC proforma invoice {$i->invoice_no} — {$v['title']}")
            ->replyTo($i->consignee_email, $i->consignee_name)
            ->line("{$i->consignee_name} <{$i->consignee_email}> generated an LC proforma invoice.")
            ->line("Vehicle: {$v['title']} (stock {$v['stock_no']})")
            ->line("Destination: {$v['port']} / {$v['country']}")
            ->line('Total CIF: US$ '.number_format((float) $i->total_cif).' — valid until '.$i->expires_on->format('j M Y'))
            ->action('View in admin', url('/admin/proforma-invoices'));
    }
}

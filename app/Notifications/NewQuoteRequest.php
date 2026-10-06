<?php

namespace App\Notifications;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells sales about a new quote request, or a customer's reply on one. */
class NewQuoteRequest extends Notification
{
    use Queueable;

    public function __construct(public Quote $quote, public ?string $replyBody = null) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $q = $this->quote->loadMissing(['vehicle', 'country', 'port']);
        $vehicle = $q->vehicle ? $q->vehicle->title.($q->vehicle->stock_no ? ' (stock '.$q->vehicle->stock_no.')' : '') : 'a vehicle';
        $isReply = $this->replyBody !== null;

        $mail = (new MailMessage)
            ->subject(($isReply ? 'Customer reply on quote ' : 'New quote request ').$q->reference.' — '.($q->vehicle?->title ?? ''))
            ->replyTo($q->contact_email, $q->contact_name)
            ->line("From: {$q->contact_name} <{$q->contact_email}>".($q->contact_phone ? " · {$q->contact_phone}" : ''))
            ->line("Vehicle: {$vehicle}");

        if ($q->port || $q->country) {
            $mail->line('Destination: '.trim(($q->port?->name ?? '').' / '.($q->country?->name ?? ''), ' /'));
        }

        $body = $isReply ? $this->replyBody : $q->message;
        if ($body) {
            $mail->line($isReply ? 'Reply:' : 'Message:')->line('"'.$body.'"');
        }

        return $mail->action('Open in admin', url('/admin/quotes/'.$q->id.'/edit'));
    }
}

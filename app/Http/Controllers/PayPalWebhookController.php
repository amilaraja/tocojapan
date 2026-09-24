<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PayPalWebhookEvent;
use App\Support\PayPalGateway;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

/**
 * Server-to-server PayPal notifications.
 *
 * The browser redirect back to checkout.return is the happy path, but it
 * only happens if the buyer keeps the tab open. This endpoint is the
 * authority: it captures orders the buyer approved and walked away from,
 * and records refunds and reversals that have no browser leg at all.
 *
 * Every delivery is signature-verified and recorded by PayPal's event id,
 * so PayPal's retries (it re-sends for up to 3 days) are idempotent.
 */
class PayPalWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $webhookId = PayPalGateway::webhookId();

        if ($webhookId === '') {
            Log::warning('PayPal webhook hit but no webhook_id is configured', ['mode' => PayPalGateway::mode()]);

            // 503 so PayPal retries once we finish configuring, rather than
            // burning the delivery.
            return response('PayPal webhooks are not configured', 503);
        }

        $raw = $request->getContent();

        if (! $this->signatureIsValid($request, $webhookId, $raw)) {
            Log::warning('PayPal webhook rejected: bad signature', [
                'transmission_id' => $request->header('paypal-transmission-id'),
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 400);
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload) || empty($payload['id']) || empty($payload['event_type'])) {
            return response('Malformed payload', 400);
        }

        // Claim the event id. The unique index makes this atomic, so two
        // simultaneous deliveries of the same event cannot both proceed.
        try {
            $event = PayPalWebhookEvent::create([
                'event_id' => $payload['id'],
                'event_type' => $payload['event_type'],
                'payload' => $payload,
            ]);
        } catch (QueryException $e) {
            if (! $this->isDuplicateKey($e)) {
                throw $e;
            }

            return response('Already seen', 200);
        }

        try {
            $order = $this->dispatchEvent($payload['event_type'], $payload['resource'] ?? [], $event);
        } catch (\Throwable $e) {
            // Release the claim so PayPal's next retry can try again.
            $event->delete();

            Log::error('PayPal webhook handler failed', [
                'event_id' => $payload['id'],
                'event_type' => $payload['event_type'],
                'error' => $e->getMessage(),
            ]);

            return response('Handler error', 500);
        }

        $event->forceFill([
            'order_id' => $order?->id,
            'processed_at' => now(),
        ])->save();

        return response('OK', 200);
    }

    /**
     * Verify against PayPal's published signing certificate. Done locally
     * (RSA-SHA256 over the raw body) so a webhook is never trusted on the
     * strength of an API call that could itself be failing.
     */
    protected function signatureIsValid(Request $request, string $webhookId, string $raw): bool
    {
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = $values[0] ?? '';
        }

        try {
            return $this->client()->verifyWebHookLocally($headers, $webhookId, $raw);
        } catch (\Throwable $e) {
            Log::error('PayPal webhook signature check errored: '.$e->getMessage());

            return false;
        }
    }

    /** @return Order|null the order the event touched, if we could match one */
    protected function dispatchEvent(string $type, array $resource, PayPalWebhookEvent $event): ?Order
    {
        return match ($type) {
            'CHECKOUT.ORDER.APPROVED' => $this->onOrderApproved($resource, $event),
            'PAYMENT.CAPTURE.COMPLETED' => $this->onCaptureCompleted($resource, $event),
            'PAYMENT.CAPTURE.DENIED' => $this->onCaptureFailed($resource, $event, 'PayPal denied the capture.'),
            'PAYMENT.CAPTURE.DECLINED' => $this->onCaptureFailed($resource, $event, 'PayPal declined the capture.'),
            'PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED' => $this->onRefunded($resource, $event, $type),
            default => $this->onIgnored($type, $event),
        };
    }

    /**
     * The buyer approved the payment at PayPal. If they closed the tab
     * before the redirect, nothing has captured it yet — do it here.
     */
    protected function onOrderApproved(array $resource, PayPalWebhookEvent $event): ?Order
    {
        $paypalOrderId = $resource['id'] ?? null;
        $order = $paypalOrderId ? Order::where('paypal_order_id', $paypalOrderId)->first() : null;

        if (! $order) {
            $event->note = 'Approved event for unknown PayPal order '.($paypalOrderId ?? '?');

            return null;
        }

        if ($order->status !== 'pending') {
            $event->note = 'Order already '.$order->status.'; no capture attempted.';

            return $order;
        }

        $capture = $this->client()->capturePaymentOrder($paypalOrderId);
        $detail = $this->captureDetail($capture);

        if ($detail === null) {
            // Most often ORDER_ALREADY_CAPTURED — the browser leg won the
            // race. The matching CAPTURE.COMPLETED event settles it.
            $event->note = 'Capture returned no capture detail; leaving order pending.';
            Log::warning('PayPal webhook capture returned no detail', [
                'order_no' => $order->order_no,
                'response' => $capture,
            ]);

            return $order;
        }

        $this->markPaid($order, $detail, $capture, $event);

        return $order;
    }

    /** Money actually moved — this is what marks an order paid. */
    protected function onCaptureCompleted(array $resource, PayPalWebhookEvent $event): ?Order
    {
        $order = $this->resolveOrder($resource);

        if (! $order) {
            $event->note = 'Capture completed for an order we do not have.';
            Log::warning('PayPal capture completed but no matching order', ['resource' => $resource]);

            return null;
        }

        $this->markPaid($order, $this->normaliseDetail($resource), $resource, $event);

        return $order;
    }

    protected function onCaptureFailed(array $resource, PayPalWebhookEvent $event, string $reason): ?Order
    {
        $order = $this->resolveOrder($resource);

        if (! $order) {
            $event->note = $reason.' (no matching order)';

            return null;
        }

        // Deliberately left 'pending': the buyer can retry, and nothing was
        // taken. The admin note is the trail.
        $order->payment_payload = $resource;
        $order->admin_notes = trim((string) $order->admin_notes."\n".now()->toDateTimeString().' — '.$reason);
        $order->save();

        $event->note = $reason;

        return $order;
    }

    protected function onRefunded(array $resource, PayPalWebhookEvent $event, string $type): ?Order
    {
        // On a refund the resource is the refund, and the capture it reverses
        // is in links / related ids rather than at the top level.
        $captureId = $resource['links'][0]['href'] ?? null;
        $order = $this->resolveOrder($resource);

        if (! $order && is_string($captureId)) {
            // .../v2/payments/captures/{id}/refund style links
            if (preg_match('#/captures/([^/]+)#', $captureId, $m)) {
                $order = Order::where('paypal_capture_id', $m[1])->first();
            }
        }

        if (! $order) {
            $event->note = $type.' for an order we do not have.';
            Log::warning('PayPal refund with no matching order', ['resource' => $resource]);

            return null;
        }

        $order->payment_payload = $resource;
        $order->save();
        $order->transitionTo('refunded');

        $event->note = $type.' applied.';

        return $order;
    }

    protected function onIgnored(string $type, PayPalWebhookEvent $event): ?Order
    {
        // Recorded, not acted on. Returning 200 stops PayPal retrying an
        // event we have no opinion about.
        $event->note = 'No handler for '.$type.'; recorded only.';

        return null;
    }

    /**
     * Mark an order paid, but only if the money that arrived is the money
     * we asked for. A mismatch is an admin problem, never an auto-approve.
     */
    protected function markPaid(Order $order, array $detail, array $payload, PayPalWebhookEvent $event): void
    {
        if (($detail['status'] ?? null) !== 'COMPLETED') {
            $event->note = 'Capture status '.($detail['status'] ?? 'unknown').'; not marking paid.';

            return;
        }

        $expected = number_format((float) $order->amount_usd, 2, '.', '');
        $got = number_format((float) ($detail['value'] ?? 0), 2, '.', '');
        $currency = $detail['currency'] ?? '';

        if ($got !== $expected || $currency !== $order->currency) {
            $note = sprintf(
                'PayPal captured %s %s but order expects %s %s — NOT marked paid, needs review.',
                $currency, $got, $order->currency, $expected
            );

            $order->payment_payload = $payload;
            $order->admin_notes = trim((string) $order->admin_notes."\n".now()->toDateTimeString().' — '.$note);
            $order->save();

            $event->note = $note;
            Log::error('PayPal capture amount mismatch', ['order_no' => $order->order_no, 'detail' => $detail]);

            return;
        }

        $order->payment_payload = $payload;
        $order->paypal_capture_id = $detail['id'] ?? $order->paypal_capture_id;
        $order->save();

        // No-ops if the browser leg already marked it paid.
        $order->transitionTo('paid');

        $event->note = 'Marked paid from capture '.($detail['id'] ?? '?');
    }

    /**
     * Find the order behind a capture/refund resource. Tried in order of
     * how much we trust the identifier.
     */
    protected function resolveOrder(array $resource): ?Order
    {
        if (! empty($resource['custom_id'])) {
            $order = Order::where('order_no', $resource['custom_id'])->first();
            if ($order) {
                return $order;
            }
        }

        $relatedOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
        if ($relatedOrderId) {
            $order = Order::where('paypal_order_id', $relatedOrderId)->first();
            if ($order) {
                return $order;
            }
        }

        $relatedCaptureId = $resource['supplementary_data']['related_ids']['capture_id'] ?? ($resource['id'] ?? null);
        if ($relatedCaptureId) {
            return Order::where('paypal_capture_id', $relatedCaptureId)->first();
        }

        return null;
    }

    /** Pull the capture out of a v2 *order* response (the capture API reply). */
    protected function captureDetail(array $orderResponse): ?array
    {
        $capture = $orderResponse['purchase_units'][0]['payments']['captures'][0] ?? null;

        return is_array($capture) ? $this->normaliseDetail($capture) : null;
    }

    /** Flatten a v2 capture object to the few fields we act on. */
    protected function normaliseDetail(array $capture): array
    {
        return [
            'id' => $capture['id'] ?? null,
            'status' => $capture['status'] ?? null,
            'value' => $capture['amount']['value'] ?? null,
            'currency' => $capture['amount']['currency_code'] ?? null,
        ];
    }

    /** Seam: overridden in tests so no live PayPal call is made. */
    protected function client(): PayPalClient
    {
        return PayPalGateway::client();
    }

    protected function isDuplicateKey(QueryException $e): bool
    {
        // 23000/23505 = integrity constraint violation (MySQL / Postgres).
        return in_array((string) $e->getCode(), ['23000', '23505'], true);
    }
}

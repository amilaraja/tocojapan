<?php

namespace App\Support;

use Srmklive\PayPal\Services\PayPal as PayPalClient;

/**
 * One place that knows which PayPal mode is active and whether it is
 * usable. Checkout, the webhook endpoint and the vehicle page all ask
 * the same questions, so they all ask them here.
 */
class PayPalGateway
{
    public static function mode(): string
    {
        return config('paypal.mode', 'sandbox');
    }

    /** Credentials present for the active mode — enough to call the API. */
    public static function configured(): bool
    {
        $cfg = config('paypal.'.self::mode());

        return ! empty($cfg['client_id']) && ! empty($cfg['client_secret']);
    }

    /** Webhook ID for the active mode, or '' when webhooks are not set up. */
    public static function webhookId(): string
    {
        return (string) (config('paypal.'.self::mode().'.webhook_id') ?: '');
    }

    public static function client(): PayPalClient
    {
        $paypal = new PayPalClient();
        $paypal->setApiCredentials(config('paypal'));
        $paypal->setAccessToken($paypal->getAccessToken());

        return $paypal;
    }
}

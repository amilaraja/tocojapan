<?php

namespace App\Mail\Transport;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * Symfony Mailer transport that sends through Gmail API using an OAuth
 * refresh token. Drop-in replacement for SMTP — no app password, no port
 * concerns — and uses the same Gmail OAuth client the WP site does.
 */
class GmailApiTransport extends AbstractTransport
{
    protected ?Gmail $gmail = null;

    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        protected string $refreshToken,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $raw = rtrim(strtr(base64_encode($email->toString()), '+/', '-_'), '=');

        $payload = new Message();
        $payload->setRaw($raw);

        $this->gmail()->users_messages->send('me', $payload);
    }

    protected function gmail(): Gmail
    {
        if ($this->gmail) {
            return $this->gmail;
        }

        $client = new Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setAccessType('offline');

        // Cache the access token so we don't hit Google's token endpoint
        // on every send. Refresh ~5 min before expiry.
        $token = Cache::get('gmail_oauth_access_token');
        if (! $token) {
            $client->fetchAccessTokenWithRefreshToken($this->refreshToken);
            $token = $client->getAccessToken();
            $ttl = max(60, (int) ($token['expires_in'] ?? 3500) - 300);
            Cache::put('gmail_oauth_access_token', $token, $ttl);
        } else {
            $client->setAccessToken($token);
            if ($client->isAccessTokenExpired()) {
                $client->fetchAccessTokenWithRefreshToken($this->refreshToken);
                $token = $client->getAccessToken();
                $ttl = max(60, (int) ($token['expires_in'] ?? 3500) - 300);
                Cache::put('gmail_oauth_access_token', $token, $ttl);
            }
        }

        return $this->gmail = new Gmail($client);
    }
}

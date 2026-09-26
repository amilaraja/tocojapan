<?php

namespace App\Modules\Mailer\Domain\Brevo;

use App\Modules\Mailer\Support\MailerSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Thin Brevo v3 client over Laravel Http (no SDK).
 *
 * - Every request passes BrevoGuard first: no send, schedule or unsubscribe
 *   change can ever leave this class (TOC-CMP-002).
 * - 20 s timeout (TOC-NFR-005).
 * - 429 and 5xx (and connection failures) are retried up to 5 times with
 *   exponential backoff, honouring Retry-After (TOC-BRV-005). An optional
 *   time budget keeps interactive calls (push) under 25 s.
 * - The key is never logged.
 */
class BrevoClient
{
    /** Retries used by the last request (for the audit log). */
    public int $lastRetries = 0;

    protected ?int $budgetSeconds = null;

    public function __construct(protected MailerSettings $settings) {}

    public function isConfigured(): bool
    {
        return filled($this->settings->brevoApiKey());
    }

    /** A copy of the client that gives up once $seconds have passed (for push and screens). */
    public function withBudget(int $seconds): static
    {
        $clone = clone $this;
        $clone->budgetSeconds = $seconds;

        return $clone;
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->request('GET', $path, [], $query);
    }

    public function post(string $path, array $payload = []): Response
    {
        return $this->request('POST', $path, $payload);
    }

    public function put(string $path, array $payload = []): Response
    {
        return $this->request('PUT', $path, $payload);
    }

    /**
     * @param  array<mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    public function request(string $method, string $path, array $payload = [], array $query = []): Response
    {
        BrevoGuard::assertAllowed($method, $path, $payload);

        $key = $this->settings->brevoApiKey();
        if (blank($key)) {
            throw new BrevoNotConfigured;
        }

        $maxRetries = (int) config('mailer.brevo.max_retries', 5);
        $started = now();
        $this->lastRetries = 0;

        for ($attempt = 0; ; $attempt++) {
            $response = null;
            $error = null;

            try {
                $response = Http::baseUrl(rtrim((string) config('mailer.brevo.base_url'), '/'))
                    ->withHeaders(['api-key' => $key, 'accept' => 'application/json'])
                    ->timeout((int) config('mailer.brevo.timeout', 20))
                    ->connectTimeout(10)
                    ->send($method, ltrim($path, '/'), $method === 'GET' ? ['query' => $query] : ['json' => (object) $payload]);
            } catch (ConnectionException $e) {
                $error = $e;
            }

            if ($response?->status() === 401) {
                throw BrevoRejected::fromMessage($response->json('message'));
            }

            $retryable = $response === null || $response->status() === 429 || $response->serverError();
            if (! $retryable) {
                return $response;
            }

            $delay = $this->delayFor($response, $attempt);
            $elapsed = $started->diffInMilliseconds(now(), true) / 1000;
            $overBudget = $this->budgetSeconds !== null && $elapsed + $delay >= $this->budgetSeconds;

            if ($attempt >= $maxRetries || $overBudget) {
                $status = $response?->status();
                Log::warning('Mailer: Brevo unavailable', ['method' => $method, 'path' => $path, 'status' => $status, 'retries' => $attempt]);

                throw new BrevoUnavailable(
                    $status === null
                        ? 'Brevo could not be reached. Please try again in a few minutes.'
                        : "Brevo is busy or having problems (code {$status}). Please try again in a few minutes.",
                    $status,
                    $attempt,
                );
            }

            $this->lastRetries = $attempt + 1;
            Sleep::for($delay)->seconds();
        }
    }

    protected function delayFor(?Response $response, int $attempt): int
    {
        $retryAfter = $response?->header('Retry-After');
        if (is_numeric($retryAfter)) {
            return max(1, min(60, (int) $retryAfter));
        }

        return min(2 ** $attempt, 30); // 1, 2, 4, 8, 16 s
    }
}

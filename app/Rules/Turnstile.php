<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Turnstile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.turnstile.secret_key');

        // No secret configured ⇒ skip (local dev / preview environments).
        if (empty($secret)) {
            return;
        }

        if (empty($value) || ! is_string($value)) {
            $fail('Please complete the verification challenge.');

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);

            $payload = $response->json();

            if (! ($payload['success'] ?? false)) {
                Log::warning('Turnstile verification failed', [
                    'errors' => $payload['error-codes'] ?? null,
                    'ip' => request()->ip(),
                ]);
                $fail('Verification failed. Please try again.');
            }
        } catch (\Throwable $e) {
            Log::error('Turnstile siteverify unreachable: '.$e->getMessage());
            $fail('Could not verify your request right now. Please try again.');
        }
    }
}

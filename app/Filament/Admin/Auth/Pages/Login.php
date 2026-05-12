<?php

namespace App\Filament\Admin\Auth\Pages;

use App\Rules\Turnstile;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /** Token captured from the Turnstile widget via Livewire JS callback. */
    public string $turnstileToken = '';

    public function authenticate(): ?LoginResponse
    {
        if (! empty(config('services.turnstile.site_key'))) {
            $validator = Validator::make(
                ['cf-turnstile-response' => $this->turnstileToken],
                ['cf-turnstile-response' => ['required', new Turnstile()]],
                ['cf-turnstile-response.required' => 'Please complete the verification challenge.'],
            );

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    'data.turnstile' => $validator->errors()->first('cf-turnstile-response'),
                ]);
            }
        }

        return parent::authenticate();
    }
}

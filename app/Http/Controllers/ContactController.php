<?php

namespace App\Http\Controllers;

use App\Models\ContactInquiry;
use App\Notifications\NewContactInquiry;
use App\Rules\Turnstile;
use App\Support\StaffMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
            // Turnstile is mandatory whenever it is configured — `nullable`
            // would let a bot skip the check by omitting the field entirely.
            'cf-turnstile-response' => config('services.turnstile.site_key')
                ? ['required', new Turnstile]
                : ['nullable', new Turnstile],
        ], [
            'cf-turnstile-response.required' => 'Please complete the verification challenge.',
        ]);

        $inquiry = ContactInquiry::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        StaffMail::send(new NewContactInquiry($inquiry), "contact inquiry #{$inquiry->id}");

        return redirect()
            ->route('cms.page', 'contact')
            ->with('contact_success', 'Thanks — your message has reached our team. We reply within one business day.')
            ->withFragment('contact-form');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SparePartInquiry;
use App\Notifications\NewSparePartInquiry;
use App\Rules\Turnstile;
use App\Settings\GeneralSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SparePartController extends Controller
{
    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['required', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'model_name' => ['nullable', 'string', 'max:120'],
            'chassis_no' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'string', 'max:20'],
            'engine_model' => ['nullable', 'string', 'max:80'],
            'condition' => ['nullable', 'in:New,Used'],
            'shipping_method' => ['nullable', 'in:Any,DHL,FedEx,EMS'],
            'parts_description' => ['required', 'string', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:2'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            // Turnstile is mandatory whenever it is configured — `nullable`
            // would let a bot skip the check by omitting the field entirely.
            'cf-turnstile-response' => config('services.turnstile.site_key')
                ? ['required', new Turnstile]
                : ['nullable', new Turnstile],
        ], [
            'cf-turnstile-response.required' => 'Please complete the verification challenge.',
        ]);

        $stored = [];
        foreach ($request->file('attachments', []) as $file) {
            $stored[] = $file->store('spare-part-inquiries', 'local');
        }

        $inquiry = SparePartInquiry::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'country' => $validated['country'] ?? null,
            'address' => $validated['address'] ?? null,
            'model_name' => $validated['model_name'] ?? null,
            'chassis_no' => $validated['chassis_no'] ?? null,
            'year' => $validated['year'] ?? null,
            'engine_model' => $validated['engine_model'] ?? null,
            'condition' => $validated['condition'] ?? null,
            'shipping_method' => $validated['shipping_method'] ?? null,
            'parts_description' => $validated['parts_description'],
            'attachments' => $stored ?: null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        try {
            $to = app(GeneralSettings::class)->contact_email ?: config('mail.from.address');
            Notification::route('mail', $to)->notify(new NewSparePartInquiry($inquiry));
        } catch (\Throwable $e) {
            Log::warning('Spare-part inquiry mail failed: '.$e->getMessage());
        }

        return redirect()
            ->route('cms.page', 'order-spareparts')
            ->with('spareparts_success', 'Thanks — your spare-part request has reached our team. We will send a quotation after checking availability.')
            ->withFragment('spareparts-form');
    }
}

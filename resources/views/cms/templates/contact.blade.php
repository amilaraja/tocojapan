<x-layouts.cms :page="$page">
    @php
        $d = $page->data ?? [];
        $companyName = $d['company_name'] ?? 'TOCO International Co., Ltd';
        $addr1 = $d['address_line_1'] ?? '';
        $addr2 = $d['address_line_2'] ?? '';
        $fax = $d['fax'] ?? null;
        $phone = $general->contact_phone ?? null;
        $whatsapp = $general->whatsapp_number ?? null;
        $email = $general->contact_email ?? 'info@tocojapan.com';
        $mapUrl = $d['map_embed_url'] ?? '';
        $officeHours = $d['office_hours'] ?? null;
        $waDigits = $whatsapp ? preg_replace('/\D+/', '', $whatsapp) : null;
        $telDigits = $phone ? preg_replace('/[^\d+]/', '', $phone) : null;
        $waTeam = collect($d['whatsapp_numbers'] ?? [])
            ->map(fn ($w) => [
                'label' => trim($w['label'] ?? ''),
                'number' => trim($w['number'] ?? ''),
                'digits' => preg_replace('/\D+/', '', $w['number'] ?? ''),
            ])
            ->filter(fn ($w) => $w['digits'] !== '')
            ->values();
    @endphp

    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1100px] mx-auto px-6 py-12 md:py-16">
            @if (! empty($d['kicker']))
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $d['kicker'] }}</p>
            @endif
            <h1 class="text-3xl md:text-5xl font-extrabold mt-2 leading-tight">
                {{ $d['headline'] ?? $page->title }}
            </h1>
            @if (! empty($d['intro']))
                <div class="mt-4 text-white/80 max-w-2xl prose prose-invert">{!! $d['intro'] !!}</div>
            @endif
        </div>
    </section>

    <section class="max-w-[1100px] mx-auto px-6 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Contact details --}}
            <div class="bg-white border border-line rounded-sm p-6">
                <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Contact details</p>
                <p class="text-lg font-extrabold text-toco-navy mt-1">{{ $companyName }}</p>

                @if ($addr1 || $addr2)
                    <p class="text-sm text-ink-soft mt-3 leading-relaxed">
                        {{ $addr1 }}@if ($addr1 && $addr2)<br>@endif{{ $addr2 }}
                    </p>
                @endif

                <dl class="text-sm mt-5 divide-y divide-line border-t border-line">
                    @if ($phone)
                        <div class="flex items-center justify-between py-2.5">
                            <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Phone</dt>
                            <dd><a href="tel:{{ $telDigits }}" class="font-semibold text-toco-navy hover:text-toco-red">{{ $phone }}</a></dd>
                        </div>
                    @endif
                    @if ($fax)
                        <div class="flex items-center justify-between py-2.5">
                            <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Fax</dt>
                            <dd class="font-semibold text-toco-navy">{{ $fax }}</dd>
                        </div>
                    @endif
                    @if ($whatsapp)
                        <div class="flex items-center justify-between py-2.5">
                            <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-soft inline-flex items-center gap-1.5">
                                <x-icons.whatsapp class="w-3.5 h-3.5 shrink-0" />
                                WhatsApp
                            </dt>
                            <dd><a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener" class="font-semibold text-toco-navy hover:text-toco-red">{{ $whatsapp }}</a></dd>
                        </div>
                    @endif
                    @if ($officeHours)
                        <div class="flex items-center justify-between py-2.5">
                            <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Office hours</dt>
                            <dd class="font-semibold text-toco-navy">{{ $officeHours }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between py-2.5">
                        <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Email</dt>
                        <dd><a href="mailto:{{ $email }}" class="font-semibold text-toco-navy hover:text-toco-red">{{ $email }}</a></dd>
                    </div>
                </dl>

                @if ($waTeam->isNotEmpty())
                    <div class="mt-5">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-2">WhatsApp our sales team</p>
                        <div class="grid gap-2">
                            @foreach ($waTeam as $w)
                                <a href="https://wa.me/{{ $w['digits'] }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-2.5 bg-[#25D366] hover:bg-[#1ebe57] text-white font-bold text-xs px-3.5 py-2.5 rounded-sm">
                                    <svg class="shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.2-1.7-.8-2-.9-.3-.1-.5-.2-.7.2-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-1.7-.9-2.9-1.6-4-3.5-.3-.5.3-.5.8-1.6.1-.2 0-.4 0-.5-.1-.2-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.2.2 2.1 3.3 5.2 4.6 1.9.8 2.7.9 3.6.8.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3M12 2a10 10 0 0 0-8.6 15l-1.3 4.7L7 20.4A10 10 0 1 0 12 2"/></svg>
                                    <span class="tracking-wide">{{ $w['number'] }}</span>
                                    @if ($w['label'])
                                        <span class="ml-auto text-white/80 font-medium normal-case">{{ $w['label'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @elseif ($waDigits)
                    <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener"
                       class="mt-5 inline-flex items-center justify-center gap-2 w-full bg-[#25D366] hover:bg-[#1ebe57] text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.2-1.7-.8-2-.9-.3-.1-.5-.2-.7.2-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-1.7-.9-2.9-1.6-4-3.5-.3-.5.3-.5.8-1.6.1-.2 0-.4 0-.5-.1-.2-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.2.2 2.1 3.3 5.2 4.6 1.9.8 2.7.9 3.6.8.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3M12 2a10 10 0 0 0-8.6 15l-1.3 4.7L7 20.4A10 10 0 1 0 12 2"/></svg>
                        Chat on WhatsApp
                    </a>
                @endif
            </div>

            {{-- Map --}}
            @if ($mapUrl)
                <div class="bg-white border border-line rounded-sm overflow-hidden min-h-[320px]">
                    <iframe src="{{ $mapUrl }}" class="w-full h-full min-h-[320px]" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map to {{ $companyName }}"></iframe>
                </div>
            @else
                <div class="bg-toco-silver-2 border border-line rounded-sm flex items-center justify-center text-ink-soft min-h-[320px]">
                    <p class="font-mono text-[10px] uppercase tracking-widest">Map embed not configured</p>
                </div>
            @endif
        </div>

        {{-- Inquiry form --}}
        <div id="contact-form" class="bg-white border border-line rounded-sm p-6 md:p-8 mt-6">
            <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Send an inquiry</p>
            <h2 class="text-xl font-extrabold text-toco-navy mt-1">Ask us anything</h2>
            <p class="text-sm text-ink-soft mt-2">Tell us what you're looking for — a specific model, a shipping quote, or import advice. We reply within one business day.</p>

            @if (session('contact_success'))
                <div class="mt-5 bg-green-50 border border-green-200 text-green-800 text-sm rounded-sm px-4 py-3">
                    {{ session('contact_success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-sm px-4 py-3">
                    Please correct the highlighted fields and try again.
                </div>
            @endif

            <form method="POST" action="{{ route('contact.submit') }}#contact-form" class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf
                <div>
                    <label for="c-name" class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Your name *</label>
                    <input id="c-name" name="name" type="text" required value="{{ old('name') }}" class="w-full text-sm @error('name') border-red-400 @enderror">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="c-email" class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Email *</label>
                    <input id="c-email" name="email" type="email" required value="{{ old('email') }}" class="w-full text-sm @error('email') border-red-400 @enderror">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="c-phone" class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Phone / WhatsApp</label>
                    <input id="c-phone" name="phone" type="text" value="{{ old('phone') }}" class="w-full text-sm">
                </div>
                <div>
                    <label for="c-subject" class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Subject</label>
                    <input id="c-subject" name="subject" type="text" value="{{ old('subject') }}" class="w-full text-sm">
                </div>
                <div class="md:col-span-2">
                    <label for="c-message" class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Message *</label>
                    <textarea id="c-message" name="message" rows="5" required class="w-full text-sm @error('message') border-red-400 @enderror">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <x-turnstile class="!mt-0" />
                    <button type="submit" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-sm shrink-0">
                        Send inquiry
                    </button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.cms>

{{-- TOCO CRM: one purchase or enquiry, by secret link (CRM-POR-002..006). --}}
@php
    use App\Modules\Crm\Domain\Crm;
    $isSale = $case->isVehicleSale();
    $done = fn ($key) => (bool) optional($steps->flatten()->first(fn ($s) => $s->template->key === $key))->isDone();
    $tz = 'Asia/Tokyo';
@endphp
<x-layouts.site :title="$case->reference.' — Toco Japan'">
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1100px] mx-auto px-6 py-8">
            @auth<a href="{{ route('crm.portal.index') }}" class="text-xs uppercase tracking-widest text-white/70 hover:text-white">← My purchases</a>@endauth
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold mt-2">{{ $case->reference }}</p>
            <h1 class="text-2xl md:text-3xl font-extrabold mt-1">{{ $case->vehicle ? trim($case->vehicle->title) : ($case->subject ?: Crm::TYPES[$case->type]) }}</h1>
            @if ($next)<p class="mt-2 text-white/85">{{ $next }}</p>@endif
        </div>
    </section>

    <div class="bg-surface py-8">
        <div class="max-w-[1100px] mx-auto px-6 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-sm px-4 py-3">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-sm px-4 py-3">{{ $errors->first() }}</div>
            @endif

            @if ($isSale)
                {{-- Five-step tracker (CRM-STG-001, POR-002) --}}
                <div class="bg-white border border-line rounded-sm p-5">
                    <ol class="grid grid-cols-5 gap-2">
                        @foreach (Crm::STAGES as $n => $name)
                            @php $state = $case->state === 'won' || $n < $case->stage ? 'done' : ($n === $case->stage ? 'now' : 'next'); @endphp
                            <li class="text-center">
                                <span class="mx-auto grid place-items-center w-9 h-9 rounded-full text-sm font-extrabold {{ $state === 'done' ? 'bg-emerald-600 text-white' : ($state === 'now' ? 'bg-toco-red text-white' : 'bg-toco-silver-2 text-ink-soft') }}">{{ sprintf('%02d', $n) }}</span>
                                <span class="block mt-2 text-[11px] font-semibold {{ $state === 'next' ? 'text-ink-soft' : 'text-toco-navy' }}">{{ $name }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-6 items-start">
                    @include('crm.portal.partials.sidebar')
                    <div class="space-y-4">
                        @foreach (Crm::STAGES as $n => $name)
                            @continue($n > max(1, (int) $case->stage) && $case->state !== 'won')
                            <div class="bg-white border border-line rounded-sm p-5">
                                <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Step {{ sprintf('%02d', $n) }}</p>
                                <h2 class="font-extrabold text-toco-navy">{{ $name }}</h2>
                                <p class="text-sm text-ink-soft mt-1">{{ Crm::STAGE_DESCRIPTIONS[$n] }}</p>
                                <ul class="mt-3 space-y-2 text-sm">
                                    @foreach ($steps->get($n, collect()) as $step)
                                        <li class="flex items-start gap-2">
                                            <span class="mt-0.5 grid place-items-center w-5 h-5 rounded-full shrink-0 {{ $step->isDone() ? 'bg-emerald-600 text-white' : 'border border-line' }}">✓</span>
                                            <span class="{{ $step->isDone() ? 'text-ink' : 'text-ink-soft' }}">
                                                {{ $step->template->customerLabel() }}
                                                @if ($step->isDone())<span class="text-ink-soft">· {{ $step->done_at->timezone($tz)->format('j M Y') }}</span>@endif
                                                @php $d = (array) $step->data; @endphp
                                                @if ($step->isDone() && $step->template->key === 'vessel_booked' && ! empty($d['vessel_name']))
                                                    <span class="block text-ink-soft">Vessel {{ $d['vessel_name'] }}{{ ! empty($d['voyage_no']) ? ' · voyage '.$d['voyage_no'] : '' }}{{ ! empty($d['etd']) ? ' · sails '.\Illuminate\Support\Carbon::parse($d['etd'])->format('j M Y') : '' }}</span>
                                                @elseif ($step->isDone() && $step->template->key === 'bl_issued')
                                                    <span class="block text-ink-soft">B/L {{ $d['bl_number'] ?? '' }}{{ ! empty($d['eta']) ? ' · arrives '.\Illuminate\Support\Carbon::parse($d['eta'])->format('j M Y') : '' }}</span>
                                                @elseif ($step->isDone() && $step->template->key === 'documents_couriered')
                                                    <span class="block text-ink-soft">{{ $d['courier'] ?? '' }}{{ ! empty($d['tracking_number']) ? ' · tracking '.$d['tracking_number'] : '' }}</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>

                                @if ($case->isActive() && $n === (int) $case->stage)
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @if ($n === 1 && $done('quote_sent') && ! $done('order_confirmed'))
                                            <form method="POST" action="{{ route('crm.portal.confirm', [$case->reference, $token, 'order_confirmed']) }}">@csrf
                                                <button class="bg-toco-red text-white font-bold text-sm px-4 py-2 rounded-sm">Confirm my order</button></form>
                                        @endif
                                        @if ($n === 2 && $case->vehicle && in_array($case->payment_mode, [null, 'paypal', 'bank_transfer'], true))
                                            <a href="{{ route('vehicles.show', $case->vehicle->slug) }}" class="bg-toco-red text-white font-bold text-sm px-4 py-2 rounded-sm">Pay now</a>
                                        @endif
                                        @if ($n === 4 && $done('documents_couriered') && ! $done('documents_received'))
                                            <form method="POST" action="{{ route('crm.portal.confirm', [$case->reference, $token, 'documents_received']) }}">@csrf
                                                <button class="bg-toco-red text-white font-bold text-sm px-4 py-2 rounded-sm">Documents received</button></form>
                                        @endif
                                        @if ($n === 5 && ! $done('car_received'))
                                            <form method="POST" action="{{ route('crm.portal.confirm', [$case->reference, $token, 'car_received']) }}">@csrf
                                                <button class="bg-toco-red text-white font-bold text-sm px-4 py-2 rounded-sm">Car received</button></form>
                                        @endif
                                    </div>
                                @endif

                                @if ($n === 2 && $case->isActive() && (int) $case->stage === 2)
                                    @if ($paymentDue)<p class="mt-3 text-sm font-semibold text-toco-navy">Payment due by {{ \Illuminate\Support\Carbon::parse($paymentDue)->timezone($tz)->format('j M Y') }}</p>@endif
                                    @if ($bankDetails)
                                        <div class="mt-3 bg-toco-silver-2 rounded-sm p-3 text-sm">
                                            <p class="font-semibold text-toco-navy mb-1">Bank transfer details</p>
                                            <p class="whitespace-pre-line">{{ $bankDetails }}</p>
                                        </div>
                                    @endif
                                    <form method="POST" enctype="multipart/form-data" action="{{ route('crm.portal.slip', [$case->reference, $token]) }}" class="mt-4 border-t border-line pt-4">@csrf
                                        <label class="block text-sm font-semibold text-toco-navy">Paid by bank transfer? Upload your payment slip</label>
                                        <p class="text-xs text-ink-soft">JPG, PNG or PDF, up to 10 MB.</p>
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <input type="file" name="slip" accept=".jpg,.jpeg,.png,.pdf,.webp" required class="text-sm">
                                            <button class="bg-toco-navy text-white font-bold text-sm px-4 py-2 rounded-sm">Upload slip</button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-6 items-start">
                    @include('crm.portal.partials.sidebar')
                    <div class="bg-white border border-line rounded-sm p-5">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">{{ Crm::TYPES[$case->type] }}</p>
                        <p class="font-extrabold text-toco-navy text-lg">{{ $case->progressLabel() }}</p>
                    </div>
                </div>
            @endif

            {{-- Messages (CRM-POR-005) --}}
            <div class="bg-white border border-line rounded-sm p-5">
                <h2 class="font-extrabold text-toco-navy">Messages</h2>
                @if ($case->isActive())
                    <form method="POST" action="{{ route('crm.portal.message', [$case->reference, $token]) }}" class="mt-3">@csrf
                        <textarea name="body" rows="3" required maxlength="5000" class="w-full border border-line rounded-sm p-3 text-sm" placeholder="Write to {{ $contact }}…"></textarea>
                        <button class="mt-2 bg-toco-red text-white font-bold text-sm px-4 py-2 rounded-sm">Send message</button>
                    </form>
                @endif
                <ul class="mt-4 space-y-3">
                    @forelse ($messages as $message)
                        @php $mine = in_array($message->type, ['customer_message', 'email_in'], true); @endphp
                        <li class="rounded-sm p-3 text-sm {{ $mine ? 'bg-toco-silver-2 ml-6' : 'bg-blue-50 mr-6' }}">
                            <p class="text-[11px] text-ink-soft mb-1">{{ $mine ? 'You' : $contact }} · {{ $message->created_at->timezone($tz)->format('j M Y H:i') }} JST</p>
                            <p class="whitespace-pre-line">{{ $message->body }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-ink-soft">No messages yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts.site>

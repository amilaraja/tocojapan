@php
    use App\Modules\Crm\Domain\Crm;
    use App\Modules\Crm\Models\CrmActivity;
    use App\Modules\Crm\Support\CrmAccess;
    use App\Modules\Crm\Support\CrmUi;
    $customer = $this->getRecord();
    $cases = CrmAccess::scopeCases(\App\Modules\Crm\Models\CrmCase::query())->where('customer_id', $customer->id)->with(['vehicle', 'owner'])->latest('id')->get();
    $won = $cases->where('state', 'won');
    $activity = CrmActivity::whereIn('case_id', $cases->pluck('id'))->with(['user', 'case'])->latest('created_at')->limit(60)->get();
    $muted = 'opacity:.65;font-size:12px';
@endphp
<x-filament-panels::page>
    @include('crm::filament.partials.theme')
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
        <div class="crm-accent" style="--c:#6366f1"><x-filament::section compact>
            <div style="{{ $muted }}">Contact</div>
            <div style="font-size:14px;line-height:1.7;margin-top:4px">
                <div>{{ $customer->email ?? '—' }}</div>
                <div>{{ $customer->phone ?? '' }}</div>
                @if ($customer->whatsappNumber())<div><a href="https://wa.me/{{ $customer->whatsappNumber() }}" target="_blank" style="color:#16a34a;font-weight:600">WhatsApp</a></div>@endif
                <div>{{ $customer->country?->name ?? $customer->country_name }}</div>
                <div style="{{ $muted }}">{{ $customer->user_id ? 'Has a website account' : 'Guest (no account)' }}</div>
            </div>
        </x-filament::section></div>
        <div class="crm-accent" style="--c:#10b981"><x-filament::section compact>
            <div style="{{ $muted }}">Purchases</div>
            <div style="font-size:24px;font-weight:800;color:#10b981">{{ $won->where('type', Crm::TYPE_VEHICLE)->count() }}</div>
            <div style="{{ $muted }}">US$ {{ number_format((float) $won->sum('value_usd')) }} in total</div>
        </x-filament::section></div>
        <div class="crm-accent" style="--c:#0ea5e9"><x-filament::section compact>
            <div style="{{ $muted }}">Open Cases</div>
            <div style="font-size:24px;font-weight:800;color:#0ea5e9">{{ $cases->filter->isActive()->count() }}</div>
        </x-filament::section></div>
    </div>
    @if ($customer->notes)
        <x-filament::section heading="Notes" compact><div style="white-space:pre-line;font-size:14px">{{ $customer->notes }}</div></x-filament::section>
    @endif
    <div class="crm-accent" style="--c:#a855f7"><x-filament::section>
        <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Cases</span> <span class="crm-count">{{ $cases->count() }}</span></x-slot>
        @foreach ($cases as $case)
            <a href="{{ \App\Modules\Crm\Filament\Resources\Cases\CaseResource::getUrl('view', ['record' => $case]) }}" class="crm-row" style="--c:{{ $case->isVehicleSale() ? CrmUi::stageHex($case->stage) : '#64748b' }}">
                <div>
                    <b class="crm-ref">{{ $case->reference }}</b> · {{ $case->vehicle ? $case->vehicle->stock_no.' '.trim($case->vehicle->title) : ($case->subject ?: Crm::TYPES[$case->type]) }}
                    <div style="{{ $muted }}">{{ CrmUi::time($case->created_at, 'j M Y') }} · {{ $case->owner?->name ?? 'Unassigned' }}</div>
                </div>
                <div style="text-align:right;font-size:13px;display:grid;gap:3px;justify-items:end"><span class="crm-soft" style="--c:{{ $case->isVehicleSale() ? CrmUi::stageHex($case->stage) : '#64748b' }}">{{ $case->progressLabel() }}</span><span class="crm-chip" style="--c:{{ CrmUi::stateHex($case->state) }}">{{ Crm::STATES[$case->state] }}</span></div>
            </a>
        @endforeach
    </x-filament::section></div>
    <div class="crm-accent" style="--c:#f59e0b"><x-filament::section>
        <x-slot name="heading"><span class="crm-h"><span class="dot"></span>History</span></x-slot>
        @foreach ($activity as $a)
            <div style="padding:6px 0;border-top:1px solid rgba(127,127,127,.15);font-size:13px">
                <span style="{{ $muted }}">{{ CrmUi::time($a->created_at) }} · {{ $a->case?->reference }} · {{ CrmActivity::TYPES[$a->type] ?? $a->type }} · {{ $a->user?->name ?? (in_array($a->type, ['customer_message', 'email_in']) ? 'Customer' : 'System') }}</span>
                <div style="white-space:pre-line">{{ \Illuminate\Support\Str::limit((string) $a->body, 300) }}</div>
            </div>
        @endforeach
    </x-filament::section></div>
</x-filament-panels::page>

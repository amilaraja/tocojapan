@php
    use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
    use App\Modules\Crm\Models\CrmSlaTimer;
    use App\Modules\Crm\Support\CrmUi;
    $s = $this->sections();
    $muted = 'opacity:.65;font-size:12px';
    $who = fn ($case) => ($case->customer?->displayName() ?? '—').($case->vehicle ? ' · '.$case->vehicle->stock_no : '');
@endphp
<x-filament-panels::page>
    @include('crm::filament.partials.theme')
    <div wire:poll.60s style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start">
        <div class="crm-accent" style="--c:#ef4444"><x-filament::section>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Deadlines (nearest first)</span> <span class="crm-count">{{ $s['deadlines']->count() }}</span></x-slot>
            @forelse ($s['deadlines'] as $t)
                @php $late = $t->breached_at || ($t->paused_at === null && $t->due_at->isPast()); @endphp
                <a href="{{ CaseResource::getUrl('view', ['record' => $t->case_id]) }}" class="crm-row">
                    <div><b class="crm-ref">{{ $t->case->reference }}</b> · {{ $who($t->case) }}<div style="{{ $muted }}">{{ CrmSlaTimer::MEASURES[$t->measure] ?? $t->measure }}</div></div>
                    <span class="crm-chip" style="--c:{{ $t->paused_at ? '#94a3b8' : ($late ? '#dc2626' : ($t->warned_at ? '#d97706' : '#16a34a')) }}">{{ $t->paused_at ? 'paused' : ($late ? 'late' : CrmUi::time($t->due_at)) }}</span>
                </a>
            @empty
                <div style="{{ $muted }}">Nothing waiting on you.</div>
            @endforelse
        </x-filament::section></div>

        <div class="crm-accent" style="--c:#a855f7"><x-filament::section>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Follow-ups due today or overdue</span> <span class="crm-count">{{ $s['followUps']->count() }}</span></x-slot>
            @forelse ($s['followUps'] as $case)
                <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" class="crm-row">
                    <div><b class="crm-ref">{{ $case->reference }}</b> · {{ $who($case) }}<div style="{{ $muted }}">{{ $case->progressLabel() }}</div></div>
                    <div style="white-space:nowrap;color:{{ $case->next_follow_up_at->isPast() ? '#dc2626' : 'inherit' }}">{{ CrmUi::time($case->next_follow_up_at) }}</div>
                </a>
            @empty
                <div style="{{ $muted }}">No follow-ups due.</div>
            @endforelse
        </x-filament::section></div>

        <div class="crm-accent" style="--c:#0ea5e9"><x-filament::section>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Tasks due</span> <span class="crm-count">{{ $s['tasks']->count() }}</span></x-slot>
            @forelse ($s['tasks'] as $task)
                <a href="{{ CaseResource::getUrl('view', ['record' => $task->case_id]) }}" class="crm-row">
                    <div>{{ $task->title }}<div style="{{ $muted }}">{{ $task->case?->reference }} · {{ $task->case?->customer?->displayName() }}</div></div>
                    <div style="white-space:nowrap;color:{{ $task->due_at->isPast() ? '#dc2626' : 'inherit' }}">{{ CrmUi::time($task->due_at) }}</div>
                </a>
            @empty
                <div style="{{ $muted }}">No tasks due.</div>
            @endforelse
        </x-filament::section></div>

        <div class="crm-accent" style="--c:#6366f1"><x-filament::section>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>New for you (24 h)</span> <span class="crm-count">{{ $s['new']->count() }}</span></x-slot>
            @forelse ($s['new'] as $case)
                <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" class="crm-row"><div><b class="crm-ref">{{ $case->reference }}</b> · {{ $who($case) }}</div><div style="{{ $muted }}">{{ CrmUi::ago($case->assigned_at) }}</div></a>
            @empty
                <div style="{{ $muted }}">Nothing new.</div>
            @endforelse
        </x-filament::section></div>

        <div class="crm-accent" style="--c:#f59e0b"><x-filament::section>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>No activity for 3+ days</span> <span class="crm-count">{{ $s['stale']->count() }}</span></x-slot>
            @forelse ($s['stale'] as $case)
                <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" class="crm-row"><div><b class="crm-ref">{{ $case->reference }}</b> · {{ $who($case) }}</div><div style="{{ $muted }}">{{ CrmUi::ago($case->last_staff_activity_at ?? $case->created_at) }}</div></a>
            @empty
                <div style="{{ $muted }}">None.</div>
            @endforelse
        </x-filament::section></div>

        @if ($s['unassigned']->isNotEmpty())
            <div class="crm-accent" style="--c:#eab308"><x-filament::section>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Unassigned (open one and take it)</span> <span class="crm-count">{{ $s['unassigned']->count() }}</span></x-slot>
                @foreach ($s['unassigned'] as $case)
                    <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" class="crm-row"><div><b class="crm-ref">{{ $case->reference }}</b> · {{ $who($case) }}</div><div style="{{ $muted }}">{{ CrmUi::ago($case->created_at) }}</div></a>
                @endforeach
            </x-filament::section></div>
        @endif

        @if ($s['away']->isNotEmpty())
            <div class="crm-accent" style="--c:#64748b"><x-filament::section>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Staff away: Cases to hand over</span> <span class="crm-count">{{ $s['away']->count() }}</span></x-slot>
                @foreach ($s['away'] as $case)
                    <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" class="crm-row"><div><b class="crm-ref">{{ $case->reference }}</b> · {{ $who($case) }}</div><div style="{{ $muted }}">{{ $case->owner?->name }}</div></a>
                @endforeach
                <div style="{{ $muted }};margin-top:6px">Select them in Cases (filter by owner) and use "Assign to…" to move them in bulk.</div>
            </x-filament::section></div>
        @endif

        @if ($s['operations']->isNotEmpty())
            <div class="crm-accent" style="--c:#14b8a6"><x-filament::section>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Shipping and documents (Operations)</span> <span class="crm-count">{{ $s['operations']->count() }}</span></x-slot>
                @foreach ($s['operations'] as $case)
                    <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" class="crm-row"><div><b class="crm-ref">{{ $case->reference }}</b> · {{ $who($case) }}</div><div style="{{ $muted }}">{{ $case->progressLabel() }}</div></a>
                @endforeach
            </x-filament::section></div>
        @endif
    </div>
</x-filament-panels::page>

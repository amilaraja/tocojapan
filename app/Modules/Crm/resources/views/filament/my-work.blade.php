@php
    use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
    use App\Modules\Crm\Models\CrmSlaTimer;
    use App\Modules\Crm\Support\CrmUi;
    $s = $this->sections();
    $muted = 'opacity:.65;font-size:12px';
    $row = 'display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid rgba(127,127,127,.15);font-size:14px';
    $who = fn ($case) => ($case->customer?->displayName() ?? '—').($case->vehicle ? ' · '.$case->vehicle->stock_no : '');
@endphp
<x-filament-panels::page>
    <div wire:poll.60s style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start">
        <x-filament::section heading="Deadlines (nearest first)">
            @forelse ($s['deadlines'] as $t)
                @php $late = $t->breached_at || ($t->paused_at === null && $t->due_at->isPast()); @endphp
                <a href="{{ CaseResource::getUrl('view', ['record' => $t->case_id]) }}" style="{{ $row }}">
                    <div><b>{{ $t->case->reference }}</b> · {{ $who($t->case) }}<div style="{{ $muted }}">{{ CrmSlaTimer::MEASURES[$t->measure] ?? $t->measure }}</div></div>
                    <div style="font-weight:700;color:{{ $late ? '#dc2626' : ($t->warned_at ? '#d97706' : 'inherit') }};white-space:nowrap">{{ $t->paused_at ? 'paused' : ($late ? 'late' : CrmUi::time($t->due_at)) }}</div>
                </a>
            @empty
                <div style="{{ $muted }}">Nothing waiting on you.</div>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Follow-ups due today or overdue">
            @forelse ($s['followUps'] as $case)
                <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" style="{{ $row }}">
                    <div><b>{{ $case->reference }}</b> · {{ $who($case) }}<div style="{{ $muted }}">{{ $case->progressLabel() }}</div></div>
                    <div style="white-space:nowrap;color:{{ $case->next_follow_up_at->isPast() ? '#dc2626' : 'inherit' }}">{{ CrmUi::time($case->next_follow_up_at) }}</div>
                </a>
            @empty
                <div style="{{ $muted }}">No follow-ups due.</div>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Tasks due">
            @forelse ($s['tasks'] as $task)
                <a href="{{ CaseResource::getUrl('view', ['record' => $task->case_id]) }}" style="{{ $row }}">
                    <div>{{ $task->title }}<div style="{{ $muted }}">{{ $task->case?->reference }} · {{ $task->case?->customer?->displayName() }}</div></div>
                    <div style="white-space:nowrap;color:{{ $task->due_at->isPast() ? '#dc2626' : 'inherit' }}">{{ CrmUi::time($task->due_at) }}</div>
                </a>
            @empty
                <div style="{{ $muted }}">No tasks due.</div>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="New for you (24 h)">
            @forelse ($s['new'] as $case)
                <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" style="{{ $row }}"><div><b>{{ $case->reference }}</b> · {{ $who($case) }}</div><div style="{{ $muted }}">{{ CrmUi::ago($case->assigned_at) }}</div></a>
            @empty
                <div style="{{ $muted }}">Nothing new.</div>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="No activity for 3+ days">
            @forelse ($s['stale'] as $case)
                <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" style="{{ $row }}"><div><b>{{ $case->reference }}</b> · {{ $who($case) }}</div><div style="{{ $muted }}">{{ CrmUi::ago($case->last_staff_activity_at ?? $case->created_at) }}</div></a>
            @empty
                <div style="{{ $muted }}">None.</div>
            @endforelse
        </x-filament::section>

        @if ($s['unassigned']->isNotEmpty())
            <x-filament::section heading="Unassigned (open one and take it)">
                @foreach ($s['unassigned'] as $case)
                    <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" style="{{ $row }}"><div><b>{{ $case->reference }}</b> · {{ $who($case) }}</div><div style="{{ $muted }}">{{ CrmUi::ago($case->created_at) }}</div></a>
                @endforeach
            </x-filament::section>
        @endif

        @if ($s['away']->isNotEmpty())
            <x-filament::section heading="Staff away: Cases to hand over">
                @foreach ($s['away'] as $case)
                    <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" style="{{ $row }}"><div><b>{{ $case->reference }}</b> · {{ $who($case) }}</div><div style="{{ $muted }}">{{ $case->owner?->name }}</div></a>
                @endforeach
                <div style="{{ $muted }};margin-top:6px">Select them in Cases (filter by owner) and use "Assign to…" to move them in bulk.</div>
            </x-filament::section>
        @endif

        @if ($s['operations']->isNotEmpty())
            <x-filament::section heading="Shipping and documents (Operations)">
                @foreach ($s['operations'] as $case)
                    <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" style="{{ $row }}"><div><b>{{ $case->reference }}</b> · {{ $who($case) }}</div><div style="{{ $muted }}">{{ $case->progressLabel() }}</div></a>
                @endforeach
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>

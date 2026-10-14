{{-- TOCO CRM: one Case. Inline styles only (the admin theme CSS is prebuilt). --}}
@php
    use App\Modules\Crm\Domain\Crm;
    use App\Modules\Crm\Domain\SlaClock;
    use App\Modules\Crm\Domain\StageEngine;
    use App\Modules\Crm\Models\CrmActivity;
    use App\Modules\Crm\Models\CrmAgentStatus;
    use App\Modules\Crm\Models\CrmSlaTimer;
    use App\Modules\Crm\Support\CrmUi;

    $case = $this->case();
    $case->loadMissing(['customer.country', 'vehicle.supplier', 'owner', 'destPort.country', 'queue']);
    $customer = $case->customer;
    $purchases = $customer?->purchaseCount() ?? 0;
    $steps = $this->steps();
    $times = StageEngine::timeInStages($case);
    $timers = $case->slaTimers()->whereNull('cancelled_at')->latest('id')->get();
    $running = $timers->filter(fn ($t) => $t->met_at === null);
    $tasks = $case->tasks()->with('assignee')->orderByRaw('done_at IS NOT NULL')->orderBy('due_at')->get();
    $documents = $case->getMedia('documents');
    $colour = SlaClock::colour($case);
    $muted = 'opacity:.65;font-size:12px';
    $card = 'border:1px solid rgba(127,127,127,.25);border-radius:10px;padding:16px';
    $pill = fn ($bg, $fg = '#fff') => "display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;background:{$bg};color:{$fg}";
@endphp

<x-filament-panels::page>
    @include('crm::filament.partials.theme')
    {{-- Header (CRM-CAS-004) --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px">
        <div class="crm-accent" style="--c:#6366f1"><x-filament::section compact>
            <div style="{{ $muted }}">Customer</div>
            <div style="font-size:17px;font-weight:700;margin-top:2px">
                <a href="{{ \App\Modules\Crm\Filament\Resources\Customers\CustomerResource::getUrl('view', ['record' => $customer]) }}" style="text-decoration:underline">{{ $customer?->displayName() }}</a>
            </div>
            @if ($purchases > 0)
                <div style="margin-top:4px"><span style="{{ $pill('#16a34a') }}">Returning customer ({{ $purchases }} {{ \Illuminate\Support\Str::plural('purchase', $purchases) }})</span></div>
            @endif
            <div style="margin-top:6px;font-size:13px;line-height:1.6">
                @if ($customer?->email)<div>✉ {{ $customer->email }}</div>@endif
                @if ($customer?->phone)<div>☎ {{ $customer->phone }}</div>@endif
                @if ($customer?->whatsappNumber())<div><a href="https://wa.me/{{ $customer->whatsappNumber() }}" target="_blank" style="color:#16a34a;font-weight:600">WhatsApp {{ $customer->whatsapp ?: $customer->phone }}</a></div>@endif
                @if ($customer?->country || $customer?->country_name)<div>{{ $customer->country?->name ?? $customer->country_name }}</div>@endif
            </div>
        </x-filament::section></div>

        <div class="crm-accent" style="--c:{{ $case->isVehicleSale() ? CrmUi::stageHex($case->stage) : '#0ea5e9' }}"><x-filament::section compact>
            <div style="{{ $muted }}">{{ Crm::TYPES[$case->type] }} · {{ Crm::SOURCES[$case->source] ?? $case->source }}</div>
            @if ($case->vehicle)
                <div style="display:flex;gap:10px;margin-top:6px;align-items:center">
                    @if ($photo = $case->vehicle->cardPhotoUrl())<img src="{{ $photo }}" alt="" style="width:84px;height:60px;object-fit:cover;border-radius:6px">@endif
                    <div style="min-width:0">
                        <div style="font-weight:700">{{ $case->vehicle->stock_no }}</div>
                        <div style="font-size:13px">{{ trim($case->vehicle->title) }}</div>
                        @if ($case->vehicle->isSupplierStock())<span style="{{ $pill('#d97706') }}">Dealer stock: {{ $case->vehicle->supplier?->name }}</span>@endif
                        @if (in_array($case->vehicle->status, ['reserved', 'sold'], true))<span style="{{ $pill($case->vehicle->status === 'sold' ? '#6b7280' : '#2563eb') }}">{{ ucfirst($case->vehicle->status) }}</span>@endif
                    </div>
                </div>
            @elseif ($case->subject)
                <div style="font-weight:600;margin-top:4px">{{ $case->subject }}</div>
            @endif
            <div style="margin-top:8px;font-size:13px;line-height:1.6">
                @if ($case->destPort)<div>To {{ $case->destPort->name }}, {{ $case->destPort->country?->name }}</div>@endif
                @if ($case->value_usd)<div style="font-weight:700">US$ {{ number_format((float) $case->value_usd) }} {{ $case->value_basis }}</div>@endif
                @if ($case->payment_mode)<div>{{ Crm::PAYMENT_MODES[$case->payment_mode] ?? $case->payment_mode }}</div>@endif
            </div>
        </x-filament::section></div>

        <div class="crm-accent" style="--c:{{ CrmUi::stateHex($case->state) }}"><x-filament::section compact>
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                <div style="{{ $muted }}">Owner</div>
                <span style="{{ $pill(CrmUi::stateHex($case->state)) }}">{{ Crm::STATES[$case->state] }}{{ $case->reason ? ': '.$case->reason : '' }}</span>
            </div>
            <div style="font-size:16px;font-weight:700;margin-top:2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span class="crm-avatar" style="--c:{{ CrmUi::personHex($case->owner_id) }}">{{ $case->owner ? CrmUi::initials($case->owner->name) : '?' }}</span>
                {{ $case->owner?->name ?? 'Unassigned' }}
                @if ($case->owner && ($nick = CrmAgentStatus::nicknameFor($case->owner_id)))<span style="font-weight:400;{{ $muted }}">buyers see "{{ $nick }}"</span>@endif
            </div>
            <div style="font-size:13px;line-height:1.7;margin-top:6px">
                @if ($case->queue)<div>Queue: {{ $case->queue->name }}</div>@endif
                <div>Created {{ CrmUi::time($case->created_at) }}</div>
                <div>Customer wrote: <b style="color:{{ $case->last_customer_message_at && (! $case->last_staff_response_at || $case->last_staff_response_at < $case->last_customer_message_at) ? CrmUi::slaHex($colour) : 'inherit' }}">{{ CrmUi::ago($case->last_customer_message_at) }}</b></div>
                <div>We replied: <b>{{ $case->last_staff_response_at ? CrmUi::ago($case->last_staff_response_at) : 'not yet' }}</b></div>
                <div>Next follow-up: <b style="color:{{ $case->isActive() && $case->next_follow_up_at?->isPast() ? '#dc2626' : 'inherit' }}">{{ CrmUi::time($case->next_follow_up_at) }}</b></div>
                @if ($case->stale_at && $case->isActive())<div><span style="{{ $pill('#d97706') }}">Stale: no activity for {{ $case->last_staff_activity_at ? $case->last_staff_activity_at->diffInDays() : $case->created_at->diffInDays() }}+ days</span></div>@endif
            </div>
        </x-filament::section></div>
    </div>

    {{-- SLA clocks (CRM-SLA-005) --}}
    @if ($running->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            @foreach ($running as $timer)
                @php $c = $timer->breached_at || ($timer->paused_at === null && $timer->due_at->isPast()) ? 'danger' : ($timer->warned_at || ($timer->paused_at === null && $timer->warn_at?->isPast()) ? 'warning' : 'success'); @endphp
                <span style="{{ $pill(CrmUi::slaHex($c)) }};font-size:12px;padding:4px 10px">
                    {{ CrmSlaTimer::MEASURES[$timer->measure] ?? $timer->measure }}:
                    {{ $timer->paused_at ? 'paused' : ($c === 'danger' ? 'late since '.CrmUi::time($timer->due_at) : 'due '.CrmUi::time($timer->due_at)) }}
                </span>
            @endforeach
        </div>
    @endif

    {{-- Five-stage bar (CRM-STG-001, 008) --}}
    @if ($case->isVehicleSale())
        <div class="crm-stages">
            @foreach (Crm::STAGES as $n => $name)
                @php
                    $state = $case->state === 'won' || $n < $case->stage ? 'done' : ($n === $case->stage ? 'now' : 'next');
                @endphp
                <div class="crm-stage {{ $state }}" style="--c:{{ CrmUi::stageHex($n) }}">
                    <div class="t">{{ $state === 'done' ? '✓ ' : '' }}{{ sprintf('%02d', $n) }} <span style="font-weight:600">{{ $name }}</span></div>
                    <div class="d">{{ isset($times[$n]) ? CrmUi::duration($times[$n]) : ($state === 'next' ? 'upcoming' : '') }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start">
        {{-- Mini steps (CRM-STG-002..006) --}}
        @if ($case->isVehicleSale())
            <div class="crm-accent" style="--c:{{ CrmUi::stageHex($case->stage) }}"><x-filament::section>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Checklist</span></x-slot>
                @foreach ($steps->groupBy(fn ($s) => $s->template->stage) as $stage => $group)
                    <details @if ($stage === (int) $case->stage) open @endif style="margin-bottom:10px">
                        <summary style="cursor:pointer;font-weight:700;padding:4px 0">
                            <span class="crm-chip" style="--c:{{ CrmUi::stageHex($stage) }}">{{ sprintf('%02d', $stage) }}</span> {{ Crm::STAGES[$stage] }}
                            <span class="crm-soft" style="--c:{{ $group->filter->isDone()->count() === $group->count() ? '#10b981' : CrmUi::stageHex($stage) }}">{{ $group->filter->isDone()->count() }}/{{ $group->count() }} done</span>
                        </summary>
                        <ul style="list-style:none;padding:0;margin:6px 0 0">
                            @foreach ($group as $step)
                                <li style="display:flex;gap:10px;align-items:flex-start;padding:7px 0;border-top:1px solid rgba(127,127,127,.15)">
                                    <span style="flex:none;width:20px;height:20px;border-radius:999px;display:grid;place-items:center;font-size:12px;{{ $step->isDone() ? 'background:'.CrmUi::stageHex($stage).';color:#fff' : 'border:1.5px solid '.CrmUi::stageHex($stage) }}">{{ $step->isDone() ? '✓' : '' }}</span>
                                    <div style="flex:1;min-width:0">
                                        <div style="font-size:14px">
                                            {{ $step->template->label }}
                                            @if (! $step->template->required)<span style="{{ $muted }}">(optional)</span>@endif
                                            @if ($step->template->customer_visible)<span title="The customer sees this step" style="{{ $muted }}">👁</span>@endif
                                            @if ($step->template->responsible_role === 'operations')<span style="{{ $pill('rgba(127,127,127,.3)', 'inherit') }}">Operations</span>@endif
                                        </div>
                                        @if ($step->isDone())
                                            <div style="{{ $muted }}">
                                                {{ $step->done_automatically ? 'Automatically' : ($step->doneBy?->name ?? 'Customer') }} · {{ CrmUi::time($step->done_at) }}
                                                @foreach ((array) $step->data as $k => $v)
                                                    @continue(in_array($k, ['hold_id', 'by_customer'], true) || is_array($v))
                                                    · {{ str_replace('_', ' ', $k) }}: {{ $v }}
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    @if ($case->isActive())
                                        <div style="flex:none">
                                            @if (! $step->isDone() && $this->canTick($step))
                                                {{ ($this->tickStepAction)(['key' => $step->template->key]) }}
                                            @elseif ($step->isDone())
                                                {{ ($this->reopenStepAction)(['key' => $step->template->key]) }}
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </x-filament::section></div>
        @endif

        <div style="display:grid;gap:16px">
            {{-- Linked website records (CRM-CAS-005) --}}
            @if ($linked = $this->linkedRecords())
                <div class="crm-accent" style="--c:#14b8a6"><x-filament::section compact>
                <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Website records</span></x-slot>
                    @foreach ($linked as $row)
                        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;font-size:14px">
                            @if ($row['url'])<a href="{{ $row['url'] }}" style="font-weight:600;text-decoration:underline">{{ $row['label'] }}</a>@else<span>{{ $row['label'] }}</span>@endif
                            <span style="{{ $muted }}">{{ $row['status'] }}</span>
                        </div>
                    @endforeach
                </x-filament::section></div>
            @endif

            {{-- Tasks (CRM-ACT-004) --}}
            <div class="crm-accent" style="--c:#0ea5e9"><x-filament::section compact>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Tasks</span> <span class="crm-count">{{ $tasks->whereNull('done_at')->count() }}</span></x-slot>
                @forelse ($tasks as $task)
                    <div style="display:flex;justify-content:space-between;gap:8px;padding:5px 0;font-size:14px;{{ $task->done_at ? 'opacity:.5;text-decoration:line-through' : '' }}">
                        <div>
                            {{ $task->title }}
                            <div style="{{ $muted }};color:{{ ! $task->done_at && $task->due_at->isPast() ? '#dc2626' : 'inherit' }}">{{ CrmUi::time($task->due_at) }} · {{ $task->assignee?->name }}</div>
                        </div>
                        @unless ($task->done_at){{ ($this->completeTaskAction)(['task' => $task->id]) }}@endunless
                    </div>
                @empty
                    <div style="{{ $muted }}">No tasks. Use Follow up → Add task.</div>
                @endforelse
            </x-filament::section></div>

            {{-- Documents (CRM-ACT-007) --}}
            <div class="crm-accent" style="--c:#a855f7"><x-filament::section compact>
            <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Documents</span> <span class="crm-count">{{ $documents->count() }}</span></x-slot>
                @forelse ($documents as $doc)
                    <div style="display:flex;justify-content:space-between;gap:8px;padding:5px 0;font-size:14px">
                        <div style="min-width:0">
                            <a href="#" wire:click.prevent="downloadDocument({{ $doc->id }})" style="font-weight:600;text-decoration:underline">{{ $doc->getCustomProperty('label') ?: $doc->file_name }}</a>
                            <div style="{{ $muted }}">{{ str_replace('_', ' ', (string) $doc->getCustomProperty('doc_type')) }} · {{ $doc->getCustomProperty('visible_to_customer') ? 'customer can download' : 'internal' }}{{ $doc->getCustomProperty('uploaded_by_customer') ? ' · from customer' : '' }}</div>
                        </div>
                        {{ ($this->toggleDocumentAction)(['media' => $doc->id]) }}
                    </div>
                @empty
                    <div style="{{ $muted }}">No documents. Use Follow up → Upload document.</div>
                @endforelse
            </x-filament::section></div>
        </div>
    </div>

    {{-- Timeline (CRM-ACT-001) --}}
    <div class="crm-accent" style="--c:#f59e0b"><x-filament::section>
    <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Timeline</span></x-slot>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
            @foreach (['all' => 'All', 'messages' => 'Messages', 'emails' => 'Emails', 'notes' => 'Notes', 'calls' => 'Calls & WhatsApp', 'progress' => 'Progress', 'documents' => 'Documents', 'system' => 'System'] as $key => $label)
                <button type="button" wire:click="$set('timelineFilter', '{{ $key }}')" style="padding:3px 10px;border-radius:999px;font-size:12px;border:1px solid rgba(127,127,127,.35);{{ $timelineFilter === $key ? 'background:#6366f1;border-color:#6366f1;color:#fff' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
        @forelse ($this->timeline() as $activity)
            @php
                $isCustomer = in_array($activity->type, ['customer_message', 'email_in'], true);
                $isNote = $activity->type === 'note';
                $bg = $isCustomer ? 'rgba(37,99,235,.08)' : ($isNote ? 'rgba(234,179,8,.14)' : (in_array($activity->type, ['email_out', 'staff_message'], true) ? 'rgba(22,163,74,.08)' : 'transparent'));
            @endphp
            <div style="padding:9px 12px;border-radius:8px;margin-bottom:6px;background:{{ $bg }};{{ $isNote ? 'border-left:3px solid #eab308' : '' }}">
                <div style="{{ $muted }}">
                    <b>{{ CrmActivity::TYPES[$activity->type] ?? $activity->type }}</b>
                    · {{ $isCustomer ? ($customer?->displayName() ?? 'Customer') : ($activity->user?->name ?? 'System') }}
                    · {{ CrmUi::time($activity->created_at) }}
                    @if ($isNote) · internal, never shown to the customer @endif
                    @if ($activity->visible_to_customer && ! $isCustomer) · customer sees this @endif
                </div>
                @if ($activity->subject)<div style="font-weight:600;font-size:14px;margin-top:2px">{{ $activity->subject }}</div>@endif
                @if ($activity->body)<div style="font-size:14px;white-space:pre-line;margin-top:2px">{{ \Illuminate\Support\Str::limit($activity->body, 3000) }}</div>@endif
            </div>
        @empty
            <div style="{{ $muted }}">Nothing yet.</div>
        @endforelse
    </x-filament::section></div>
</x-filament-panels::page>

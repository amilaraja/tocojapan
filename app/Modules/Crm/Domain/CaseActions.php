<?php

namespace App\Modules\Crm\Domain;

use App\Models\User;
use App\Models\VehicleHold;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmCustomer;
use App\Modules\Crm\Models\CrmReason;
use App\Modules\Crm\Models\CrmTask;
use App\Modules\Crm\Support\CrmSettings;
use App\Services\Holds\HoldService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** What staff and customers do to a Case (CRM-CAS, ACT, POR). */
class CaseActions
{
    /**
     * New Case, from intake or by hand (CRM-CAS-003). Starts its stage or
     * status, its clocks and a default follow-up date, assigns it, and
     * tells the owner and the customer.
     *
     * @param  array<string, mixed>  $attributes  type, source, vehicle_id, dest_port_id, dest_country_id, payment_mode, subject, value_usd, value_basis
     * @param  array<string, mixed>  $context  message (first text), skip_customer, created_by
     */
    public static function open(CrmCustomer $customer, array $attributes, array $context = []): CrmCase
    {
        $case = DB::transaction(fn () => CrmCase::create($attributes + [
            'customer_id' => $customer->id,
            'state' => Crm::STATE_OPEN,
            'next_follow_up_at' => now()->addDays(CrmSettings::int('sla.default_follow_up_days')),
        ]));
        $case->setRelation('customer', $customer);

        $by = $context['created_by'] ?? null;
        Timeline::log($case, 'system', 'Case created from '.(Crm::SOURCES[$case->source] ?? $case->source).($by ? ' by staff' : ''),
            ['source' => $case->source], userId: $by?->id);
        if (! empty($context['message'])) {
            Timeline::log($case, $by ? 'note' : 'customer_message', (string) $context['message'], userId: $by?->id, visibleToCustomer: ! $by);
        }

        StageEngine::initialize($case);

        if ($by === null) {
            // A customer is waiting for a first reply (CRM-SLA-001).
            SlaClock::start($case, 'first_response', $case->created_at);
            $case->forceFill(['last_customer_message_at' => $case->created_at])->saveQuietly();
        }
        if ($case->isVehicleSale()) {
            SlaClock::start($case, 'quote_sent', $case->created_at);
        }

        if (! empty($context['owner'])) {
            $owner = $context['owner'];
            $case->forceFill(['owner_id' => $owner->id, 'assigned_at' => now(), 'queue_id' => Assigner::queueFor($case->type, $case->source)?->id])->saveQuietly();
            Timeline::log($case, 'assignment', 'Assigned to '.$owner->name, ['to' => $owner->id], userId: $by?->id);
        } else {
            Assigner::assignNew($case);
        }
        $case->refresh();

        Notifier::event('case_created', $case, [
            'message' => $context['message'] ?? null,
            // Staff-made Cases (phone, walk-in) do not get the automatic acknowledgement.
            'skip_customer' => (bool) ($context['skip_customer'] ?? $by !== null),
        ]);

        return $case;
    }

    /** Staff replied to the customer (email, portal message, call or WhatsApp log). */
    public static function staffResponded(CrmCase $case, ?User $by = null): void
    {
        $case->forceFill(['last_staff_response_at' => now()])->saveQuietly();
        SlaClock::meet($case, 'first_response');
        SlaClock::meet($case, 'customer_reply');
        if ($case->first_response_at === null) {
            $case->forceFill(['first_response_at' => now()])->saveQuietly();
        }

        if ($case->isVehicleSale()) {
            StageEngine::complete($case, 'acknowledged', $by, [], true);
        } elseif ($case->status === 'new') {
            self::setStatus($case, 'in_progress', $by);
        }
    }

    /** A customer wrote: timeline, reply clock, owner told (CRM-ACT-008, SLA-002). */
    public static function customerMessaged(CrmCase $case, string $text, string $via = 'customer_message', array $meta = []): void
    {
        Timeline::log($case, $via, $text, $meta, userId: null, visibleToCustomer: true, subject: $meta['subject'] ?? null);
        $case->forceFill(['last_customer_message_at' => now()])->saveQuietly();

        if (in_array($case->state, [Crm::STATE_WAITING, Crm::STATE_ON_HOLD], true)) {
            self::setState($case, Crm::STATE_OPEN, null, null, ['system' => true]);
        }
        if (! $case->isVehicleSale() && $case->status === 'waiting_customer') {
            $case->forceFill(['status' => 'in_progress'])->saveQuietly();
        }

        // First response still pending: that clock already covers it.
        if (SlaClock::running($case, 'first_response') === null) {
            SlaClock::start($case, 'customer_reply');
        }

        Notifier::event('customer_message', $case, ['message' => mb_strimwidth($text, 0, 600, '…')]);
    }

    public static function addNote(CrmCase $case, string $text, User $by): void
    {
        Timeline::log($case, 'note', $text, userId: $by->id);
    }

    /** Call, WhatsApp chat or meeting (CRM-ACT-006). */
    public static function logContact(CrmCase $case, string $type, string $outcome, ?string $notes, User $by): void
    {
        if (! in_array($type, ['call', 'whatsapp', 'meeting'], true)) {
            return;
        }
        Timeline::log($case, $type, trim($outcome."\n".$notes), ['outcome' => $outcome], userId: $by->id);
        self::staffResponded($case, $by);
    }

    /** Email from the Case (CRM-ACT-002): stored on the timeline, Reply-To the owner. */
    public static function sendEmail(CrmCase $case, string $subject, string $body, User $by, ?string $templateKey = null): void
    {
        if (! $case->customer?->email) {
            throw ValidationException::withMessages(['body' => 'This customer has no email address.']);
        }
        $rendered = TemplateRenderer::render($subject, $body, $case);
        Notifier::sendToCustomer($case, $rendered['subject'], $rendered['body'], 'manual', $by);
        Timeline::log($case, 'email_out', $rendered['body'], ['to' => $case->customer->email, 'template' => $templateKey], userId: $by->id, visibleToCustomer: true, subject: $rendered['subject']);
        self::staffResponded($case, $by);

        if ($templateKey === 'reply_quote' && $case->isVehicleSale()) {
            StageEngine::complete($case, 'quote_sent', $by, ['price' => $case->value_usd, 'basis' => $case->value_basis], true);
        }
    }

    /** Message shown in the customer's account (and emailed). */
    public static function sendPortalMessage(CrmCase $case, string $text, User $by): void
    {
        Timeline::log($case, 'staff_message', $text, userId: $by->id, visibleToCustomer: true);
        $rendered = TemplateRenderer::render('New message about {vehicle}', "Dear {customer_name},\n\n".$text."\n\nBest regards,\n{sender_name}", $case);
        Notifier::sendToCustomer($case, $rendered['subject'], $rendered['body'], 'manual', $by);
        self::staffResponded($case, $by);
    }

    public static function addTask(CrmCase $case, string $title, Carbon $dueAt, ?int $assigneeId, User $by): CrmTask
    {
        $task = $case->tasks()->create(['title' => $title, 'due_at' => $dueAt, 'assignee_id' => $assigneeId ?? $case->owner_id ?? $by->id, 'created_by' => $by->id]);
        Timeline::log($case, 'task', 'Task: '.$title.' (due '.$dueAt->copy()->timezone('Asia/Tokyo')->format('j M H:i').' JST)', ['task_id' => $task->id], userId: $by->id);

        return $task;
    }

    public static function completeTask(CrmTask $task, User $by): void
    {
        if ($task->done_at) {
            return;
        }
        $task->forceFill(['done_at' => now()])->save();
        Timeline::log($task->case, 'task', 'Task done: '.$task->title, ['task_id' => $task->id], userId: $by->id);
    }

    /** Next follow-up date (CRM-ACT-005). */
    public static function setFollowUp(CrmCase $case, ?Carbon $at, ?User $by = null): void
    {
        if ($at === null && $case->isActive()) {
            throw ValidationException::withMessages(['next_follow_up_at' => 'An open Case needs a next follow-up date.']);
        }
        $case->forceFill(['next_follow_up_at' => $at])->save();
    }

    /**
     * Open / Waiting on customer / On hold (CRM-SLA-002, ACT-005).
     *
     * @param  array<string, mixed>  $options  follow_up (Carbon), hold_until, note, system
     */
    public static function setState(CrmCase $case, string $state, ?User $by, ?string $note = null, array $options = []): void
    {
        if (! in_array($state, Crm::ACTIVE_STATES, true)) {
            throw ValidationException::withMessages(['state' => 'Use Won, Lost or Close for finished Cases.']);
        }
        $followUp = $options['follow_up'] ?? $case->next_follow_up_at;
        if ($followUp === null) {
            throw ValidationException::withMessages(['next_follow_up_at' => 'An open Case needs a next follow-up date.']);
        }

        $from = $case->state;
        $case->forceFill([
            'state' => $state,
            'next_follow_up_at' => $followUp,
            'hold_until' => $state === Crm::STATE_ON_HOLD ? ($options['hold_until'] ?? $followUp) : null,
        ])->save();

        if ($state === Crm::STATE_OPEN) {
            SlaClock::resume($case);
        } else {
            SlaClock::pause($case);
        }

        if ($from !== $state) {
            Timeline::log($case, 'state', 'Status: '.Crm::STATES[$state].($note ? "\n".$note : ''), ['from' => $from, 'to' => $state], userId: ($options['system'] ?? false) ? null : $by?->id);
        }
    }

    /** Spare-part and general Cases (SRS §3.4). */
    public static function setStatus(CrmCase $case, string $status, ?User $by, ?string $note = null): void
    {
        if ($case->isVehicleSale() || ! isset(Crm::SUPPORT_STATUSES[$status])) {
            return;
        }
        $from = $case->status;
        $case->forceFill(['status' => $status])->save();
        Timeline::log($case, 'state', 'Status: '.Crm::SUPPORT_STATUSES[$status].($note ? "\n".$note : ''), ['from' => $from, 'to' => $status], userId: $by?->id);

        match ($status) {
            'waiting_customer' => self::setState($case, Crm::STATE_WAITING, $by, null, ['system' => true]),
            'resolved', 'closed' => self::finish($case, Crm::STATE_CLOSED, $status === 'resolved' ? 'Resolved' : 'Closed', null, $by),
            default => $case->state !== Crm::STATE_OPEN && $case->isActive() ? self::setState($case, Crm::STATE_OPEN, $by, null, ['system' => true]) : null,
        };
    }

    /** @param array<string, mixed> $context */
    public static function win(CrmCase $case, ?User $by = null, array $context = []): void
    {
        self::finish($case, Crm::STATE_WON, null, null, $by);
        Notifier::event('case_won', $case, ['skip_customer' => true]);
    }

    /** Lost needs a reason from the list (CRM-CAS-007). */
    public static function lose(CrmCase $case, ?string $reason, ?string $note, User $by): void
    {
        if (! $reason || ! array_key_exists($reason, CrmReason::options('lost'))) {
            throw ValidationException::withMessages(['reason' => 'Choose why this sale was lost.']);
        }
        self::finish($case, Crm::STATE_LOST, $reason, $note, $by);
        self::releaseHold($case, 'Case '.$case->reference.' lost: '.$reason);
        Notifier::event('case_lost', $case);
    }

    /** Closed: duplicate, spam, not a buyer (CRM-CAS-007, NTF-006). */
    public static function close(CrmCase $case, ?string $reason, ?string $note, User $by): void
    {
        if (! $reason || ! array_key_exists($reason, CrmReason::options('close'))) {
            throw ValidationException::withMessages(['reason' => 'Choose why this Case is closed.']);
        }
        self::finish($case, Crm::STATE_CLOSED, $reason, $note, $by);
        self::releaseHold($case, 'Case '.$case->reference.' closed: '.$reason);
    }

    /** Admin reopens a finished Case. */
    public static function reopenCase(CrmCase $case, User $by): void
    {
        if ($case->isActive()) {
            return;
        }
        $case->forceFill(['state' => Crm::STATE_OPEN, 'reason' => null, 'reason_note' => null, 'closed_at' => null,
            'next_follow_up_at' => now()->addDays(CrmSettings::int('sla.default_follow_up_days'))])->save();
        if (! $case->isVehicleSale() && in_array($case->status, ['resolved', 'closed'], true)) {
            $case->forceFill(['status' => 'in_progress'])->save();
        }
        Timeline::log($case, 'state', 'Case reopened', userId: $by->id);
    }

    /** A general enquiry about a car becomes a vehicle sale, history kept (SRS §3.4). */
    public static function convertToVehicleSale(CrmCase $case, ?int $vehicleId, User $by): void
    {
        if ($case->isVehicleSale()) {
            return;
        }
        $case->forceFill(['type' => Crm::TYPE_VEHICLE, 'status' => null, 'vehicle_id' => $vehicleId ?? $case->vehicle_id,
            'queue_id' => Assigner::queueFor(Crm::TYPE_VEHICLE, $case->source)?->id ?? $case->queue_id])->save();
        StageEngine::initialize($case);
        if ($case->first_response_at) {
            StageEngine::complete($case, 'acknowledged', $by, [], true);
        }
        SlaClock::start($case, 'quote_sent');
        Timeline::log($case, 'system', 'Converted to a vehicle sale', userId: $by->id);
    }

    /** Merge $from into $into: activities, links, tasks and documents move (CRM-CAS-006). */
    public static function merge(CrmCase $from, CrmCase $into, User $by): void
    {
        if ($from->id === $into->id) {
            return;
        }
        if ($from->customer_id !== $into->customer_id) {
            throw ValidationException::withMessages(['into' => 'Only Cases of the same customer can be merged. Merge the customers first.']);
        }

        DB::transaction(function () use ($from, $into, $by) {
            $from->activities()->update(['case_id' => $into->id]);
            $from->links()->update(['case_id' => $into->id]);
            $from->tasks()->update(['case_id' => $into->id]);
            $from->getMedia('documents')->each(fn ($media) => $media->move($into, 'documents'));
            $from->slaTimers()->whereNull('met_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
            $from->forceFill(['merged_into_id' => $into->id, 'state' => Crm::STATE_CLOSED, 'reason' => 'Duplicate',
                'reason_note' => 'Merged into '.$into->reference, 'closed_at' => now()])->save();
            Timeline::log($into, 'system', "Case {$from->reference} merged into this Case", ['merged' => $from->id], userId: $by->id);
        });
    }

    /** Merge duplicate customers (CRM-CUS-003). */
    public static function mergeCustomers(CrmCustomer $from, CrmCustomer $into, User $by): void
    {
        if ($from->id === $into->id) {
            return;
        }
        DB::transaction(function () use ($from, $into) {
            CrmCase::where('customer_id', $from->id)->update(['customer_id' => $into->id]);
            foreach (['name', 'phone', 'whatsapp', 'country_id', 'country_name', 'user_id'] as $field) {
                if (blank($into->{$field}) && filled($from->{$field})) {
                    $into->{$field} = $from->{$field};
                }
            }
            $into->notes = trim($into->notes."\n".($from->email ? 'Also: '.$from->email : ''));
            $from->forceFill(['merged_into_id' => $into->id, 'user_id' => null])->save();
            $into->save();
        });
    }

    private static function finish(CrmCase $case, string $state, ?string $reason, ?string $note, ?User $by): void
    {
        $case->forceFill(['state' => $state, 'reason' => $reason, 'reason_note' => $note, 'closed_at' => now(), 'hold_until' => null])->save();
        SlaClock::cancel($case);
        $case->tasks()->whereNull('done_at')->update(['done_at' => now()]);

        $label = Crm::STATES[$state].($reason ? ': '.$reason : '').($note ? "\n".$note : '');
        Timeline::log($case, 'state', $label, ['to' => $state, 'reason' => $reason], userId: $by?->id, visibleToCustomer: $state === Crm::STATE_WON);
    }

    private static function releaseHold(CrmCase $case, string $why): void
    {
        $vehicle = $case->vehicle;
        if (! $vehicle) {
            return;
        }
        $holds = app(HoldService::class);
        $hold = $holds->activeHold($vehicle);
        if ($hold && $hold->buyer_ref === $case->reference && $hold->channel === VehicleHold::CHANNEL_ADMIN) {
            $holds->release($hold, $why);
            Timeline::log($case, 'system', 'Vehicle hold released', userId: null);
        }
    }
}

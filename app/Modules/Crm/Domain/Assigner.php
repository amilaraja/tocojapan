<?php

namespace App\Modules\Crm\Domain;

use App\Models\User;
use App\Modules\Crm\Models\CrmAgentStatus;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmQueue;
use App\Modules\Crm\Support\CrmAccess;
use Illuminate\Support\Facades\DB;

/**
 * Who owns a new Case (CRM-ASN-001..006): the returning customer's previous
 * owner, else the next available member of the matching queue by rotation,
 * skipping staff who are away or at their open-case limit. Nobody available
 * = the unassigned queue, and the admins are told.
 */
class Assigner
{
    public static function queueFor(string $type, string $source): ?CrmQueue
    {
        return CrmQueue::where('is_active', true)->orderBy('sort')->get()
            ->first(fn (CrmQueue $queue) => $queue->accepts($type, $source));
    }

    /** Assign a new Case. Returns the owner, or null when it stays unassigned. */
    public static function assignNew(CrmCase $case): ?User
    {
        $queue = self::queueFor($case->type, $case->source);
        $case->queue_id = $queue?->id;

        $owner = self::previousOwner($case) ?? ($queue ? self::nextInRotation($queue) : null);

        $case->forceFill(['queue_id' => $queue?->id, 'owner_id' => $owner?->id, 'assigned_at' => $owner ? now() : null])->saveQuietly();

        if ($owner) {
            Timeline::log($case, 'assignment', 'Assigned to '.$owner->name.($queue ? ' ('.$queue->name.' queue)' : ''), ['to' => $owner->id, 'auto' => true], userId: null);
            SlaClock::running($case, 'first_response')?->forceFill(['owner_id' => $owner->id])->save();
        } else {
            Timeline::log($case, 'assignment', 'Waiting in the unassigned queue'.($queue ? ' ('.$queue->name.')' : ''), ['auto' => true], userId: null);
        }

        return $owner;
    }

    /** Manual (re)assignment by an admin, one at a time or in bulk (CRM-ASN-005). */
    public static function assign(CrmCase $case, ?User $to, ?string $note = null, ?User $by = null): void
    {
        $from = $case->owner;
        if ((int) $case->owner_id === (int) $to?->id) {
            return;
        }

        $case->forceFill(['owner_id' => $to?->id, 'assigned_at' => $to ? now() : null, 'unassigned_alerted_at' => null])->save();
        $case->setRelation('owner', $to);

        $text = $to
            ? ($from ? "Reassigned from {$from->name} to {$to->name}" : "Assigned to {$to->name}")
            : 'Moved to the unassigned queue'.($from ? " (was {$from->name})" : '');
        Timeline::log($case, 'assignment', $text.($note ? "\nNote: ".$note : ''), ['from' => $from?->id, 'to' => $to?->id], userId: $by?->id ?? auth()->id());

        // Running clocks follow the new owner for reporting.
        $case->slaTimers()->whereNull('met_at')->whereNull('cancelled_at')->update(['owner_id' => $to?->id]);

        if ($to) {
            Notifier::event('case_assigned', $case, ['note' => $note, 'reassigned' => $from !== null]);
        }
    }

    /** The owner of this customer's latest earlier Case, if still active and available (CRM-ASN-004). */
    public static function previousOwner(CrmCase $case): ?User
    {
        $previous = CrmCase::where('customer_id', $case->customer_id)
            ->where('id', '!=', $case->id)
            ->whereNotNull('owner_id')
            ->orderByRaw("CASE WHEN state = 'won' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        $user = $previous?->owner;

        return $user && self::isAvailable($user, null) ? $user : null;
    }

    /** Round robin among available members, after the last one assigned (CRM-ASN-002, 003). */
    public static function nextInRotation(CrmQueue $queue): ?User
    {
        return DB::transaction(function () use ($queue) {
            $queue = CrmQueue::whereKey($queue->id)->lockForUpdate()->first();
            $members = $queue->members()->with('user')->orderBy('id')->get()->filter(fn ($m) => $m->user !== null)->values();
            if ($members->isEmpty()) {
                return null;
            }

            $start = $members->search(fn ($m) => (int) $m->user_id === (int) $queue->last_assigned_user_id);
            $start = $start === false ? -1 : $start;
            $count = $members->count();

            for ($i = 1; $i <= $count; $i++) {
                $member = $members[($start + $i) % $count];
                if (self::isAvailable($member->user, $member->max_open_cases ?? $queue->max_open_cases)) {
                    $queue->forceFill(['last_assigned_user_id' => $member->user_id])->save();

                    return $member->user;
                }
            }

            return null;
        });
    }

    public static function isAvailable(User $user, ?int $maxOpen): bool
    {
        if (! CrmAccess::canUse($user)) {
            return false;
        }
        $status = CrmAgentStatus::where('user_id', $user->id)->first();
        if ($status && $status->isAwayOn(now())) {
            return false;
        }
        if ($maxOpen !== null && $maxOpen > 0) {
            $open = CrmCase::where('owner_id', $user->id)->whereIn('state', Crm::ACTIVE_STATES)->whereNull('merged_into_id')->count();
            if ($open >= $maxOpen) {
                return false;
            }
        }

        return true;
    }
}

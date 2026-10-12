<?php

namespace App\Modules\Crm\Domain;

use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmCase;

/** Writes the Case timeline and keeps the "last activity" clocks (CRM-ACT-001, SLA-004, 005). */
class Timeline
{
    /** @param array<string, mixed> $meta */
    public static function log(CrmCase $case, string $type, ?string $body = null, array $meta = [], ?int $userId = null, bool $visibleToCustomer = false, ?string $subject = null): CrmActivity
    {
        $userId ??= auth()->id();

        $activity = $case->activities()->create([
            'type' => $type,
            'user_id' => $userId,
            'subject' => $subject !== null ? mb_substr($subject, 0, 255) : null,
            'body' => $body,
            'meta' => $meta ?: null,
            'visible_to_customer' => $visibleToCustomer,
            'created_at' => now(),
        ]);

        $updates = ['last_activity_at' => now()];
        if ($userId !== null && in_array($type, CrmActivity::STAFF_WORK, true)) {
            // Staff worked the Case: it is not stale any more.
            $updates += ['last_staff_activity_at' => now(), 'stale_at' => null, 'stale_admin_at' => null];
        }
        $case->forceFill($updates)->saveQuietly();

        return $activity;
    }
}

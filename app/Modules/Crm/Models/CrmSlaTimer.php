<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One SLA clock on a Case: start, pause, warning, breach, met (CRM-SLA-001..006). */
class CrmSlaTimer extends Model
{
    protected $table = 'crm_sla_timers';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime', 'due_at' => 'datetime', 'warn_at' => 'datetime', 'paused_at' => 'datetime', 'warned_at' => 'datetime',
        'breached_at' => 'datetime', 'met_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public const MEASURES = [
        'first_response' => 'First response',
        'customer_reply' => 'Reply to customer',
        'quote_sent' => 'Quote sent',
        'payment' => 'Payment',
        'vessel_booked' => 'Vessel booked',
        'documents_couriered' => 'Documents couriered',
    ];

    /** @return BelongsTo<CrmCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CrmCase::class, 'case_id');
    }

    public function isRunning(): bool
    {
        return $this->met_at === null && $this->cancelled_at === null;
    }
}

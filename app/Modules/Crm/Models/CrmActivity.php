<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry on a Case timeline (CRM-ACT-001). */
class CrmActivity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'crm_activities';

    protected $guarded = [];

    protected $casts = ['meta' => 'array', 'visible_to_customer' => 'bool', 'created_at' => 'datetime'];

    public const TYPES = [
        'email_out' => 'Email sent',
        'email_in' => 'Email received',
        'customer_message' => 'Customer message',
        'staff_message' => 'Message to customer',
        'note' => 'Note',
        'call' => 'Call',
        'whatsapp' => 'WhatsApp',
        'meeting' => 'Meeting',
        'task' => 'Task',
        'stage' => 'Stage',
        'step' => 'Step',
        'assignment' => 'Assignment',
        'document' => 'Document',
        'state' => 'Status',
        'record' => 'Website record',
        'system' => 'System',
    ];

    /** Timeline filter groups (CRM-ACT-001). */
    public const FILTERS = [
        'emails' => ['email_out', 'email_in'],
        'messages' => ['customer_message', 'staff_message', 'email_in'],
        'notes' => ['note'],
        'calls' => ['call', 'whatsapp', 'meeting'],
        'progress' => ['stage', 'step', 'state', 'assignment'],
        'documents' => ['document'],
        'system' => ['system', 'record', 'task'],
    ];

    /** Activities that count as staff working the Case (stale detection, CRM-SLA-004). */
    public const STAFF_WORK = ['email_out', 'staff_message', 'note', 'call', 'whatsapp', 'meeting', 'stage', 'step', 'document', 'task', 'state'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CrmCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CrmCase::class, 'case_id');
    }
}

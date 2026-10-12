<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Follow-up task with a due time and reminder (CRM-ACT-004). */
class CrmTask extends Model
{
    protected $table = 'crm_tasks';

    protected $guarded = [];

    protected $casts = ['due_at' => 'datetime', 'reminded_at' => 'datetime', 'done_at' => 'datetime'];

    /** @return BelongsTo<CrmCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CrmCase::class, 'case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function isOverdue(): bool
    {
        return $this->done_at === null && $this->due_at->isPast();
    }
}

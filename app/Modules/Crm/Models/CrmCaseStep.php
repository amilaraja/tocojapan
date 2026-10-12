<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A mini step on one Case (CRM-STG-004, 008). */
class CrmCaseStep extends Model
{
    protected $table = 'crm_case_steps';

    protected $guarded = [];

    protected $casts = [
        'done_at' => 'datetime',
        'done_automatically' => 'bool',
        'data' => 'array',
    ];

    /** @return BelongsTo<CrmStepTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CrmStepTemplate::class, 'step_template_id');
    }

    /** @return BelongsTo<CrmCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CrmCase::class, 'case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }
}

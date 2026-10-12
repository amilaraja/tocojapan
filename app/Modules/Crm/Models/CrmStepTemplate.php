<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;

/** A configured mini step of a stage (CRM-STG-002). */
class CrmStepTemplate extends Model
{
    protected $table = 'crm_step_templates';

    protected $guarded = [];

    protected $casts = [
        'required' => 'bool',
        'customer_visible' => 'bool',
        'is_active' => 'bool',
        'fields' => 'array',
    ];

    public function customerLabel(): string
    {
        return $this->customer_label ?: $this->label;
    }
}

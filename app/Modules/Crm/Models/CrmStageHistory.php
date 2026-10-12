<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;

/** Stage entries and exits, for time-in-stage (CRM-STG-008). */
class CrmStageHistory extends Model
{
    protected $table = 'crm_stage_history';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['entered_at' => 'datetime', 'left_at' => 'datetime'];
}

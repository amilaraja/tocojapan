<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;

/** A saved list filter for one user (CRM-DSH-006). */
class CrmSavedView extends Model
{
    protected $table = 'crm_saved_views';

    protected $guarded = [];

    protected $casts = ['filters' => 'array'];
}

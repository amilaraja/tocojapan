<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;

class CrmHoliday extends Model
{
    protected $table = 'crm_holidays';

    protected $guarded = [];

    protected $casts = ['date' => 'date'];
}

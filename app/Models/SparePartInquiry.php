<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparePartInquiry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
        'is_handled' => 'bool',
        'handled_at' => 'datetime',
    ];
}

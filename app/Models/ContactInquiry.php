<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_handled' => 'bool',
        'handled_at' => 'datetime',
    ];
}

<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedMessage extends Model
{
    protected $table = 'mailer_processed_messages';

    protected $guarded = [];

    protected $casts = ['received_at' => 'datetime'];
}

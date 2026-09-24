<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Model;

class ImportState extends Model
{
    protected $table = 'mailer_import_state';

    protected $guarded = [];

    protected $casts = [
        'last_checkpoint_at' => 'datetime',
        'lock_until' => 'datetime',
        'failure_alert_sent_at' => 'datetime',
        'backfill_cursor' => 'array',
    ];
}

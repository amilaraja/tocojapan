<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Model;

/** Raw key/value row. Read and write through MailerSettings, never directly. */
class MailerSetting extends Model
{
    protected $table = 'mailer_settings';

    protected $guarded = [];

    protected $casts = ['encrypted' => 'bool'];
}

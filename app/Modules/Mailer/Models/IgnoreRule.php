<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Model;

class IgnoreRule extends Model
{
    protected $table = 'mailer_ignore_rules';

    protected $guarded = [];
}

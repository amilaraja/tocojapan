<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Model;

class EmailImage extends Model
{
    public $timestamps = false;

    protected $table = 'mailer_email_images';

    protected $guarded = [];

    protected $casts = ['generated_at' => 'datetime'];
}

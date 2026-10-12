<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;

/** Every notification sent or failed (CRM-NTF-005). */
class CrmNotificationLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'crm_notification_log';

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];
}

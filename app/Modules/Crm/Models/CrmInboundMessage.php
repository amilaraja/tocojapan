<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;

/** A mailbox message the CRM already looked at (dedupe for buyer replies). */
class CrmInboundMessage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'crm_inbound_messages';

    protected $guarded = [];

    protected $casts = ['received_at' => 'datetime', 'created_at' => 'datetime'];
}

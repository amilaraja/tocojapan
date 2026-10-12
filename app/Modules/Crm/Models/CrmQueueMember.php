<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmQueueMember extends Model
{
    protected $table = 'crm_queue_members';

    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CrmQueue, $this> */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(CrmQueue::class, 'queue_id');
    }
}

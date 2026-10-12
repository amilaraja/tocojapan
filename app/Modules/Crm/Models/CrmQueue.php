<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A group of staff who receive Cases of some types by rotation (CRM-ASN-001). */
class CrmQueue extends Model
{
    protected $table = 'crm_queues';

    protected $guarded = [];

    protected $casts = ['case_types' => 'array', 'sources' => 'array', 'is_active' => 'bool'];

    /** @return HasMany<CrmQueueMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CrmQueueMember::class, 'queue_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'crm_queue_members', 'queue_id', 'user_id')->withPivot('max_open_cases')->withTimestamps();
    }

    public function accepts(string $type, string $source): bool
    {
        return (empty($this->case_types) || in_array($type, $this->case_types, true))
            && (empty($this->sources) || in_array($source, $this->sources, true));
    }
}

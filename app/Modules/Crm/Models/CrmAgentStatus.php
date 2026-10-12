<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Away status, time zone and notification preferences per staff member (CRM-ASN-007, NFR-005, NTF-004). */
class CrmAgentStatus extends Model
{
    protected $table = 'crm_agent_status';

    protected $guarded = [];

    protected $casts = ['away_from' => 'date', 'away_until' => 'date', 'can_pick_unassigned' => 'bool'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function for(int $userId): self
    {
        return self::firstOrNew(['user_id' => $userId]);
    }

    public function isAwayOn(\DateTimeInterface $at): bool
    {
        $day = \Illuminate\Support\Carbon::instance($at)->setTimezone('Asia/Tokyo')->toDateString();

        return $this->away_from !== null
            && $this->away_from->toDateString() <= $day
            && ($this->away_until === null || $this->away_until->toDateString() >= $day);
    }

    /** The name buyers see for this staff member (email sign-off, sender, portal). */
    public static function nicknameFor(?int $userId): ?string
    {
        $nickname = $userId ? trim((string) self::where('user_id', $userId)->value('nickname')) : '';

        return $nickname !== '' ? $nickname : null;
    }

    public static function timezoneFor(?int $userId): string
    {
        return $userId ? (self::where('user_id', $userId)->value('timezone') ?: 'Asia/Tokyo') : 'Asia/Tokyo';
    }
}

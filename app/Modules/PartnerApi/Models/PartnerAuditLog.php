<?php

namespace App\Modules\PartnerApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only record of every partner-network change (FR-PRT-03, FR-HLD-08):
 * who, when, before and after. Rows can never be updated or deleted.
 */
class PartnerAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The partner audit log is append-only.'));
        static::deleting(fn () => throw new LogicException('The partner audit log is append-only.'));
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerClient::class, 'partner_client_id');
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(
        ?Partner $partner,
        string $action,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?PartnerClient $client = null,
    ): self {
        $user = auth()->user();
        $request = app()->runningInConsole() ? null : request();

        return self::create([
            'partner_id' => $partner?->getKey(),
            'partner_client_id' => $client?->getKey(),
            'action' => $action,
            'actor_type' => $user ? 'user' : 'system',
            'actor_id' => $user?->getKey(),
            'actor_name' => $user?->name,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip' => $request?->ip(),
        ]);
    }
}

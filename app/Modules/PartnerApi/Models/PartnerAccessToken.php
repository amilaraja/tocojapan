<?php

namespace App\Modules\PartnerApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Short-lived bearer token from the client-credentials exchange (§8.1).
 * Only its SHA-256 is stored; the token itself is high-entropy random.
 */
class PartnerAccessToken extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerClient::class, 'partner_client_id');
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public static function findByPlainToken(string $token): ?self
    {
        return self::with('client.partner')->where('token_hash', hash('sha256', $token))->first();
    }
}

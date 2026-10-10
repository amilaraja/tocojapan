<?php

namespace App\Modules\PartnerApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One partner API call or image fetch (SRS §8.6). Pruned after 12 months. */
class PartnerRequestLog extends Model
{
    public const UPDATED_AT = null;

    public const KIND_API = 'api';

    public const KIND_IMAGE = 'image';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['counted' => 'boolean', 'created_at' => 'datetime'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerClient::class, 'partner_client_id');
    }
}

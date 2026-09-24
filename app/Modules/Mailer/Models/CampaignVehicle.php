<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignVehicle extends Model
{
    public $timestamps = false;

    protected $table = 'mailer_campaign_vehicles';

    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'added_at' => 'datetime',
        'position' => 'integer',
    ];

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}

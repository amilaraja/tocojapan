<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class OrderMessage extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['order_id', 'user_id', 'from_admin', 'body', 'read_by_customer_at', 'read_by_admin_at'];

    protected $casts = [
        'from_admin' => 'boolean',
        'read_by_customer_at' => 'datetime',
        'read_by_admin_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }
}

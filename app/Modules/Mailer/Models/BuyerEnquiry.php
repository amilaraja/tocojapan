<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One enquiry email from a buyer: extracted fields only, never the text (TOC-LOG-003).
 *
 * @property int $id
 * @property int $buyer_id
 * @property string $gmail_message_id
 * @property ?\Illuminate\Support\Carbon $received_at
 * @property ?string $kind
 * @property ?string $make
 * @property ?string $model
 * @property ?int $year
 * @property ?string $drive
 * @property ?string $budget
 * @property ?string $country_code
 * @property ?string $port
 * @property ?array<string, mixed> $details
 */
class BuyerEnquiry extends Model
{
    protected $table = 'mailer_buyer_enquiries';

    protected $guarded = [];

    protected $casts = [
        'received_at' => 'datetime',
        'year' => 'integer',
        'details' => 'array',
    ];

    /** @var array<string, string> */
    public const KINDS = [
        'stock' => 'Stock enquiry',
        'auction' => 'Auction enquiry',
        'request' => 'Buyer request',
        'other' => 'Other',
    ];

    /** @return BelongsTo<Buyer, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function vehicleLabel(): string
    {
        return trim(($this->year ? $this->year.' ' : '').($this->make ?? '').' '.($this->model ?? ''));
    }
}

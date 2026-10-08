<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One buyer per email address, built from enquiry emails (TOC-BUY-003).
 *
 * @property int $id
 * @property string $email
 * @property ?string $title
 * @property ?string $first_name
 * @property ?string $last_name
 * @property ?string $country
 * @property ?string $country_code
 * @property ?string $port
 * @property ?string $phone
 * @property ?string $phone_e164
 * @property ?string $buyer_type
 * @property ?int $approved_sender_id
 * @property int $enquiry_count
 * @property ?\Illuminate\Support\Carbon $first_enquiry_at
 * @property ?\Illuminate\Support\Carbon $last_enquiry_at
 * @property ?\Illuminate\Support\Carbon $brevo_synced_at
 * @property ?int $brevo_country_list_id
 * @property-read ?BuyerEnquiry $latestEnquiry
 */
class Buyer extends Model
{
    protected $table = 'mailer_buyers';

    protected $guarded = [];

    protected $casts = [
        'enquiry_count' => 'integer',
        'first_enquiry_at' => 'datetime',
        'last_enquiry_at' => 'datetime',
        'brevo_synced_at' => 'datetime',
    ];

    public const TYPES = ['individual' => 'Individual', 'dealer' => 'Dealer / importer'];

    /** @return HasMany<BuyerEnquiry, $this> */
    public function enquiries(): HasMany
    {
        return $this->hasMany(BuyerEnquiry::class);
    }

    /** @return HasOne<BuyerEnquiry, $this> */
    public function latestEnquiry(): HasOne
    {
        return $this->hasOne(BuyerEnquiry::class)->latestOfMany('received_at');
    }

    /** @return BelongsTo<ApprovedSender, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(ApprovedSender::class, 'approved_sender_id');
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    /** wa.me link for click-to-chat, when the phone number could be normalised. */
    public function whatsappUrl(): ?string
    {
        return $this->phone_e164 ? 'https://wa.me/'.ltrim($this->phone_e164, '+') : null;
    }
}

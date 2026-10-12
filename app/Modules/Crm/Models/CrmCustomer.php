<?php

namespace App\Modules\Crm\Models;

use App\Models\Country;
use App\Models\User;
use App\Modules\Crm\Domain\Crm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A person or company that contacted TOCO (CRM-CUS-001). */
class CrmCustomer extends Model
{
    protected $table = 'crm_customers';

    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return HasMany<CrmCase, $this> */
    public function cases(): HasMany
    {
        return $this->hasMany(CrmCase::class, 'customer_id');
    }

    /** Won vehicle sales (CRM-CUS-004). */
    public function purchaseCount(): int
    {
        return $this->cases()->where('type', Crm::TYPE_VEHICLE)->where('state', Crm::STATE_WON)->count();
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->email ?: 'Customer #'.$this->id);
    }

    /** Digits only, for WhatsApp click-to-chat (CRM-ACT-006). */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($this->whatsapp ?: $this->phone));

        return $digits !== '' && strlen($digits) >= 7 ? $digits : null;
    }
}

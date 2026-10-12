<?php

namespace App\Modules\Crm\Models;

use App\Models\Country;
use App\Models\Port;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\Crm\Domain\Crm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One managed enquiry or purchase (SRS §1.4, CRM-CAS-004). "Case" is a
 * reserved word in PHP, hence CrmCase.
 */
class CrmCase extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'crm_cases';

    protected $guarded = [];

    protected $casts = [
        'value_usd' => 'decimal:2',
        'hold_until' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'last_staff_response_at' => 'datetime',
        'last_staff_activity_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'assigned_at' => 'datetime',
        'stage_entered_at' => 'datetime',
        'stale_at' => 'datetime',
        'stale_admin_at' => 'datetime',
        'unassigned_alerted_at' => 'datetime',
        'closed_at' => 'datetime',
        'stage' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CrmCase $case): void {
            $case->reference ??= self::nextReference();
            $case->portal_token ??= Str::random(48);
            $case->last_activity_at ??= now();
        });
    }

    /** C-YYYY-NNNNN, sequential per year (CRM-CAS-004). */
    public static function nextReference(): string
    {
        $year = now('Asia/Tokyo')->year;
        $prefix = "C-{$year}-";

        return DB::transaction(function () use ($prefix) {
            $last = self::query()->where('reference', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('reference')->value('reference');
            $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        });
    }

    public function registerMediaCollections(): void
    {
        // Private disk: customers download through the account page only (CRM-POR-003, NFR-002).
        $this->addMediaCollection('documents')->useDisk('local');
    }

    /** @return BelongsTo<CrmCustomer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CrmCustomer::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /** @return BelongsTo<Port, $this> */
    public function destPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'dest_port_id');
    }

    /** @return BelongsTo<Country, $this> */
    public function destCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'dest_country_id');
    }

    /** @return BelongsTo<CrmQueue, $this> */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(CrmQueue::class, 'queue_id');
    }

    /** @return HasMany<CrmCaseLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(CrmCaseLink::class, 'case_id');
    }

    /** @return HasMany<CrmCaseStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(CrmCaseStep::class, 'case_id');
    }

    /** @return HasMany<CrmActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'case_id');
    }

    /** @return HasMany<CrmTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'case_id');
    }

    /** @return HasMany<CrmSlaTimer, $this> */
    public function slaTimers(): HasMany
    {
        return $this->hasMany(CrmSlaTimer::class, 'case_id');
    }

    /** @return HasMany<CrmStageHistory, $this> */
    public function stageHistory(): HasMany
    {
        return $this->hasMany(CrmStageHistory::class, 'case_id');
    }

    /** @param Builder<CrmCase> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('state', Crm::ACTIVE_STATES)->whereNull('merged_into_id');
    }

    public function isVehicleSale(): bool
    {
        return $this->type === Crm::TYPE_VEHICLE;
    }

    public function isActive(): bool
    {
        return Crm::isActive($this->state) && $this->merged_into_id === null;
    }

    /** First linked record of a class, e.g. the order. @template T of Model @param class-string<T> $class @return T|null */
    public function linked(string $class): ?Model
    {
        $link = $this->links()->where('linkable_type', (new $class)->getMorphClass())->latest('id')->first();

        return $link?->linkable;
    }

    /** "02 Payment" for vehicle sales, the support status otherwise. */
    public function progressLabel(): string
    {
        return $this->isVehicleSale()
            ? Crm::stageLabel($this->stage)
            : (Crm::SUPPORT_STATUSES[$this->status] ?? ucfirst((string) $this->status));
    }

    /** Customer URL without login (CRM-POR-006). */
    public function portalUrl(): string
    {
        return route('crm.portal.show', ['case' => $this->reference, 'token' => $this->portal_token]);
    }
}

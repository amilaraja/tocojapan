<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'address', 'country_id', 'locale', 'preferred_currency', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // CRM staff work in their own /crm panel and cannot open /admin (CR-TOC-02-01).
        return match ($panel->getId()) {
            'admin' => $this->hasAnyRole(['super_admin', 'admin', 'sales', 'mailer_admin', 'mailer_marketer']),
            'crm' => \App\Modules\Crm\CrmServiceProvider::enabled() && \App\Modules\Crm\Support\CrmAccess::canUse($this),
            default => false,
        };
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return HasMany<ExpoPushToken, $this> */
    public function expoPushTokens(): HasMany
    {
        return $this->hasMany(ExpoPushToken::class);
    }

    /** @return HasMany<Favorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** @return HasMany<\App\Models\Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(\App\Models\Order::class);
    }

    public function hasFavorited(Vehicle $vehicle): bool
    {
        return $this->favorites()->where('vehicle_id', $vehicle->id)->exists();
    }

    /** Public URL of the user's avatar, or null when none is set. */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path);
    }
}

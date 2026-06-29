<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'integer',
        'pre_inspection_required' => 'bool',
    ];

    /** @return HasMany<Port, $this> */
    public function ports(): HasMany
    {
        return $this->hasMany(Port::class);
    }

    /** @return HasMany<ImportRegulation, $this> */
    public function importRegulations(): HasMany
    {
        return $this->hasMany(ImportRegulation::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** This country's flag as a Unicode emoji, derived from its ISO-3166 code. */
    public function flagEmoji(): string
    {
        return self::iso2ToFlag($this->iso2);
    }

    /** Convert a 2-letter ISO country code into its flag emoji. */
    public static function iso2ToFlag(?string $iso2): string
    {
        $iso2 = strtoupper(trim((string) $iso2));

        if (strlen($iso2) !== 2 || ! ctype_alpha($iso2)) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($iso2[0]) - 65).mb_chr(0x1F1E6 + ord($iso2[1]) - 65);
    }
}

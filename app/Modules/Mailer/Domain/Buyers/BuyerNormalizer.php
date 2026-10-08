<?php

namespace App\Modules\Mailer\Domain\Buyers;

use Illuminate\Support\Facades\DB;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Turns the free-typed values buyers enter into consistent data
 * (TOC-BUY-002): ISO country codes, E.164 phone numbers, buyer types,
 * names without titles, plausible years.
 */
class BuyerNormalizer
{
    /** Spellings portals and buyers use that differ from the country list. */
    public const COUNTRY_ALIASES = [
        'uae' => 'AE', 'u.a.e' => 'AE', 'dubai' => 'AE', 'usa' => 'US', 'u.s.a' => 'US', 'united states of america' => 'US', 'america' => 'US',
        'uk' => 'GB', 'u.k' => 'GB', 'england' => 'GB', 'scotland' => 'GB', 'wales' => 'GB', 'northern ireland' => 'GB', 'great britain' => 'GB',
        'drc' => 'CD', 'dr congo' => 'CD', 'democratic republic of the congo' => 'CD', 'congo (drc)' => 'CD', 'congo, democratic republic' => 'CD',
        'congo' => 'CG', 'republic of the congo' => 'CG', 'ivory coast' => 'CI', "cote d'ivoire" => 'CI', 'côte d’ivoire' => 'CI',
        'tanzania, united republic of' => 'TZ', 'south korea' => 'KR', 'korea' => 'KR', 'russia' => 'RU', 'russian federation' => 'RU',
        'swaziland' => 'SZ', 'eswatini' => 'SZ', 'burma' => 'MM', 'myanmar' => 'MM', 'vietnam' => 'VN', 'viet nam' => 'VN',
        'trinidad' => 'TT', 'trinidad & tobago' => 'TT', 'st. lucia' => 'LC', 'st lucia' => 'LC', 'st. vincent' => 'VC',
        'st kitts' => 'KN', 'antigua' => 'AG', 'bahamas, the' => 'BS', 'the bahamas' => 'BS', 'gambia, the' => 'GM', 'the gambia' => 'GM',
        'brunei' => 'BN', 'macedonia' => 'MK', 'north macedonia' => 'MK', 'czech republic' => 'CZ', 'turkey' => 'TR', 'türkiye' => 'TR',
        'hong kong sar' => 'HK', 'cape verde' => 'CV', 'laos' => 'LA', 'syria' => 'SY', 'iran' => 'IR', 'moldova' => 'MD', 'bolivia' => 'BO',
        'venezuela' => 'VE', 'micronesia' => 'FM', 'east timor' => 'TL', 'palestine' => 'PS',
    ];

    public const TITLES = ['mr', 'mrs', 'ms', 'miss', 'mx', 'dr', 'prof', 'sir', 'madam', 'mdm', 'eng', 'engr', 'rev', 'hon', 'chief', 'alhaji'];

    /** @var array<string, string>|null lower-case name => ISO code */
    private ?array $countries = null;

    public function countryCode(?string $name): ?string
    {
        $key = $this->key($name);
        if ($key === '') {
            return null;
        }
        if (strlen($key) === 2 && ctype_alpha($key)) {
            return strtoupper($key);
        }

        return self::COUNTRY_ALIASES[$key] ?? $this->countries()[$key] ?? null;
    }

    /** Values portals put in the phone field when the buyer did not give one. */
    public const NO_PHONE = ['not shared', 'not provided', 'hidden', 'private', 'none', 'n/a', '-'];

    /**
     * E.164 (+254712345678) when the number is valid. Repairs the portal
     * quirks seen in real enquiries: a doubled country code ("+234+234…"),
     * a trunk 0 after the country code ("+44 07…", "+254+07…") and local
     * numbers without a country code ("0712…" with the buyer's country).
     */
    public function phoneE164(?string $raw, ?string $countryCode): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || in_array(mb_strtolower($raw), self::NO_PHONE, true) || preg_match_all('/\d/', $raw) < 6) {
            return null;
        }
        $util = PhoneNumberUtil::getInstance();
        $region = $countryCode ? strtoupper($countryCode) : null;
        $cc = $region ? (string) $util->getCountryCodeForRegion($region) : '';
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        $lastPart = trim((string) preg_replace('/^.*\+/', '+', $raw));   // text from the last "+"

        $candidates = [$raw, $lastPart, '+'.$digits];
        if (str_starts_with($digits, '00')) {
            $candidates[] = '+'.substr($digits, 2);
        }
        if ($cc !== '' && $cc !== '0') {
            $rest = $digits;
            // Strip up to two leading copies of the country code, then any trunk zeros.
            for ($i = 0; $i < 2 && str_starts_with($rest, $cc); $i++) {
                $rest = substr($rest, strlen($cc));
            }
            $candidates[] = '+'.$cc.ltrim($rest, '0');
            $candidates[] = '+'.$cc.ltrim($digits, '0');
        }

        foreach (array_unique($candidates) as $number) {
            try {
                $parsed = $util->parse($number, $region ?? 'ZZ');
            } catch (NumberParseException) {
                continue;
            }
            if ($util->isValidNumber($parsed)) {
                return $util->format($parsed, PhoneNumberFormat::E164);
            }
        }

        return null;
    }

    /** @return array{title: ?string, first_name: ?string, last_name: ?string} */
    public function name(?string $raw): array
    {
        $parts = preg_split('/\s+/u', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $title = null;
        while ($parts !== [] && in_array(rtrim(mb_strtolower($parts[0]), '.'), self::TITLES, true)) {
            // Shift first: "$title ??= array_shift(...)" would skip the shift once a
            // title is set and loop forever on "Mr. Dr. …".
            $word = rtrim((string) array_shift($parts), '.');
            $title ??= $word;
        }
        if ($parts === []) {
            return ['title' => $title, 'first_name' => null, 'last_name' => null];
        }
        // "JOHN SMITH" / "john smith" → "John Smith"; mixed case is kept as typed.
        $fix = fn (string $w) => ($w === mb_strtoupper($w) || $w === mb_strtolower($w)) && mb_strlen($w) > 1
            ? mb_convert_case($w, MB_CASE_TITLE) : $w;
        $parts = array_map($fix, $parts);

        return [
            'title' => $title ? ucfirst(mb_strtolower($title)) : null,
            'first_name' => mb_substr(array_shift($parts), 0, 80),
            'last_name' => $parts ? mb_substr(implode(' ', $parts), 0, 120) : null,
        ];
    }

    public function buyerType(?string $raw): ?string
    {
        $v = $this->key($raw);

        return match (true) {
            $v === '' => null,
            (bool) preg_match('/dealer|importer|company|trader|business|wholesale|showroom|garage/', $v) => 'dealer',
            (bool) preg_match('/individual|private|personal|end user|self/', $v) => 'individual',
            default => null,
        };
    }

    public function year(?string $raw): ?int
    {
        if (! preg_match('/\b(19[5-9]\d|20\d\d)\b/', (string) $raw, $m)) {
            return null;
        }
        $year = (int) $m[1];

        return $year <= (int) date('Y') + 1 ? $year : null;
    }

    public function drive(?string $raw): ?string
    {
        $v = strtoupper(trim((string) $raw));

        return match (true) {
            str_contains($v, 'RHD') || str_contains($v, 'RIGHT') => 'RHD',
            str_contains($v, 'LHD') || str_contains($v, 'LEFT') => 'LHD',
            $v === 'ANY' || $v === 'BOTH' => 'ANY',
            default => null,
        };
    }

    /** Short free text (make, model, port …): trimmed, single spaces, "-" / "N/A" dropped. */
    public function text(?string $raw, int $max = 120): ?string
    {
        $v = trim((string) preg_replace('/\s+/u', ' ', (string) $raw));
        if ($v === '' || in_array(mb_strtolower($v), ['-', '--', 'n/a', 'na', 'none', 'nil', '.', 'any'], true)) {
            return null;
        }

        return mb_substr($v, 0, $max);
    }

    private function key(?string $value): string
    {
        return trim(mb_strtolower((string) preg_replace('/\s+/u', ' ', (string) $value)), " \t.");
    }

    /** @return array<string, string> */
    private function countries(): array
    {
        if ($this->countries !== null) {
            return $this->countries;
        }
        $map = [];
        // Every region the phone library knows, named in English by ICU.
        foreach (PhoneNumberUtil::getInstance()->getSupportedRegions() as $code) {
            $name = \Locale::getDisplayRegion('-'.$code, 'en');
            if ($name && $name !== $code) {
                $map[$this->key($name)] = $code;
            }
        }
        // The site's own country list wins (it is what staff see elsewhere).
        foreach (DB::table('countries')->whereNotNull('iso2')->get(['name', 'iso2']) as $row) {
            $map[$this->key($row->name)] = strtoupper($row->iso2);
        }

        return $this->countries = $map;
    }
}

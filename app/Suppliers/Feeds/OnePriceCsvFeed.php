<?php

namespace App\Suppliers\Feeds;

/**
 * OnePrice (919919.jp / AVN) stock CSV, as consumed by the old WordPress
 * plugin "Mobiz Toco OnePrice Importer": no header row, 29 positional
 * columns, Shift-JIS or UTF-8, lookup fields sent as numeric codes.
 */
class OnePriceCsvFeed implements SupplierFeed
{
    /** Positional columns (0-indexed) — identical to the WP plugin's map. */
    public const COLUMNS = [
        'vehicle_id', 'wholesale_price', 'retail_price', 'initial_registration_date',
        'manufacturer', 'model_name', 'grade', 'vehicle_size_length', 'vehicle_size_width',
        'vehicle_size_height', 'model_code', 'chassis_number', 'mileage', 'body_type',
        'steering_position', 'transmission', 'number_of_doors', 'engine_displacement',
        'seating_capacity', 'fuel_type', 'exterior_color', 'drive_system', 'options',
        'images', 'listing_sheet', 'listing_store_prefecture', 'listing_store_city',
        'evaluation_score', 'update_date_time',
    ];

    /** body_type_master → body_types.slug (null = leave unset). */
    public const BODY_TYPES = [
        1 => 'bus', 2 => 'convertible', 3 => 'hatchback', 5 => null, 6 => 'sedan', 7 => 'suv',
        8 => 'truck', 9 => 'van', 10 => 'wagon', 11 => 'coupe', 12 => null,
    ];

    public const STEERING = [12 => 'right', 13 => 'left', 54 => null];

    public const TRANSMISSION = [5 => 'automatic', 6 => 'manual', 8 => null, 53 => 'cvt'];

    public const DOORS = [1 => 2, 2 => 3, 3 => 4, 4 => 5];

    public const FUEL = [16 => 'cng', 17 => 'diesel', 18 => 'electric', 20 => 'petrol', 21 => 'hybrid', 22 => 'lpg', 24 => null];

    public const DRIVE = [9 => '2wd', 10 => '4wd'];

    public const COLORS = [
        26 => 'black', 27 => 'blue', 28 => 'brown', 29 => 'wine', 33 => 'gold', 34 => 'gray',
        35 => 'green', 38 => 'orange', 39 => 'gunmetal', 40 => 'pink', 41 => 'red', 42 => 'silver',
        47 => 'white', 48 => 'yellow', 49 => 'other', 50 => 'purple', 55 => 'pearl',
    ];

    /** option_master → config/vehicle_features.php [group, key, label]; null group = extra note. */
    public const OPTIONS = [
        1 => ['safety', 'abs', 'ABS'],
        2 => ['safety', 'driver_airbag', 'Driver Airbag'],
        6 => ['comfort', 'power_window', 'Power Window'],
        10 => ['comfort', 'air_conditioner', 'Air Conditioner'],
        13 => ['comfort', 'navigation', 'Navigation'],
        14 => ['comfort', 'power_steering', 'Power Steering'],
        15 => ['comfort', 'keyless_entry', 'Keyless Entry'],
        27 => ['seats', 'leather_seat', 'Leather Seats'],
        30 => [null, 'alloy_wheels', 'Alloy Wheels'],
        33 => ['other', 'sun_roof', 'Sun Roof'],
        38 => [null, 'maintenance_record', 'Maintenance record available'],
        39 => [null, 'repainted', 'Repainted'],
        41 => [null, 'no_accident', 'No accident history'],
    ];

    /** Japanese prefecture → English, for the vehicle "location" field. */
    public const PREFECTURES = [
        '北海道' => 'Hokkaido', '青森県' => 'Aomori', '岩手県' => 'Iwate', '宮城県' => 'Miyagi', '秋田県' => 'Akita',
        '山形県' => 'Yamagata', '福島県' => 'Fukushima', '茨城県' => 'Ibaraki', '栃木県' => 'Tochigi', '群馬県' => 'Gunma',
        '埼玉県' => 'Saitama', '千葉県' => 'Chiba', '東京都' => 'Tokyo', '神奈川県' => 'Kanagawa', '新潟県' => 'Niigata',
        '富山県' => 'Toyama', '石川県' => 'Ishikawa', '福井県' => 'Fukui', '山梨県' => 'Yamanashi', '長野県' => 'Nagano',
        '岐阜県' => 'Gifu', '静岡県' => 'Shizuoka', '愛知県' => 'Aichi', '三重県' => 'Mie', '滋賀県' => 'Shiga',
        '京都府' => 'Kyoto', '大阪府' => 'Osaka', '兵庫県' => 'Hyogo', '奈良県' => 'Nara', '和歌山県' => 'Wakayama',
        '鳥取県' => 'Tottori', '島根県' => 'Shimane', '岡山県' => 'Okayama', '広島県' => 'Hiroshima', '山口県' => 'Yamaguchi',
        '徳島県' => 'Tokushima', '香川県' => 'Kagawa', '愛媛県' => 'Ehime', '高知県' => 'Kochi', '福岡県' => 'Fukuoka',
        '佐賀県' => 'Saga', '長崎県' => 'Nagasaki', '熊本県' => 'Kumamoto', '大分県' => 'Oita', '宮崎県' => 'Miyazaki',
        '鹿児島県' => 'Kagoshima', '沖縄県' => 'Okinawa',
    ];

    public static function describe(): string
    {
        return 'OnePrice stock CSV exactly as downloaded from OnePrice: no header row, 29 columns '
            .'(vehicle id, wholesale price, retail price, first registration YYYYMM, maker, model, grade, '
            .'… photos separated by ";"). Japanese (Shift-JIS) files are fine.';
    }

    public function read(string $path, int $offset, int $maxRows, int $lineNo): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open {$path}");
        }
        if ($offset === 0) {
            // Skip a UTF-8 BOM if present.
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
        } else {
            fseek($handle, $offset);
        }

        $rows = [];
        $read = 0;
        while ($read < $maxRows && ($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $lineNo++;
            if ($line === [null] || count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $read++;
            $rows[] = $this->parseLine($line, $lineNo);
        }

        $eof = feof($handle);
        $pos = (int) ftell($handle);
        fclose($handle);

        return ['rows' => $rows, 'offset' => $pos, 'eof' => $eof];
    }

    /**
     * @param  array<int, string|null>  $line
     * @return array{line: int, ref: ?string, payload: ?array<string, mixed>, error: ?string}
     */
    public function parseLine(array $line, int $lineNo): array
    {
        $line = array_map(fn ($v) => self::utf8(trim((string) $v)), $line);
        $raw = [];
        foreach (self::COLUMNS as $i => $name) {
            $raw[$name] = ($line[$i] ?? '') === '' ? null : $line[$i];
        }

        $ref = $raw['vehicle_id'] !== null ? preg_replace('/\D/', '', $raw['vehicle_id']) : '';
        // A header row (e.g. "vehicle_id,wholesale_price,…") parses to an empty id.
        if ($ref === '' || $ref === null) {
            return ['line' => $lineNo, 'ref' => null, 'payload' => null, 'error' => 'Missing vehicle id'];
        }

        $reg = preg_replace('/\D/', '', (string) $raw['initial_registration_date']);
        $year = strlen($reg) >= 4 ? (int) substr($reg, 0, 4) : 0;
        $month = strlen($reg) >= 6 ? (int) substr($reg, 4, 2) : null;
        if ($year < 1900 || $year > (int) date('Y') + 1) {
            return ['line' => $lineNo, 'ref' => $ref, 'payload' => null, 'error' => 'Invalid first registration date "'.$raw['initial_registration_date'].'"'];
        }

        $make = self::clean($raw['manufacturer']);
        $model = self::clean($raw['model_name']);
        if ($make === '') {
            return ['line' => $lineNo, 'ref' => $ref, 'payload' => null, 'error' => 'Missing maker'];
        }
        // Some trucks arrive without a model; file them like the feed's own "BMW OTHER".
        if ($model === '') {
            $model = 'OTHER';
        }
        // The feed sometimes prefixes the model with the maker ("PONTIAC FIREBIRD").
        if (str_starts_with($model.' ', $make.' ') && strlen($model) > strlen($make)) {
            $model = trim(substr($model, strlen($make)));
        }

        $features = [];
        $extras = [];
        foreach (array_filter(explode(';', (string) $raw['options'])) as $code) {
            $opt = self::OPTIONS[(int) $code] ?? null;
            if (! $opt) {
                continue;
            }
            [$group, $key, $label] = $opt;
            if ($group === null) {
                $extras[$key] = $label;
            } else {
                $features[$group][$key] = $label;
            }
        }

        $photos = array_values(array_filter(array_map(
            fn ($u) => self::httpsUrl(trim($u)),
            explode(';', (string) $raw['images']),
        )));

        $prefecture = $raw['listing_store_prefecture'];
        $location = $prefecture !== null
            ? (self::PREFECTURES[$prefecture] ?? null)
            : null;

        $mm = fn (?string $v) => ($n = self::int($v)) ? round($n / 10, 2) : null;

        $payload = [
            'ref' => $ref,
            'make' => $make,
            'model' => $model,
            'grade' => self::grade($raw['grade']),
            'year' => $year,
            'month' => ($month >= 1 && $month <= 12) ? $month : null,
            'mileage_km' => self::int($raw['mileage']),
            'engine_cc' => self::int($raw['engine_displacement']) ?: null,
            'fuel' => self::lookup(self::FUEL, $raw['fuel_type']),
            'transmission' => self::lookup(self::TRANSMISSION, $raw['transmission']),
            'drive' => self::lookup(self::DRIVE, $raw['drive_system']),
            'steering_side' => self::lookup(self::STEERING, $raw['steering_position']) ?? 'right',
            'body_type' => self::lookup(self::BODY_TYPES, $raw['body_type']),
            'doors' => self::lookup(self::DOORS, $raw['number_of_doors']),
            'seats' => self::int($raw['seating_capacity']) ?: null,
            'exterior_color' => self::lookup(self::COLORS, $raw['exterior_color']),
            'length_cm' => $mm($raw['vehicle_size_length']),
            'width_cm' => $mm($raw['vehicle_size_width']),
            'height_cm' => $mm($raw['vehicle_size_height']),
            'chassis_number' => self::nullIfDash($raw['chassis_number']),
            'model_code' => self::nullIfDash($raw['model_code']),
            'location' => $location ? $location.', Japan' : 'Japan',
            'features' => $features ?: null,
            'photos' => $photos,
            'retail_price' => self::int($raw['retail_price']),
            'wholesale_price' => self::int($raw['wholesale_price']),
            'meta' => array_filter([
                'auction_sheet' => self::httpsUrl((string) $raw['listing_sheet']),
                'evaluation_score' => $raw['evaluation_score'],
                'extras' => $extras ?: null,
                'yard_prefecture' => $prefecture,
                'yard_city' => $raw['listing_store_city'],
                'supplier_updated_at' => self::int($raw['update_date_time']),
            ], fn ($v) => $v !== null && $v !== ''),
        ];

        return ['line' => $lineNo, 'ref' => $ref, 'payload' => $payload, 'error' => null];
    }

    private static function utf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'SJIS-win');
    }

    private static function clean(?string $value): string
    {
        // Full-width ASCII/space → half-width, half-width katakana → full-width
        // (ﾊｲｳｪｲｽﾀｰ → ハイウェイスター), collapse whitespace.
        $value = mb_convert_kana((string) $value, 'asKV', 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private static function grade(?string $value): ?string
    {
        $value = self::clean($value);

        return $value === '' ? null : mb_substr($value, 0, 60);
    }

    private static function int(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', $value);

        return $digits === '' ? null : (int) $digits;
    }

    /** @param  array<int, mixed>  $map */
    private static function lookup(array $map, ?string $code): mixed
    {
        $n = self::int($code);

        return $n === null ? null : ($map[$n] ?? null);
    }

    private static function nullIfDash(?string $value): ?string
    {
        $value = self::clean($value);

        return ($value === '' || $value === '-') ? null : mb_substr($value, 0, 60);
    }

    /** The image host serves HTTPS; upgrade so pages don't trigger mixed-content blocking. */
    private static function httpsUrl(string $url): ?string
    {
        if ($url === '' || ! preg_match('~^https?://~i', $url)) {
            return null;
        }

        return preg_replace('~^http://~i', 'https://', $url);
    }
}

<?php

namespace App\Modules\JctFeed\Domain;

use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use XMLWriter;

/**
 * Toco's own stock in JCT's import format. Read-only: never writes vehicles.
 * Field order and value spellings follow JCT's spec; empty values are left
 * out, as the spec asks ("don't mention that tag").
 */
class JctStockFeed
{
    /** Own stock that is on sale or under offer. @return Builder<Vehicle> */
    public static function stock(): Builder
    {
        return Vehicle::query()
            ->where(function (Builder $query) {
                $query->whereNull('supplier_id');
                if ($own = Supplier::ownStockId()) {
                    $query->orWhere('supplier_id', $own);
                }
            })
            ->whereIn('status', ['published', 'reserved']);
    }

    /** The feed's cars: newest first, capped at JCT's membership limit. @return Builder<Vehicle> */
    public static function query(): Builder
    {
        return self::stock()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit((int) config('jct_feed.max_vehicles'))
            ->with(['make', 'vehicleModel', 'bodyType', 'media']);
    }

    /** @return Collection<int, Vehicle> */
    public static function vehicles(): Collection
    {
        return self::query()->get();
    }

    public static function imageBaseUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/feeds/jct/images/';
    }

    /** One vehicle as JCT fields, in spec order, empty values dropped. @return array<string, mixed> */
    public static function row(Vehicle $vehicle): array
    {
        $tz = config('jct_feed.timezone');

        $row = [
            'veh_title' => self::clean($vehicle->title),
            'stock_number' => $vehicle->stock_no,
        ] + self::prices($vehicle) + [
            'status' => $vehicle->status === 'reserved' ? 'Reserved' : 'Available',
            'new_used' => 'Used',
            'display' => 1,
            'mfg_year' => $vehicle->manufacture_year ?? $vehicle->year_first_reg,
            'mfg_month' => $vehicle->manufacture_year ? $vehicle->manufacture_month : $vehicle->registration_month,
            'chassis_no' => config('jct_feed.full_chassis') ? $vehicle->chassis_number : $vehicle->chassisNumberRedacted(),
            'mileage' => $vehicle->mileage_km,
            'engine_cc' => $vehicle->engine_cc,
            'drive' => match ($vehicle->steering_side) {
                'right' => 'Right Hand',
                'left' => 'Left Hand',
                default => null,
            },
            'doors' => $vehicle->doors ? (int) $vehicle->doors : null,
            'seat_capacity' => $vehicle->seats,
            'other_info' => self::otherInfo($vehicle),
            'update_date' => $vehicle->updated_at?->copy()->setTimezone($tz)->format('Y-m-d H:i:s'),
            'location' => config('jct_feed.location'),
            'port' => config('jct_feed.port'),
            'type' => self::type($vehicle),
            'body_type' => $vehicle->bodyType?->name,
            'make' => $vehicle->make?->name,
            'maker' => $vehicle->vehicleModel?->name,
            'model' => $vehicle->grade,
            'model_code' => $vehicle->model_code,
            'fuel' => $vehicle->fuel ? ucfirst((string) $vehicle->fuel) : null,
            'color' => $vehicle->exterior_color,
            'transmission' => match ($vehicle->transmission) {
                'automatic' => 'AT',
                'manual' => 'MT',
                default => $vehicle->transmission ? strtoupper((string) $vehicle->transmission) : null,
            },
            'wheel_drive' => match (strtolower((string) $vehicle->drive)) {
                '2wd' => '2WD',
                '4wd', 'awd' => '4WD',
                default => null,
            },
            'accessories' => implode(', ', self::accessories($vehicle)) ?: null,
            'picture' => self::pictures($vehicle),
        ];

        return array_filter($row, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /** @return array<string, mixed> */
    public static function toArray(?Collection $vehicles = null): array
    {
        $vehicles ??= self::vehicles();
        $data = [];
        foreach ($vehicles->values() as $i => $vehicle) {
            $data[(string) ($i + 1)] = self::jsonRow(self::row($vehicle));
        }

        return [
            'status' => true,
            'generation_time' => self::now(),
            'total_vehicle' => count($data),
            'image_base_url' => self::imageBaseUrl(),
            // Serial numbers from 1: always encodes as an object, as in JCT's sample.
            'data' => (object) $data,
        ];
    }

    public static function toJson(?Collection $vehicles = null): string
    {
        return json_encode(self::toArray($vehicles), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    public static function toXml(?Collection $vehicles = null): string
    {
        $vehicles ??= self::vehicles();
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'utf-8', 'yes');
        $xml->startElement('vehicle_list');
        $xml->writeElement('generation_time', self::now());
        $xml->writeElement('total_vehicle', (string) $vehicles->count());

        foreach ($vehicles->values() as $i => $vehicle) {
            $xml->startElement('vehicle');
            $xml->writeElement('s_no', (string) ($i + 1));
            $xml->writeElement('image_base_url', self::imageBaseUrl());
            foreach (self::row($vehicle) as $tag => $value) {
                if ($tag === 'picture') {
                    $xml->startElement('picture');
                    foreach ($value as $file) {
                        $xml->writeElement('url', $file);
                    }
                    $xml->endElement();

                    continue;
                }
                $xml->writeElement($tag, (string) $value);
            }
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * USD stock goes in price_usd / offer_price_usd, JPY in price / offer_price.
     * JCT only accepts an offer at least US$100 / ¥10,000 below the price;
     * a smaller discount is sent as the plain (discounted) price instead.
     *
     * @return array<string, string>
     */
    private static function prices(Vehicle $vehicle): array
    {
        $effective = $vehicle->effectivePriceFob();
        if ($effective === null || $effective <= 0) {
            return [];
        }

        [$priceKey, $offerKey, $minGap] = match (strtoupper((string) ($vehicle->currency ?: 'USD'))) {
            'USD' => ['price_usd', 'offer_price_usd', 100],
            'JPY' => ['price', 'offer_price', 10000],
            default => [null, null, 0],
        };
        if ($priceKey === null) {
            return [];
        }

        $list = (float) $vehicle->price_fob;
        if ($vehicle->isDiscounted() && $list - $effective >= $minGap) {
            return [$priceKey => self::amount($list), $offerKey => self::amount($effective)];
        }

        return [$priceKey => self::amount($effective)];
    }

    private static function amount(float $value): string
    {
        return (string) (int) round($value);
    }

    /** JCT vehicle type: Car, Truck, Bus, Machinery or Bike. */
    private static function type(Vehicle $vehicle): string
    {
        $body = strtolower((string) $vehicle->bodyType?->name);

        return match (true) {
            str_contains($body, 'truck') => 'Truck',
            str_contains($body, 'bus') => 'Bus',
            str_contains($body, 'equipment') || str_contains($body, 'machinery') => 'Machinery',
            str_contains($body, 'motorcycle') || str_contains($body, 'bike') => 'Bike',
            default => 'Car',
        };
    }

    /** Feature labels, flattened and de-duplicated. @return list<string> */
    private static function accessories(Vehicle $vehicle): array
    {
        $labels = [];
        $features = (array) ($vehicle->features ?? []);
        array_walk_recursive($features, function ($label) use (&$labels) {
            if (is_string($label) && trim($label) !== '') {
                $labels[] = trim(str_replace(',', ' ', $label));
            }
        });

        return array_values(array_unique($labels));
    }

    /** Description as plain text, max 500 characters (JCT limit). */
    private static function otherInfo(Vehicle $vehicle): ?string
    {
        $text = self::clean(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', (string) $vehicle->description)));

        return $text === '' ? null : mb_strimwidth($text, 0, 500, '', 'UTF-8');
    }

    /** @return list<string> */
    private static function pictures(Vehicle $vehicle): array
    {
        return $vehicle->getMedia('photos')
            ->take((int) config('jct_feed.max_photos'))
            ->map(fn (Media $media) => JctImages::fileName($media))
            ->values()
            ->all();
    }

    /** JSON values as strings, like JCT's sample; display and doors stay numbers. @param array<string, mixed> $row */
    private static function jsonRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (! is_array($value) && ! in_array($key, ['display', 'doors'], true)) {
                $row[$key] = (string) $value;
            }
        }

        return $row;
    }

    private static function clean(?string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function now(): string
    {
        return Carbon::now(config('jct_feed.timezone'))->format('Y-m-d H:i:s');
    }
}

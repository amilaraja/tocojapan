<?php

namespace App\Suppliers\Feeds;

/**
 * Reads one supplier's stock file and turns each line into the normalised
 * payload consumed by App\Suppliers\SupplierImporter.
 *
 * Payload keys (all optional except ref/make/model/year):
 *   ref, make, model, grade, year, month, mileage_km, engine_cc, fuel,
 *   transmission, drive, steering_side, body_type (body_types.slug), doors,
 *   seats, exterior_color, length_cm, width_cm, height_cm, chassis_number,
 *   model_code, location, features ({group: {key: label}}), photos (urls),
 *   retail_price, wholesale_price, meta (free-form, shown on the detail page).
 */
interface SupplierFeed
{
    /**
     * Read up to $maxRows data lines starting at byte $offset.
     *
     * @return array{rows: list<array{line: int, ref: ?string, payload: ?array<string, mixed>, error: ?string}>, offset: int, eof: bool}
     */
    public function read(string $path, int $offset, int $maxRows, int $lineNo): array;

    /** Human description of the expected file, shown on the upload form. */
    public static function describe(): string;
}

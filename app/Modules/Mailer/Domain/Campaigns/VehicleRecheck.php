<?php

namespace App\Modules\Mailer\Domain\Campaigns;

use App\Modules\Mailer\Domain\Vehicles\VehicleDTO;
use App\Modules\Mailer\Domain\Vehicles\VehicleSource;

/**
 * TOC-CB-004/005: compares each vehicle snapshot with the live vehicle.
 * Changed prices refresh the snapshot (the email shows the new price);
 * sold or missing vehicles block the push.
 */
class VehicleRecheck
{
    public function __construct(protected VehicleSource $source) {}

    /**
     * @param  list<array<string, mixed>>  $snapshots  VehicleDTO arrays, in order
     * @return array{snapshots: list<array<string, mixed>>, issues: list<array{vehicle_id: int, ref: string, type: string, message: string, blocking: bool}>}
     */
    public function check(array $snapshots): array
    {
        $current = $this->source->findMany(array_map(fn ($s) => (int) $s['id'], $snapshots));
        $issues = [];
        $fresh = [];

        foreach ($snapshots as $snapshot) {
            $old = VehicleDTO::fromArray($snapshot);
            $now = $current[$old->id] ?? null;
            $ref = $old->stockRef ?? '#'.$old->id;

            if ($now === null || $now->status === VehicleDTO::STATUS_UNAVAILABLE) {
                $issues[] = ['vehicle_id' => $old->id, 'ref' => $ref, 'type' => 'missing', 'blocking' => true,
                    'message' => "{$ref} is no longer on the website. Remove it before pushing."];
                $fresh[] = $snapshot;

                continue;
            }

            if ($now->status === VehicleDTO::STATUS_SOLD) {
                $issues[] = ['vehicle_id' => $old->id, 'ref' => $ref, 'type' => 'sold', 'blocking' => true,
                    'message' => "{$ref} was sold. Remove it before pushing."];
                $fresh[] = $snapshot;

                continue;
            }

            if ($now->priceFob !== $old->priceFob) {
                $issues[] = ['vehicle_id' => $old->id, 'ref' => $ref, 'type' => 'price_changed', 'blocking' => false,
                    'message' => "{$ref}: Price changed from ".self::money($old->priceFob).' to '.self::money($now->priceFob).'. The email will show the new price.'];
            }

            $fresh[] = $now->toArray();
        }

        return ['snapshots' => $fresh, 'issues' => $issues];
    }

    /** @param  list<array{blocking: bool}>  $issues */
    public static function blocks(array $issues): bool
    {
        return collect($issues)->contains('blocking', true);
    }

    public static function money(?float $v): string
    {
        return $v === null ? 'price on request' : '$'.number_format($v);
    }
}

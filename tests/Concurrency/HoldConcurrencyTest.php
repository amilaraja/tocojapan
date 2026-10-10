<?php

use App\Models\Vehicle;
use App\Models\VehicleHold;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/*
 * TOC-PAPI-NFR-10: of any number of simultaneous holds on one car, exactly
 * one succeeds. Runs real parallel PHP processes against MariaDB, never
 * SQLite and never the live database. Not part of the default suites; run:
 *
 *   set -a; . /home/tocojapan.com/secure/holds-test-db.env; set +a
 *   APP_CONFIG_CACHE=/tmp/x.php APP_ROUTES_CACHE=/tmp/y.php APP_EVENTS_CACHE=/tmp/z.php \
 *   VIEW_COMPILED_PATH=/tmp/toco-test-views APP_ENV=testing DB_CONNECTION=mysql \
 *   DB_DATABASE=$HOLDS_TEST_DB_DATABASE DB_USERNAME=$HOLDS_TEST_DB_USERNAME DB_PASSWORD=$HOLDS_TEST_DB_PASSWORD \
 *   php artisan test tests/Concurrency
 */

uses(Tests\TestCase::class);

it('lets exactly one of many simultaneous claims win (NFR-10)', function () {
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'toco_holds_test') {
        $this->markTestSkipped('Needs the isolated toco_holds_test MySQL database.');
    }

    Artisan::call('migrate:fresh', ['--force' => true]);

    foreach (range(1, 3) as $round) {
        $vehicle = Vehicle::factory()->create(['status' => 'published', 'price_fob' => 1000]);
        $startAt = microtime(true) + 3;

        $workers = collect(range(1, 16))->map(function (int $n) use ($vehicle, $startAt) {
            $process = new Process([PHP_BINARY, __DIR__.'/claim-worker.php', (string) $vehicle->id, (string) $n, (string) $startAt], base_path());
            $process->setTimeout(60);
            $process->start();

            return $process;
        });

        $results = $workers->map(function (Process $process) {
            $process->wait();

            return trim($process->getOutput()) ?: trim($process->getErrorOutput());
        });

        expect($results->filter(fn ($r) => str_starts_with($r, 'error'))->values()->all())->toBe([])
            ->and($results->filter(fn ($r) => $r === 'won')->count())->toBe(1)
            ->and($results->filter(fn ($r) => $r === 'lost')->count())->toBe(15)
            ->and(VehicleHold::active()->where('vehicle_id', $vehicle->id)->count())->toBe(1)
            ->and($vehicle->fresh()->status)->toBe('reserved');
    }
});

<?php

// One contender in the hold race (HoldConcurrencyTest). Boots the app on the
// same isolated MySQL test database, waits for the shared start time, then
// claims. Prints won | lost | error: ...

use App\Models\Vehicle;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use App\Services\Holds\VehicleUnavailable;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $vehicleId, $contender, $startAt] = $argv;

if (config('database.connections.mysql.database') !== 'toco_holds_test') {
    exit('error: refusing to run outside toco_holds_test');
}

while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    app(HoldService::class)->claim(Vehicle::findOrFail((int) $vehicleId), new HoldClaim('admin', 'Contender '.$contender));
    echo 'won';
} catch (VehicleUnavailable) {
    echo 'lost';
} catch (Throwable $e) {
    echo 'error: '.get_class($e).': '.$e->getMessage();
}

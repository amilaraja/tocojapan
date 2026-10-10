<?php

namespace App\Console\Commands;

use App\Services\Holds\HoldService;
use Illuminate\Console\Command;

/** Every minute: lapse holds whose time ran out, so the car returns to sale (FR-HLD-04). */
class ExpireHolds extends Command
{
    protected $signature = 'holds:expire';

    protected $description = 'Release vehicle holds that have expired';

    public function handle(HoldService $holds): int
    {
        $count = $holds->expireDue();
        if ($count > 0) {
            $this->info("Expired {$count} hold(s).");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Services\Holds;

use App\Models\VehicleHold;
use RuntimeException;

/** The car is held by someone else, sold, or not for sale (FR-HLD-01: 409). */
class VehicleUnavailable extends RuntimeException
{
    public const HELD = 'held';

    public const NOT_FOR_SALE = 'not_for_sale';

    public const HOLD_LIMIT = 'hold_limit';

    public function __construct(
        public readonly string $reason,
        public readonly ?VehicleHold $currentHold = null,
        string $message = 'This vehicle is not available.',
    ) {
        parent::__construct($message);
    }
}

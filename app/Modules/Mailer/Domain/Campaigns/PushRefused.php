<?php

namespace App\Modules\Mailer\Domain\Campaigns;

use RuntimeException;

class PushRefused extends RuntimeException
{
    /** @param  list<array<string, mixed>>  $issues */
    public function __construct(string $message, public readonly array $issues = [], public readonly bool $duplicate = false)
    {
        parent::__construct($message);
    }
}

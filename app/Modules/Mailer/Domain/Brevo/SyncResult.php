<?php

namespace App\Modules\Mailer\Domain\Brevo;

final class SyncResult
{
    public function __construct(
        public readonly string $outcome, // added | updated | skipped | failed
        public readonly ?string $reason = null,
        public readonly ?int $status = null,
        public readonly int $retries = 0,
    ) {}
}

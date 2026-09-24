<?php

namespace App\Modules\Mailer\Domain\Brevo;

use RuntimeException;

/** Brevo could not be reached, or kept answering 429/5xx after all retries (TOC-BRV-005). */
class BrevoUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly int $retries = 0)
    {
        parent::__construct($message);
    }
}

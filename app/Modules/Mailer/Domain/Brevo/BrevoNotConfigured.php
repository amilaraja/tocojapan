<?php

namespace App\Modules\Mailer\Domain\Brevo;

use RuntimeException;

class BrevoNotConfigured extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Brevo is not connected yet. Add the Brevo key in Mailer settings.');
    }
}

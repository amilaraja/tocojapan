<?php

namespace App\Modules\Mailer\Domain\Importer;

use RuntimeException;

/** Gmail not configured or not reachable; the message is readable by staff. */
class MailboxUnavailable extends RuntimeException {}

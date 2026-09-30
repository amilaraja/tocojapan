<?php

namespace App\Modules\Mailer\Domain\Importer;

use RuntimeException;

/** The message was deleted after Gmail listed it (a sent draft, deleted mail); the run skips it. */
class MessageGone extends RuntimeException {}

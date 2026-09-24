<?php

namespace App\Modules\Mailer\Domain\Brevo;

use LogicException;

/** Thrown before any HTTP request that would send, schedule or unsubscribe-edit (TOC-CMP-002). */
class BrevoSendForbidden extends LogicException {}

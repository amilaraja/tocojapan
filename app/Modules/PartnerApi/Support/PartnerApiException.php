<?php

namespace App\Modules\PartnerApi\Support;

use RuntimeException;

/**
 * An error a partner should see, rendered as RFC 9457 problem+json.
 * $code is stable and documented (guide.md, Errors); $detail is for humans.
 */
class PartnerApiException extends RuntimeException
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $extra  extra problem members
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        public readonly string $title,
        public readonly ?string $detail = null,
        public readonly array $headers = [],
        public readonly array $extra = [],
    ) {
        parent::__construct($title);
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class LeadDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable,
        public readonly ?int $httpStatus = null,
        public readonly ?string $errorCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

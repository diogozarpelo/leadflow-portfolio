<?php

namespace App\Data;

final readonly class LeadDeliveryResult
{
    public function __construct(
        public ?string $externalId = null,
        public ?int $httpStatus = null,
    ) {}
}

<?php

namespace App\Contracts;

use App\Data\LeadDeliveryPayload;
use App\Data\LeadDeliveryResult;
use App\Exceptions\LeadDeliveryException;

interface LeadDeliveryDriver
{
    public function name(): string;

    /**
     * @throws LeadDeliveryException
     */
    public function deliver(
        LeadDeliveryPayload $payload
    ): LeadDeliveryResult;
}

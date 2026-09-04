<?php

namespace App\Services\LeadDelivery;

use App\Contracts\LeadDeliveryDriver;
use App\Data\LeadDeliveryPayload;
use App\Data\LeadDeliveryResult;
use App\Exceptions\LeadDeliveryException;

final class NullLeadDeliveryDriver implements LeadDeliveryDriver
{
    public function name(): string
    {
        return 'null';
    }

    public function deliver(
        LeadDeliveryPayload $payload
    ): LeadDeliveryResult {
        throw new LeadDeliveryException(
            'Lead delivery is not configured.',
            retryable: false,
            errorCode: 'delivery_not_configured',
        );
    }
}

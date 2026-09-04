<?php

namespace App\Services\LeadDelivery;

use App\Contracts\LeadDeliveryDriver;
use App\Data\LeadDeliveryPayload;
use App\Data\LeadDeliveryResult;
use App\Exceptions\LeadDeliveryException;
use Illuminate\Contracts\Filesystem\Filesystem;

final class LocalCsvLeadDeliveryDriver implements LeadDeliveryDriver
{
    public function __construct(
        private readonly LeadDeliveryCsvEncoder $encoder,
        private readonly Filesystem $disk,
        private readonly string $directory = 'lead-delivery/outbox',
    ) {}

    public function name(): string
    {
        return 'local_csv';
    }

    public function deliver(
        LeadDeliveryPayload $payload
    ): LeadDeliveryResult {
        $path = $this->pathFor($payload);

        $stored = $this->disk->put(
            $path,
            $this->encoder->encode($payload)
        );

        if (! $stored) {
            throw new LeadDeliveryException(
                'Unable to store the lead CSV locally.',
                retryable: true,
                errorCode: 'local_csv_storage_failed',
            );
        }

        return new LeadDeliveryResult(
            externalId: "local-csv-{$payload->internalId}",
        );
    }

    private function pathFor(
        LeadDeliveryPayload $payload
    ): string {
        $directory = trim($this->directory, '/');
        $filename = "lead-{$payload->internalId}.csv";

        if ($directory === '') {
            return $filename;
        }

        return "{$directory}/{$filename}";
    }
}

<?php

namespace App\Services\LeadDelivery;

use App\Data\LeadDeliveryPayload;
use InvalidArgumentException;
use RuntimeException;

final class LeadDeliveryCsvEncoder
{
    public function encode(
        LeadDeliveryPayload $payload,
        string $delimiter = ','
    ): string {
        if (strlen($delimiter) !== 1) {
            throw new InvalidArgumentException(
                'The CSV delimiter must contain exactly one character.'
            );
        }

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException(
                'Unable to create the temporary CSV stream.'
            );
        }

        try {
            $row = $payload->toArray();

            $this->writeRow(
                $stream,
                array_keys($row),
                $delimiter
            );

            $this->writeRow(
                $stream,
                array_values($row),
                $delimiter
            );

            rewind($stream);

            $csv = stream_get_contents($stream);

            if ($csv === false) {
                throw new RuntimeException(
                    'Unable to read the generated CSV.'
                );
            }

            return $csv;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     * @param  array<int, int|string|null>  $row
     */
    private function writeRow(
        $stream,
        array $row,
        string $delimiter
    ): void {
        $writtenBytes = fputcsv(
            $stream,
            $row,
            $delimiter,
            '"',
            '',
            "\n"
        );

        if ($writtenBytes === false) {
            throw new RuntimeException(
                'Unable to write the CSV row.'
            );
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Contracts\LeadDeliveryDriver;
use Illuminate\Console\Command;
use Throwable;

abstract class LeadDeliveryCommand extends Command
{
    protected function validatedLimit(): ?int
    {
        $limit = filter_var(
            $this->option('limit'),
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 1000,
                ],
            ]
        );

        if ($limit === false) {
            $this->error(
                'The limit must be an integer between 1 and 1000.'
            );

            return null;
        }

        return $limit;
    }

    protected function deliveryIsAvailable(): bool
    {
        if (! (bool) config('lead-delivery.enabled', false)) {
            $this->error('Lead delivery is disabled.');

            return false;
        }

        if ((string) config('lead-delivery.driver', 'null') === 'null') {
            $this->error('Lead delivery driver is not configured.');

            return false;
        }

        try {
            app(LeadDeliveryDriver::class);
        } catch (Throwable) {
            $this->error(
                'The configured lead delivery driver could not be resolved.'
            );

            return false;
        }

        return true;
    }
}

<?php

namespace App\Providers;

use App\Contracts\LeadDeliveryDriver;
use App\Services\LeadDelivery\LeadDeliveryCsvEncoder;
use App\Services\LeadDelivery\LocalCsvLeadDeliveryDriver;
use App\Services\LeadDelivery\NullLeadDeliveryDriver;
use App\Services\LeadDeliveryLogger;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class LeadDeliveryServiceProvider extends ServiceProvider
{
    /**
     * Register lead delivery services.
     */
    public function register(): void
    {
        $this->app->singleton(
            LeadDeliveryDriver::class,
            fn (): LeadDeliveryDriver => $this->resolveDriver()
        );

        $this->app->singleton(
            LeadDeliveryLogger::class,
            fn (): LeadDeliveryLogger => new LeadDeliveryLogger(
                $this->app
                    ->make(LogManager::class)
                    ->channel('lead_delivery')
            )
        );
    }

    private function resolveDriver(): LeadDeliveryDriver
    {
        $driver = (string) config(
            'lead-delivery.driver',
            'null'
        );

        return match ($driver) {
            'null' => new NullLeadDeliveryDriver,
            'local_csv' => $this->makeLocalCsvDriver(),
            default => throw new InvalidArgumentException(
                "Unsupported lead delivery driver [{$driver}]."
            ),
        };
    }

    private function makeLocalCsvDriver(): LocalCsvLeadDeliveryDriver
    {
        $disk = (string) config(
            'lead-delivery.local_csv.disk',
            'local'
        );

        $directory = (string) config(
            'lead-delivery.local_csv.directory',
            'lead-delivery/outbox'
        );

        return new LocalCsvLeadDeliveryDriver(
            $this->app->make(LeadDeliveryCsvEncoder::class),
            $this->app->make(FilesystemManager::class)->disk($disk),
            $directory
        );
    }
}

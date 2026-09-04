<?php

namespace App\Providers;

use App\Contracts\LeadDeliveryDriver;
use App\Services\LeadDelivery\LeadDeliveryCsvEncoder;
use App\Services\LeadDelivery\LocalCsvLeadDeliveryDriver;
use App\Services\LeadDelivery\NullLeadDeliveryDriver;
use App\Services\LeadDeliveryLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            LeadDeliveryDriver::class,
            function (): LeadDeliveryDriver {
                $driver = (string) config(
                    'lead-delivery.driver',
                    'null'
                );

                return match ($driver) {
                    'null' => new NullLeadDeliveryDriver,
                    'local_csv' => new LocalCsvLeadDeliveryDriver(
                        $this->app->make(
                            LeadDeliveryCsvEncoder::class
                        ),
                        $this->app
                            ->make(FilesystemManager::class)
                            ->disk(
                                (string) config(
                                    'lead-delivery.local_csv.disk',
                                    'local'
                                )
                            ),
                        (string) config(
                            'lead-delivery.local_csv.directory',
                            'lead-delivery/outbox'
                        )
                    ),
                    default => throw new InvalidArgumentException(
                        "Unsupported lead delivery driver [{$driver}]."
                    ),
                };
            }
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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for(
            'lead-submissions',
            function (Request $request): Limit {
                return Limit::perMinute(5)->by($request->ip());
            }
        );
    }
}

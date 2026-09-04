<?php

namespace Tests\Feature;

use App\Contracts\LeadDeliveryDriver;
use App\Services\LeadDelivery\LocalCsvLeadDeliveryDriver;
use App\Services\LeadDelivery\NullLeadDeliveryDriver;
use App\Services\LeadDeliveryLogger;
use InvalidArgumentException;
use Tests\TestCase;

class LeadDeliveryConfigurationTest extends TestCase
{
    public function test_it_uses_safe_default_delivery_configuration(): void
    {
        $this->assertFalse(config('lead-delivery.enabled'));
        $this->assertSame('null', config('lead-delivery.driver'));
        $this->assertSame(10, config('lead-delivery.timeout'));
        $this->assertSame(3, config('lead-delivery.max_attempts'));
        $this->assertSame(
            'local',
            config('lead-delivery.local_csv.disk')
        );
        $this->assertSame(
            'lead-delivery/outbox',
            config('lead-delivery.local_csv.directory')
        );
    }

    public function test_it_uses_safe_delivery_logging_configuration(): void
    {
        $this->assertSame(
            'daily',
            config('logging.channels.lead_delivery.driver')
        );
        $this->assertSame(
            storage_path('logs/lead-delivery.log'),
            config('logging.channels.lead_delivery.path')
        );
        $this->assertSame(
            'info',
            config('logging.channels.lead_delivery.level')
        );
        $this->assertSame(
            14,
            config('logging.channels.lead_delivery.days')
        );
        $this->assertTrue(
            config('logging.channels.lead_delivery.replace_placeholders')
        );
    }

    public function test_container_resolves_the_null_driver_as_a_singleton(): void
    {
        $driver = app(LeadDeliveryDriver::class);

        $this->assertInstanceOf(
            NullLeadDeliveryDriver::class,
            $driver
        );
        $this->assertSame('null', $driver->name());
        $this->assertSame(
            $driver,
            app(LeadDeliveryDriver::class)
        );
    }

    public function test_container_resolves_the_delivery_logger_as_a_singleton(): void
    {
        $logger = app(LeadDeliveryLogger::class);

        $this->assertInstanceOf(
            LeadDeliveryLogger::class,
            $logger
        );
        $this->assertSame(
            $logger,
            app(LeadDeliveryLogger::class)
        );
    }

    public function test_container_resolves_the_local_csv_driver_as_a_singleton(): void
    {
        config([
            'lead-delivery.driver' => 'local_csv',
        ]);

        $this->app->forgetInstance(
            LeadDeliveryDriver::class
        );

        $driver = app(LeadDeliveryDriver::class);

        $this->assertInstanceOf(
            LocalCsvLeadDeliveryDriver::class,
            $driver
        );
        $this->assertSame('local_csv', $driver->name());
        $this->assertSame(
            $driver,
            app(LeadDeliveryDriver::class)
        );
    }

    public function test_container_rejects_an_unsupported_driver(): void
    {
        config([
            'lead-delivery.driver' => 'unsupported',
        ]);

        $this->app->forgetInstance(
            LeadDeliveryDriver::class
        );

        $this->expectException(
            InvalidArgumentException::class
        );
        $this->expectExceptionMessage(
            'Unsupported lead delivery driver [unsupported].'
        );

        app(LeadDeliveryDriver::class);
    }
}

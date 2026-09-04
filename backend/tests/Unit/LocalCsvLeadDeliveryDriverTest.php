<?php

namespace Tests\Unit;

use App\Data\LeadDeliveryPayload;
use App\Exceptions\LeadDeliveryException;
use App\Services\LeadDelivery\LeadDeliveryCsvEncoder;
use App\Services\LeadDelivery\LocalCsvLeadDeliveryDriver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class LocalCsvLeadDeliveryDriverTest extends TestCase
{
    public function test_it_stores_a_lead_as_a_local_csv(): void
    {
        Storage::fake('local');

        $driver = new LocalCsvLeadDeliveryDriver(
            new LeadDeliveryCsvEncoder,
            Storage::disk('local')
        );

        $result = $driver->deliver($this->payload());

        $path = 'lead-delivery/outbox/lead-42.csv';

        $this->assertTrue(
            Storage::disk('local')->exists($path)
        );

        $csv = Storage::disk('local')->get($path);

        $this->assertStringContainsString(
            'internal_id,type,name,email',
            $csv
        );

        $this->assertStringContainsString(
            '42,contact,"Cliente Teste"',
            $csv
        );

        $this->assertSame('local_csv', $driver->name());
        $this->assertSame(
            'local-csv-42',
            $result->externalId
        );
        $this->assertNull($result->httpStatus);
    }

    public function test_it_reports_a_retryable_storage_failure(): void
    {
        $disk = Mockery::mock(Filesystem::class);

        $disk->shouldReceive('put')
            ->once()
            ->andReturnFalse();

        $driver = new LocalCsvLeadDeliveryDriver(
            new LeadDeliveryCsvEncoder,
            $disk
        );

        try {
            $driver->deliver($this->payload());

            $this->fail(
                'The local CSV driver accepted a storage failure.'
            );
        } catch (LeadDeliveryException $exception) {
            $this->assertSame(
                'Unable to store the lead CSV locally.',
                $exception->getMessage()
            );
            $this->assertTrue($exception->retryable);
            $this->assertNull($exception->httpStatus);
            $this->assertSame(
                'local_csv_storage_failed',
                $exception->errorCode
            );
        }
    }

    private function payload(): LeadDeliveryPayload
    {
        return new LeadDeliveryPayload(
            internalId: 42,
            type: 'contact',
            name: 'Cliente Teste',
            email: 'cliente@example.com',
            company: 'Empresa Teste',
            companyRegistration: null,
            phone: '5514999990000',
            sector: 'cleaning',
            location: null,
            quantity: null,
            message: 'Solicitação comercial.',
            language: 'pt',
            sourcePage: 'home',
        );
    }
}

<?php

namespace Tests\Feature;

use App\Exceptions\LeadDeliveryException;
use App\Jobs\DeliverLeadJob;
use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use App\Services\LeadDelivery\LeadDeliveryCsvEncoder;
use App\Services\LeadDelivery\LocalCsvLeadDeliveryDriver;
use App\Services\LeadDeliveryLogger;
use App\Services\LeadDeliveryService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LocalCsvLeadDeliveryFailureIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_the_lead_for_retry_after_a_temporary_storage_failure(): void
    {
        config([
            'lead-delivery.max_attempts' => 3,
        ]);

        $disk = Mockery::mock(Filesystem::class);

        $disk->shouldReceive('put')
            ->once()
            ->andReturnFalse();

        $lead = $this->createLead();

        $job = new DeliverLeadJob($lead);
        $job->withFakeQueueInteractions();

        try {
            $job->handle($this->serviceWithDisk($disk));

            $this->fail(
                'The temporary storage failure was not propagated.'
            );
        } catch (LeadDeliveryException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame(
                'local_csv_storage_failed',
                $exception->errorCode
            );
        }

        $lead->refresh();

        $attempt = $lead->deliveryAttempts()->sole();

        $this->assertSame(
            Lead::STATUS_RETRYING,
            $lead->status
        );

        $this->assertSame(
            LeadDeliveryAttempt::STATUS_FAILED,
            $attempt->status
        );

        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame('local_csv', $attempt->driver);
        $this->assertNull($attempt->http_status);
        $this->assertNull($attempt->external_id);

        $this->assertSame(
            'local_csv_storage_failed',
            $attempt->error_code
        );

        $this->assertSame(
            'Unable to store the lead CSV locally.',
            $attempt->error_message
        );

        $this->assertNotNull($attempt->finished_at);
    }

    public function test_it_recovers_after_a_temporary_storage_failure(): void
    {
        config([
            'lead-delivery.max_attempts' => 3,
        ]);

        $disk = Mockery::mock(Filesystem::class);

        $disk->shouldReceive('put')
            ->twice()
            ->andReturn(false, true);

        $lead = $this->createLead();

        $job = new DeliverLeadJob($lead);
        $job->withFakeQueueInteractions();

        $service = $this->serviceWithDisk($disk);

        try {
            $job->handle($service);

            $this->fail(
                'The first storage failure was not propagated.'
            );
        } catch (LeadDeliveryException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame(
                'local_csv_storage_failed',
                $exception->errorCode
            );
        }

        $job->handle($service);

        $lead->refresh();

        $attempts = $lead->deliveryAttempts()
            ->orderBy('attempt_number')
            ->get();

        $this->assertSame(Lead::STATUS_SENT, $lead->status);
        $this->assertCount(2, $attempts);

        $this->assertSame(
            LeadDeliveryAttempt::STATUS_FAILED,
            $attempts[0]->status
        );

        $this->assertSame(
            LeadDeliveryAttempt::STATUS_SUCCEEDED,
            $attempts[1]->status
        );

        $this->assertSame(1, $attempts[0]->attempt_number);
        $this->assertSame(2, $attempts[1]->attempt_number);

        $this->assertSame(
            'local_csv_storage_failed',
            $attempts[0]->error_code
        );

        $this->assertSame(
            "local-csv-{$lead->id}",
            $attempts[1]->external_id
        );
    }

    public function test_it_stops_after_the_maximum_number_of_storage_failures(): void
    {
        config([
            'lead-delivery.max_attempts' => 2,
        ]);

        $disk = Mockery::mock(Filesystem::class);

        $disk->shouldReceive('put')
            ->twice()
            ->andReturnFalse();

        $lead = $this->createLead();

        $job = new DeliverLeadJob($lead);
        $job->withFakeQueueInteractions();

        $service = $this->serviceWithDisk($disk);

        for ($attemptNumber = 1; $attemptNumber <= 2; $attemptNumber++) {
            try {
                $job->handle($service);

                $this->fail(
                    'The storage failure was not propagated.'
                );
            } catch (LeadDeliveryException $exception) {
                $this->assertTrue($exception->retryable);
                $this->assertSame(
                    'local_csv_storage_failed',
                    $exception->errorCode
                );
            }
        }

        $job->handle($service);

        $lead->refresh();

        $attempts = $lead->deliveryAttempts()
            ->orderBy('attempt_number')
            ->get();

        $this->assertSame(
            Lead::STATUS_FAILED,
            $lead->status
        );

        $this->assertCount(2, $attempts);
        $this->assertSame(1, $attempts[0]->attempt_number);
        $this->assertSame(2, $attempts[1]->attempt_number);

        $this->assertSame(
            LeadDeliveryAttempt::STATUS_FAILED,
            $attempts[0]->status
        );

        $this->assertSame(
            LeadDeliveryAttempt::STATUS_FAILED,
            $attempts[1]->status
        );

        $this->assertSame(
            'local_csv_storage_failed',
            $attempts[0]->error_code
        );

        $this->assertSame(
            'local_csv_storage_failed',
            $attempts[1]->error_code
        );

        $this->assertNull($attempts[0]->external_id);
        $this->assertNull($attempts[1]->external_id);

        $this->assertSame(
            2,
            $lead->deliveryAttempts()->count()
        );
    }

    private function serviceWithDisk(
        Filesystem $disk
    ): LeadDeliveryService {
        return new LeadDeliveryService(
            new LocalCsvLeadDeliveryDriver(
                new LeadDeliveryCsvEncoder,
                $disk,
                'lead-delivery/failure-integration-test'
            ),
            app(LeadDeliveryLogger::class)
        );
    }

    private function createLead(): Lead
    {
        return Lead::create([
            'type' => Lead::TYPE_CONTACT,
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'company' => 'Empresa Exemplo',
            'company_registration' => null,
            'phone' => '+55 14 99999-0000',
            'sector' => 'Higiene e Limpeza',
            'message' => 'Solicito informacoes tecnicas.',
            'language' => 'pt',
            'source_page' => 'home',
        ]);
    }
}

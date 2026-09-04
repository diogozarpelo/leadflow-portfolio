<?php

namespace Tests\Feature;

use App\Contracts\LeadDeliveryDriver;
use App\Data\LeadDeliveryResult;
use App\Exceptions\LeadDeliveryException;
use App\Jobs\DeliverLeadJob;
use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use App\Services\LeadDelivery\NullLeadDeliveryDriver;
use App\Services\LeadDeliveryLogger;
use App\Services\LeadDeliveryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DeliverLeadJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_configured_queue_settings_and_lock(): void
    {
        config([
            'lead-delivery.max_attempts' => 4,
            'lead-delivery.timeout' => 15,
        ]);

        $job = new DeliverLeadJob($this->createLead());
        $middleware = $job->middleware();

        $this->assertSame(4, $job->tries);
        $this->assertSame(15, $job->timeout);
        $this->assertTrue($job->deleteWhenMissingModels);
        $this->assertSame([60, 300, 900], $job->backoff());
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(
            WithoutOverlapping::class,
            $middleware[0]
        );
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame(
            (string) $job->lead->getKey(),
            $job->uniqueId()
        );
        $this->assertSame(3600, $job->uniqueFor());
    }

    public function test_it_does_not_queue_duplicate_jobs_for_the_same_lead(): void
    {
        Queue::fake();

        $lead = $this->createLead();

        DeliverLeadJob::dispatch($lead);
        DeliverLeadJob::dispatch($lead);

        Queue::assertPushed(DeliverLeadJob::class, 1);
    }

    public function test_it_delivers_a_lead_through_the_service(): void
    {
        $lead = $this->createLead();

        $driver = Mockery::mock(LeadDeliveryDriver::class);
        $driver->shouldReceive('name')
            ->once()
            ->andReturn('testing');
        $driver->shouldReceive('deliver')
            ->once()
            ->andReturn(new LeadDeliveryResult(
                externalId: 'external-job-123',
                httpStatus: 202,
            ));

        $job = new DeliverLeadJob($lead);
        $job->withFakeQueueInteractions();

        $job->handle(new LeadDeliveryService($driver, app(LeadDeliveryLogger::class)));

        $lead->refresh();
        $attempt = $lead->deliveryAttempts()->first();

        $this->assertSame(Lead::STATUS_SENT, $lead->status);
        $this->assertNotNull($attempt);
        $this->assertSame(
            LeadDeliveryAttempt::STATUS_SUCCEEDED,
            $attempt->status
        );
        $this->assertSame(
            'external-job-123',
            $attempt->external_id
        );
    }

    public function test_it_fails_immediately_for_a_non_retryable_error(): void
    {
        $lead = $this->createLead();

        $job = new DeliverLeadJob($lead);
        $job->withFakeQueueInteractions();

        $job->handle(
            new LeadDeliveryService(
                new NullLeadDeliveryDriver,
                app(LeadDeliveryLogger::class)
            )
        );

        $job->assertFailedWith(LeadDeliveryException::class);

        $lead->refresh();
        $attempt = $lead->deliveryAttempts()->first();

        $this->assertSame(Lead::STATUS_FAILED, $lead->status);
        $this->assertNotNull($attempt);
        $this->assertSame(
            'delivery_not_configured',
            $attempt->error_code
        );
    }

    public function test_it_propagates_a_retryable_error_to_the_queue(): void
    {
        $lead = $this->createLead();

        $exception = new LeadDeliveryException(
            'Temporary queue failure.',
            retryable: true,
            httpStatus: 503,
            errorCode: 'temporarily_unavailable',
        );

        $driver = Mockery::mock(LeadDeliveryDriver::class);
        $driver->shouldReceive('name')
            ->once()
            ->andReturn('testing');
        $driver->shouldReceive('deliver')
            ->once()
            ->andThrow($exception);

        $job = new DeliverLeadJob($lead);
        $job->withFakeQueueInteractions();

        try {
            $job->handle(new LeadDeliveryService($driver, app(LeadDeliveryLogger::class)));

            $this->fail('The retryable error was not propagated.');
        } catch (LeadDeliveryException $caughtException) {
            $this->assertSame($exception, $caughtException);
        }

        $lead->refresh();

        $this->assertSame(Lead::STATUS_RETRYING, $lead->status);
        $this->assertSame(1, $lead->deliveryAttempts()->count());
    }

    public function test_failed_callback_finalizes_a_retrying_lead(): void
    {
        $lead = $this->createLead();
        $lead->transitionTo(Lead::STATUS_PROCESSING);
        $lead->transitionTo(Lead::STATUS_RETRYING);

        $job = new DeliverLeadJob($lead);

        $job->failed(new RuntimeException('Queue stopped.'));

        $this->assertSame(
            Lead::STATUS_FAILED,
            $lead->fresh()->status
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

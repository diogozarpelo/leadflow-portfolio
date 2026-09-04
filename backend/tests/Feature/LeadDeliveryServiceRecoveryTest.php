<?php

namespace Tests\Feature;

use App\Contracts\LeadDeliveryDriver;
use App\Data\LeadDeliveryResult;
use App\Exceptions\LeadDeliveryException;
use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use App\Services\LeadDeliveryLogger;
use App\Services\LeadDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LeadDeliveryServiceRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stops_after_the_configured_attempt_limit(): void
    {
        config([
            'lead-delivery.max_attempts' => 2,
        ]);

        $lead = $this->createLead();

        $exception = new LeadDeliveryException(
            'Temporary delivery failure.',
            retryable: true,
            httpStatus: 503,
            errorCode: 'temporarily_unavailable',
        );

        $driver = Mockery::mock(LeadDeliveryDriver::class);
        $driver->shouldReceive('name')
            ->twice()
            ->andReturn('testing');
        $driver->shouldReceive('deliver')
            ->twice()
            ->andThrow($exception);

        $service = new LeadDeliveryService($driver, app(LeadDeliveryLogger::class));

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $service->deliver($lead);

                $this->fail('A retryable failure was not propagated.');
            } catch (LeadDeliveryException $caughtException) {
                $this->assertSame($exception, $caughtException);
            }
        }

        $lead->refresh();
        $attempts = $lead->deliveryAttempts()
            ->orderBy('attempt_number')
            ->get();

        $this->assertSame(Lead::STATUS_FAILED, $lead->status);
        $this->assertCount(2, $attempts);
        $this->assertSame(
            [1, 2],
            $attempts->pluck('attempt_number')->all()
        );
        $this->assertTrue(
            $attempts->every(
                fn (LeadDeliveryAttempt $attempt): bool => $attempt->status
                    === LeadDeliveryAttempt::STATUS_FAILED
            )
        );
    }

    public function test_it_stores_a_safe_message_for_an_unexpected_failure(): void
    {
        $lead = $this->createLead();
        $exception = new RuntimeException(
            'Sensitive external response must not be stored.'
        );

        $driver = Mockery::mock(LeadDeliveryDriver::class);
        $driver->shouldReceive('name')
            ->once()
            ->andReturn('testing');
        $driver->shouldReceive('deliver')
            ->once()
            ->andThrow($exception);

        $service = new LeadDeliveryService($driver, app(LeadDeliveryLogger::class));

        try {
            $service->deliver($lead);

            $this->fail('An unexpected failure was not propagated.');
        } catch (RuntimeException $caughtException) {
            $this->assertSame($exception, $caughtException);
        }

        $lead->refresh();
        $attempt = $lead->deliveryAttempts()->first();

        $this->assertSame(Lead::STATUS_RETRYING, $lead->status);
        $this->assertNotNull($attempt);
        $this->assertSame('unexpected_error', $attempt->error_code);
        $this->assertSame(
            'Unexpected lead delivery failure.',
            $attempt->error_message
        );
        $this->assertStringNotContainsString(
            'Sensitive external response',
            $attempt->error_message
        );
    }

    public function test_it_recovers_an_interrupted_processing_attempt(): void
    {
        $lead = $this->createLead();
        $lead->transitionTo(Lead::STATUS_PROCESSING);

        $interruptedAttempt = $this->createAttempt(
            $lead,
            1,
            LeadDeliveryAttempt::STATUS_PROCESSING
        );

        $driver = Mockery::mock(LeadDeliveryDriver::class);
        $driver->shouldReceive('name')
            ->once()
            ->andReturn('testing');
        $driver->shouldReceive('deliver')
            ->once()
            ->andReturn(new LeadDeliveryResult(
                externalId: 'external-456',
                httpStatus: 202,
            ));

        $service = new LeadDeliveryService($driver, app(LeadDeliveryLogger::class));
        $service->deliver($lead);

        $lead->refresh();
        $interruptedAttempt->refresh();

        $successfulAttempt = $lead->deliveryAttempts()
            ->where('attempt_number', 2)
            ->first();

        $this->assertSame(Lead::STATUS_SENT, $lead->status);
        $this->assertSame(
            LeadDeliveryAttempt::STATUS_FAILED,
            $interruptedAttempt->status
        );
        $this->assertSame(
            'processing_interrupted',
            $interruptedAttempt->error_code
        );
        $this->assertNotNull($interruptedAttempt->finished_at);
        $this->assertNotNull($successfulAttempt);
        $this->assertSame(
            LeadDeliveryAttempt::STATUS_SUCCEEDED,
            $successfulAttempt->status
        );
        $this->assertSame(
            'external-456',
            $successfulAttempt->external_id
        );
    }

    public function test_it_recovers_when_success_was_saved_before_lead_status(): void
    {
        $lead = $this->createLead();
        $lead->transitionTo(Lead::STATUS_PROCESSING);

        $this->createAttempt(
            $lead,
            1,
            LeadDeliveryAttempt::STATUS_SUCCEEDED
        );

        $driver = Mockery::mock(LeadDeliveryDriver::class);
        $driver->shouldNotReceive('name');
        $driver->shouldNotReceive('deliver');

        $service = new LeadDeliveryService($driver, app(LeadDeliveryLogger::class));
        $service->deliver($lead);

        $lead->refresh();

        $this->assertSame(Lead::STATUS_SENT, $lead->status);
        $this->assertSame(1, $lead->deliveryAttempts()->count());
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

    private function createAttempt(
        Lead $lead,
        int $attemptNumber,
        string $status
    ): LeadDeliveryAttempt {
        return $lead->deliveryAttempts()->create([
            'attempt_number' => $attemptNumber,
            'driver' => 'testing',
            'status' => $status,
            'http_status' => $status
                === LeadDeliveryAttempt::STATUS_SUCCEEDED
                    ? 202
                    : null,
            'external_id' => $status
                === LeadDeliveryAttempt::STATUS_SUCCEEDED
                    ? 'external-123'
                    : null,
            'error_code' => null,
            'error_message' => null,
            'started_at' => now()->subSecond(),
            'finished_at' => $status
                === LeadDeliveryAttempt::STATUS_SUCCEEDED
                    ? now()
                    : null,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Contracts\LeadDeliveryDriver;
use App\Data\LeadDeliveryPayload;
use App\Data\LeadDeliveryResult;
use App\Exceptions\LeadDeliveryException;
use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use App\Services\LeadDeliveryLogger;
use App\Services\LeadDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LeadDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_delivers_a_lead_and_ignores_a_duplicate_delivery(): void
    {
        $lead = $this->createLead();

        $driver = Mockery::mock(LeadDeliveryDriver::class);
        $driver->shouldReceive('name')
            ->once()
            ->andReturn('testing');
        $driver->shouldReceive('deliver')
            ->once()
            ->with(Mockery::on(
                fn (LeadDeliveryPayload $payload): bool => (
                    $payload->internalId === $lead->id
                    && $payload->email === $lead->email
                )
            ))
            ->andReturn(new LeadDeliveryResult(
                externalId: 'external-123',
                httpStatus: 202,
            ));

        $service = new LeadDeliveryService($driver, app(LeadDeliveryLogger::class));

        $service->deliver($lead);
        $service->deliver($lead);

        $lead->refresh();

        $this->assertSame(Lead::STATUS_SENT, $lead->status);
        $this->assertCount(1, $lead->deliveryAttempts);

        $attempt = $lead->deliveryAttempts->first();

        $this->assertNotNull($attempt);
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame('testing', $attempt->driver);
        $this->assertSame(
            LeadDeliveryAttempt::STATUS_SUCCEEDED,
            $attempt->status
        );
        $this->assertSame(202, $attempt->http_status);
        $this->assertSame('external-123', $attempt->external_id);
        $this->assertNotNull($attempt->finished_at);
    }

    public function test_it_marks_a_retryable_failure_for_another_attempt(): void
    {
        $lead = $this->createLead();

        $exception = new LeadDeliveryException(
            'Temporary delivery failure.',
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

        $service = new LeadDeliveryService($driver, app(LeadDeliveryLogger::class));

        try {
            $service->deliver($lead);

            $this->fail('A retryable failure was not propagated.');
        } catch (LeadDeliveryException $caughtException) {
            $this->assertSame($exception, $caughtException);
        }

        $lead->refresh();
        $attempt = $lead->deliveryAttempts()->first();

        $this->assertSame(Lead::STATUS_RETRYING, $lead->status);
        $this->assertNotNull($attempt);
        $this->assertSame(
            LeadDeliveryAttempt::STATUS_FAILED,
            $attempt->status
        );
        $this->assertSame(503, $attempt->http_status);
        $this->assertSame(
            'temporarily_unavailable',
            $attempt->error_code
        );
        $this->assertSame(
            'Temporary delivery failure.',
            $attempt->error_message
        );
        $this->assertNotNull($attempt->finished_at);
    }

    public function test_it_marks_a_non_retryable_failure_as_failed(): void
    {
        $lead = $this->createLead();

        $exception = new LeadDeliveryException(
            'Permanent delivery failure.',
            retryable: false,
            httpStatus: 422,
            errorCode: 'invalid_payload',
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

            $this->fail('A permanent failure was not propagated.');
        } catch (LeadDeliveryException $caughtException) {
            $this->assertSame($exception, $caughtException);
        }

        $lead->refresh();
        $attempt = $lead->deliveryAttempts()->first();

        $this->assertSame(Lead::STATUS_FAILED, $lead->status);
        $this->assertNotNull($attempt);
        $this->assertSame(
            LeadDeliveryAttempt::STATUS_FAILED,
            $attempt->status
        );
        $this->assertSame(422, $attempt->http_status);
        $this->assertSame('invalid_payload', $attempt->error_code);
        $this->assertSame(
            'Permanent delivery failure.',
            $attempt->error_message
        );
        $this->assertNotNull($attempt->finished_at);
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

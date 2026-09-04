<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use App\Services\LeadDeliveryLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

class LeadDeliveryLoggerTest extends TestCase
{
    public function test_it_logs_an_attempt_start(): void
    {
        [$logger, $channel] = $this->logger();

        $logger->attemptStarted(
            $this->lead(),
            $this->attempt()
        );

        $this->assertSame([
            [
                'level' => 'info',
                'message' => 'lead_delivery.attempt_started',
                'context' => $this->attemptContext(),
            ],
        ], $channel->records);
    }

    public function test_it_logs_an_attempt_success(): void
    {
        [$logger, $channel] = $this->logger();

        $logger->attemptSucceeded(
            $this->lead(),
            $this->attempt()
        );

        $this->assertSame([
            [
                'level' => 'info',
                'message' => 'lead_delivery.attempt_succeeded',
                'context' => $this->attemptContext(),
            ],
        ], $channel->records);
    }

    public function test_it_logs_interrupted_processing(): void
    {
        [$logger, $channel] = $this->logger();

        $logger->processingInterrupted(
            $this->lead(),
            $this->attempt()
        );

        $this->assertSame([
            [
                'level' => 'warning',
                'message' => 'lead_delivery.processing_interrupted',
                'context' => $this->attemptContext(),
            ],
        ], $channel->records);
    }

    public function test_it_logs_a_safe_failed_attempt(): void
    {
        [$logger, $channel] = $this->logger();

        $attempt = $this->attempt();
        $attempt->forceFill([
            'status' => LeadDeliveryAttempt::STATUS_FAILED,
            'http_status' => 503,
            'error_code' => 'service_unavailable',
        ]);

        $logger->attemptFailed(
            $this->lead(),
            $attempt,
            true
        );

        $this->assertSame([
            [
                'level' => 'warning',
                'message' => 'lead_delivery.attempt_failed',
                'context' => [
                    'lead_id' => 41,
                    'lead_status' => Lead::STATUS_PROCESSING,
                    'attempt_number' => 2,
                    'driver' => 'test',
                    'attempt_status' => LeadDeliveryAttempt::STATUS_FAILED,
                    'http_status' => 503,
                    'error_code' => 'service_unavailable',
                    'retryable' => true,
                ],
            ],
        ], $channel->records);
    }

    public function test_it_logs_the_attempt_limit_without_personal_data(): void
    {
        [$logger, $channel] = $this->logger();

        $logger->maxAttemptsReached(
            $this->lead(),
            3
        );

        $this->assertSame([
            [
                'level' => 'warning',
                'message' => 'lead_delivery.max_attempts_reached',
                'context' => [
                    'lead_id' => 41,
                    'lead_status' => Lead::STATUS_PROCESSING,
                    'max_attempts' => 3,
                ],
            ],
        ], $channel->records);
    }

    public function test_it_logs_only_the_exception_type(): void
    {
        [$logger, $channel] = $this->logger();

        $logger->jobFailed(
            $this->lead(),
            new RuntimeException('sensitive exception message')
        );

        $this->assertSame([
            [
                'level' => 'error',
                'message' => 'lead_delivery.job_failed',
                'context' => [
                    'lead_id' => 41,
                    'lead_status' => Lead::STATUS_PROCESSING,
                    'exception_type' => RuntimeException::class,
                ],
            ],
        ], $channel->records);

        $serialized = json_encode($channel->records);

        $this->assertIsString($serialized);
        $this->assertStringNotContainsString(
            'sensitive exception message',
            $serialized
        );
    }

    private function lead(): Lead
    {
        $lead = new Lead;

        $lead->forceFill([
            'id' => 41,
            'status' => Lead::STATUS_PROCESSING,
            'name' => 'Sensitive Name',
            'email' => 'sensitive@example.com',
            'company' => 'Sensitive Company',
            'phone' => '+55 14 99999-0000',
            'message' => 'Sensitive lead message',
        ]);

        return $lead;
    }

    private function attempt(): LeadDeliveryAttempt
    {
        $attempt = new LeadDeliveryAttempt;

        $attempt->forceFill([
            'attempt_number' => 2,
            'driver' => 'test',
            'status' => LeadDeliveryAttempt::STATUS_PROCESSING,
            'http_status' => null,
            'error_code' => null,
        ]);

        return $attempt;
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function attemptContext(): array
    {
        return [
            'lead_id' => 41,
            'lead_status' => Lead::STATUS_PROCESSING,
            'attempt_number' => 2,
            'driver' => 'test',
            'attempt_status' => LeadDeliveryAttempt::STATUS_PROCESSING,
            'http_status' => null,
            'error_code' => null,
        ];
    }

    /**
     * @return array{LeadDeliveryLogger, InMemoryLeadDeliveryChannel}
     */
    private function logger(): array
    {
        $channel = new InMemoryLeadDeliveryChannel;

        return [
            new LeadDeliveryLogger($channel),
            $channel,
        ];
    }
}

final class InMemoryLeadDeliveryChannel extends AbstractLogger
{
    /**
     * @var array<int, array{
     *     level: string,
     *     message: string,
     *     context: array<string, mixed>
     * }>
     */
    public array $records = [];

    /**
     * @param  mixed  $level
     * @param  array<string, mixed>  $context
     */
    public function log(
        $level,
        string|Stringable $message,
        array $context = []
    ): void {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

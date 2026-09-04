<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use Psr\Log\LoggerInterface;
use Throwable;

final class LeadDeliveryLogger
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function attemptStarted(
        Lead $lead,
        LeadDeliveryAttempt $attempt
    ): void {
        $this->logger->info(
            'lead_delivery.attempt_started',
            $this->attemptContext($lead, $attempt)
        );
    }

    public function attemptSucceeded(
        Lead $lead,
        LeadDeliveryAttempt $attempt
    ): void {
        $this->logger->info(
            'lead_delivery.attempt_succeeded',
            $this->attemptContext($lead, $attempt)
        );
    }

    public function attemptFailed(
        Lead $lead,
        LeadDeliveryAttempt $attempt,
        bool $retryable
    ): void {
        $this->logger->warning(
            'lead_delivery.attempt_failed',
            $this->attemptContext($lead, $attempt) + [
                'retryable' => $retryable,
            ]
        );
    }

    public function processingInterrupted(
        Lead $lead,
        LeadDeliveryAttempt $attempt
    ): void {
        $this->logger->warning(
            'lead_delivery.processing_interrupted',
            $this->attemptContext($lead, $attempt)
        );
    }

    public function maxAttemptsReached(
        Lead $lead,
        int $maxAttempts
    ): void {
        $this->logger->warning(
            'lead_delivery.max_attempts_reached',
            $this->leadContext($lead) + [
                'max_attempts' => $maxAttempts,
            ]
        );
    }

    public function jobFailed(
        Lead $lead,
        ?Throwable $exception
    ): void {
        $this->logger->error(
            'lead_delivery.job_failed',
            $this->leadContext($lead) + [
                'exception_type' => $exception === null
                    ? null
                    : $exception::class,
            ]
        );
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function attemptContext(
        Lead $lead,
        LeadDeliveryAttempt $attempt
    ): array {
        return $this->leadContext($lead) + [
            'attempt_number' => (int) $attempt->attempt_number,
            'driver' => $attempt->driver,
            'attempt_status' => $attempt->status,
            'http_status' => $attempt->http_status,
            'error_code' => $attempt->error_code,
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function leadContext(Lead $lead): array
    {
        return [
            'lead_id' => $lead->getKey(),
            'lead_status' => $lead->status,
        ];
    }
}

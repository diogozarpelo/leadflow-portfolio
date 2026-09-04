<?php

namespace App\Services;

use App\Contracts\LeadDeliveryDriver;
use App\Data\LeadDeliveryPayload;
use App\Exceptions\LeadDeliveryException;
use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use Throwable;

final class LeadDeliveryService
{
    public function __construct(
        private readonly LeadDeliveryDriver $driver,
        private readonly LeadDeliveryLogger $logger
    ) {}

    public function deliver(Lead $lead): void
    {
        $lead->refresh();

        if (in_array(
            $lead->status,
            [Lead::STATUS_SENT, Lead::STATUS_FAILED],
            true
        )) {
            return;
        }

        if (! $this->prepareForDelivery($lead)) {
            return;
        }

        $lead->transitionTo(Lead::STATUS_PROCESSING);

        $attemptNumber = (
            (int) $lead->deliveryAttempts()->max('attempt_number')
        ) + 1;

        $maxAttempts = $this->maxAttempts();

        if ($attemptNumber > $maxAttempts) {
            $lead->transitionTo(Lead::STATUS_FAILED);

            $this->logger->maxAttemptsReached(
                $lead,
                $maxAttempts
            );

            return;
        }

        $attempt = $lead->deliveryAttempts()->create([
            'attempt_number' => $attemptNumber,
            'driver' => mb_substr($this->driver->name(), 0, 50),
            'status' => LeadDeliveryAttempt::STATUS_PROCESSING,
            'http_status' => null,
            'external_id' => null,
            'error_code' => null,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $this->logger->attemptStarted($lead, $attempt);

        try {
            $result = $this->driver->deliver(
                LeadDeliveryPayload::fromLead($lead)
            );

            $attempt->forceFill([
                'status' => LeadDeliveryAttempt::STATUS_SUCCEEDED,
                'http_status' => $result->httpStatus,
                'external_id' => $this->limit(
                    $result->externalId,
                    191
                ),
                'finished_at' => now(),
            ])->save();

            $lead->transitionTo(Lead::STATUS_SENT);
            $this->logger->attemptSucceeded($lead, $attempt);
        } catch (LeadDeliveryException $exception) {
            $this->markAttemptAsFailed(
                $attempt,
                $exception->httpStatus,
                $exception->errorCode,
                $exception->getMessage()
            );

            $this->transitionAfterFailure(
                $lead,
                $attemptNumber,
                $exception->retryable
            );

            $this->logger->attemptFailed(
                $lead,
                $attempt,
                $exception->retryable
            );

            throw $exception;
        } catch (Throwable $exception) {
            $this->markAttemptAsFailed(
                $attempt,
                null,
                'unexpected_error',
                'Unexpected lead delivery failure.'
            );

            $this->transitionAfterFailure(
                $lead,
                $attemptNumber,
                true
            );

            $this->logger->attemptFailed(
                $lead,
                $attempt,
                true
            );

            throw $exception;
        }
    }

    private function prepareForDelivery(Lead $lead): bool
    {
        if ($lead->status !== Lead::STATUS_PROCESSING) {
            return in_array(
                $lead->status,
                [Lead::STATUS_PENDING, Lead::STATUS_RETRYING],
                true
            );
        }

        $latestAttempt = $lead->deliveryAttempts()
            ->latest('attempt_number')
            ->first();

        if (
            $latestAttempt?->status
            === LeadDeliveryAttempt::STATUS_SUCCEEDED
        ) {
            $lead->transitionTo(Lead::STATUS_SENT);

            $this->logger->attemptSucceeded(
                $lead,
                $latestAttempt
            );

            return false;
        }

        if (
            $latestAttempt?->status
            === LeadDeliveryAttempt::STATUS_PROCESSING
        ) {
            $this->markAttemptAsFailed(
                $latestAttempt,
                null,
                'processing_interrupted',
                'Lead delivery processing was interrupted.'
            );

            $this->logger->processingInterrupted(
                $lead,
                $latestAttempt
            );
        }

        $maxAttempts = $this->maxAttempts();

        if (
            (int) ($latestAttempt?->attempt_number ?? 0)
            >= $maxAttempts
        ) {
            $lead->transitionTo(Lead::STATUS_FAILED);

            $this->logger->maxAttemptsReached(
                $lead,
                $maxAttempts
            );

            return false;
        }

        $lead->transitionTo(Lead::STATUS_RETRYING);

        return true;
    }

    private function transitionAfterFailure(
        Lead $lead,
        int $attemptNumber,
        bool $retryable
    ): void {
        $maxAttempts = $this->maxAttempts();

        if ($retryable && $attemptNumber < $maxAttempts) {
            $lead->transitionTo(Lead::STATUS_RETRYING);

            return;
        }

        $lead->transitionTo(Lead::STATUS_FAILED);

        if ($retryable && $attemptNumber >= $maxAttempts) {
            $this->logger->maxAttemptsReached(
                $lead,
                $maxAttempts
            );
        }
    }

    private function markAttemptAsFailed(
        LeadDeliveryAttempt $attempt,
        ?int $httpStatus,
        ?string $errorCode,
        string $errorMessage
    ): void {
        $attempt->forceFill([
            'status' => LeadDeliveryAttempt::STATUS_FAILED,
            'http_status' => $httpStatus,
            'error_code' => $this->limit($errorCode, 100),
            'error_message' => $this->limit($errorMessage, 1000),
            'finished_at' => now(),
        ])->save();
    }

    private function maxAttempts(): int
    {
        return max(
            1,
            (int) config('lead-delivery.max_attempts', 3)
        );
    }

    private function limit(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr(trim($value), 0, $length);
    }
}

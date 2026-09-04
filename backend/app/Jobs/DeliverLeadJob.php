<?php

namespace App\Jobs;

use App\Exceptions\LeadDeliveryException;
use App\Models\Lead;
use App\Services\LeadDeliveryLogger;
use App\Services\LeadDeliveryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class DeliverLeadJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Lead $lead)
    {
        $this->tries = max(
            1,
            (int) config('lead-delivery.max_attempts', 3)
        );

        $this->timeout = max(
            1,
            (int) config('lead-delivery.timeout', 10)
        );
    }

    public function uniqueId(): string
    {
        return (string) $this->lead->getKey();
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                "lead-delivery:{$this->lead->getKey()}"
            ))
                ->dontRelease()
                ->expireAfter($this->timeout + 60),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @throws Throwable
     */
    public function handle(LeadDeliveryService $service): void
    {
        try {
            $service->deliver($this->lead);
        } catch (LeadDeliveryException $exception) {
            if ($exception->retryable) {
                throw $exception;
            }

            $this->fail($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $lead = Lead::query()->find($this->lead->getKey());

        if ($lead === null) {
            return;
        }

        if (in_array(
            $lead->status,
            [Lead::STATUS_SENT, Lead::STATUS_FAILED],
            true
        )) {
            return;
        }

        if ($lead->canTransitionTo(Lead::STATUS_FAILED)) {
            $lead->transitionTo(Lead::STATUS_FAILED);
        }

        app(LeadDeliveryLogger::class)->jobFailed(
            $lead,
            $exception
        );
    }
}

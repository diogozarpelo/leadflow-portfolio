<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leads:status')]
#[Description('Show aggregate lead delivery status without personal data')]
final class LeadDeliveryStatus extends Command
{
    /**
     * @var array<int, string>
     */
    private const LEAD_STATUSES = [
        Lead::STATUS_PENDING,
        Lead::STATUS_PROCESSING,
        Lead::STATUS_RETRYING,
        Lead::STATUS_SENT,
        Lead::STATUS_FAILED,
    ];

    /**
     * @var array<int, string>
     */
    private const ATTEMPT_STATUSES = [
        LeadDeliveryAttempt::STATUS_PROCESSING,
        LeadDeliveryAttempt::STATUS_SUCCEEDED,
        LeadDeliveryAttempt::STATUS_FAILED,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queueConnection = (string) config(
            'queue.default',
            'database'
        );

        $queueName = (string) config(
            "queue.connections.{$queueConnection}.queue",
            'default'
        );

        $this->components->info('Lead delivery operational status');

        $this->line(
            'Delivery enabled: '.
            ((bool) config('lead-delivery.enabled') ? 'yes' : 'no')
        );
        $this->line(
            'Delivery driver: '.
            (string) config('lead-delivery.driver', 'null')
        );
        $this->line("Queue connection: {$queueConnection}");
        $this->line("Queue name: {$queueName}");

        $this->newLine();

        $this->table(
            ['Lead status', 'Total'],
            $this->leadStatusRows()
        );

        $this->newLine();

        $this->table(
            ['Attempt status', 'Total'],
            $this->attemptStatusRows()
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{string, int}>
     */
    private function leadStatusRows(): array
    {
        $counts = Lead::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return array_map(
            static fn (string $status): array => [
                $status,
                (int) ($counts[$status] ?? 0),
            ],
            self::LEAD_STATUSES
        );
    }

    /**
     * @return array<int, array{string, int}>
     */
    private function attemptStatusRows(): array
    {
        $counts = LeadDeliveryAttempt::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return array_map(
            static fn (string $status): array => [
                $status,
                (int) ($counts[$status] ?? 0),
            ],
            self::ATTEMPT_STATUSES
        );
    }
}

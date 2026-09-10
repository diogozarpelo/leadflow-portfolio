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
            Lead::STATUSES
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
            LeadDeliveryAttempt::STATUSES
        );
    }
}

<?php

namespace App\Console\Commands;

use App\Contracts\LeadDeliveryDriver;
use App\Jobs\DeliverLeadJob;
use App\Models\Lead;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature(
    'leads:dispatch-pending
    {--limit=100 : Maximum number of pending leads to dispatch}
    {--dry-run : Count pending leads without dispatching jobs}'
)]
#[Description('Dispatch pending leads to the configured delivery queue')]
final class DispatchPendingLeads extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = filter_var(
            $this->option('limit'),
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 1000,
                ],
            ]
        );

        if ($limit === false) {
            $this->error(
                'The limit must be an integer between 1 and 1000.'
            );

            return self::INVALID;
        }

        $leads = Lead::query()
            ->where('status', Lead::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ((bool) $this->option('dry-run')) {
            $this->info(
                "{$leads->count()} pending lead(s) would be dispatched."
            );

            return self::SUCCESS;
        }

        if (! (bool) config('lead-delivery.enabled', false)) {
            $this->error('Lead delivery is disabled.');

            return self::FAILURE;
        }

        if (
            (string) config('lead-delivery.driver', 'null')
            === 'null'
        ) {
            $this->error('Lead delivery driver is not configured.');

            return self::FAILURE;
        }

        try {
            app(LeadDeliveryDriver::class);
        } catch (Throwable) {
            $this->error(
                'The configured lead delivery driver could not be resolved.'
            );

            return self::FAILURE;
        }

        foreach ($leads as $lead) {
            DeliverLeadJob::dispatch($lead)->afterCommit();
        }

        $this->info(
            "{$leads->count()} pending lead(s) dispatched."
        );

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\DeliverLeadJob;
use App\Models\Lead;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature(
    'leads:dispatch-pending
    {--limit=100 : Maximum number of pending leads to dispatch}
    {--dry-run : Count pending leads without dispatching jobs}'
)]
#[Description('Dispatch pending leads to the configured delivery queue')]
final class DispatchPendingLeads extends LeadDeliveryCommand
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->validatedLimit();

        if ($limit === null) {
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

        if (! $this->deliveryIsAvailable()) {
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

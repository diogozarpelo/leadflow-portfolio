<?php

namespace App\Console\Commands;

use App\Jobs\DeliverLeadJob;
use App\Models\Lead;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature(
    'leads:retry-failed
    {--limit=100 : Maximum number of failed leads to retry}
    {--dry-run : Count failed leads without changing their status}'
)]
#[Description('Retry failed leads through the configured delivery queue')]
final class RetryFailedLeads extends LeadDeliveryCommand
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

        if ((bool) $this->option('dry-run')) {
            $count = Lead::query()
                ->where('status', Lead::STATUS_FAILED)
                ->limit($limit)
                ->count();

            $this->info(
                "{$count} failed lead(s) would be retried."
            );

            return self::SUCCESS;
        }

        if (! $this->deliveryIsAvailable()) {
            return self::FAILURE;
        }

        $count = 0;

        try {
            DB::transaction(function () use ($limit, &$count): void {
                $leads = Lead::query()
                    ->where('status', Lead::STATUS_FAILED)
                    ->orderBy('id')
                    ->limit($limit)
                    ->lockForUpdate()
                    ->get();

                foreach ($leads as $lead) {
                    $lead->transitionTo(Lead::STATUS_RETRYING);

                    DeliverLeadJob::dispatch($lead)->afterCommit();
                }

                $count = $leads->count();
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'Failed leads could not be prepared for retry.'
            );

            return self::FAILURE;
        }

        $this->info("{$count} failed lead(s) queued for retry.");

        return self::SUCCESS;
    }
}

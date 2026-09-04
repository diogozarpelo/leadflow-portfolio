<?php

namespace App\Console\Commands;

use App\Contracts\LeadDeliveryDriver;
use App\Jobs\DeliverLeadJob;
use App\Models\Lead;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature(
    'leads:retry-failed
    {--limit=100 : Maximum number of failed leads to retry}
    {--dry-run : Count failed leads without changing their status}'
)]
#[Description('Retry failed leads through the configured delivery queue')]
final class RetryFailedLeads extends Command
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

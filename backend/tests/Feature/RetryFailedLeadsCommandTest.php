<?php

namespace Tests\Feature;

use App\Contracts\LeadDeliveryDriver;
use App\Jobs\DeliverLeadJob;
use App\Models\Lead;
use App\Services\LeadDelivery\NullLeadDeliveryDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class RetryFailedLeadsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_counts_failed_leads_without_changing_them(): void
    {
        Queue::fake();

        $firstLead = $this->createFailedLead(
            'primeiro@example.com'
        );
        $secondLead = $this->createFailedLead(
            'segundo@example.com'
        );

        $this->artisan('leads:retry-failed', [
            '--dry-run' => true,
        ])
            ->expectsOutput(
                '2 failed lead(s) would be retried.'
            )
            ->assertExitCode(Command::SUCCESS);

        Queue::assertNothingPushed();

        $this->assertSame(
            Lead::STATUS_FAILED,
            $firstLead->fresh()->status
        );
        $this->assertSame(
            Lead::STATUS_FAILED,
            $secondLead->fresh()->status
        );
    }

    public function test_it_rejects_an_invalid_limit(): void
    {
        Queue::fake();

        $this->artisan('leads:retry-failed', [
            '--limit' => 0,
        ])
            ->expectsOutput(
                'The limit must be an integer between 1 and 1000.'
            )
            ->assertExitCode(Command::INVALID);

        Queue::assertNothingPushed();
    }

    public function test_it_does_not_retry_when_delivery_is_disabled(): void
    {
        Queue::fake();

        config([
            'lead-delivery.enabled' => false,
            'lead-delivery.driver' => 'testing',
        ]);

        $lead = $this->createFailedLead('cliente@example.com');

        $this->artisan('leads:retry-failed')
            ->expectsOutput('Lead delivery is disabled.')
            ->assertExitCode(Command::FAILURE);

        Queue::assertNothingPushed();

        $this->assertSame(
            Lead::STATUS_FAILED,
            $lead->fresh()->status
        );
    }

    public function test_it_does_not_retry_with_the_null_driver(): void
    {
        Queue::fake();

        config([
            'lead-delivery.enabled' => true,
            'lead-delivery.driver' => 'null',
        ]);

        $lead = $this->createFailedLead('cliente@example.com');

        $this->artisan('leads:retry-failed')
            ->expectsOutput(
                'Lead delivery driver is not configured.'
            )
            ->assertExitCode(Command::FAILURE);

        Queue::assertNothingPushed();

        $this->assertSame(
            Lead::STATUS_FAILED,
            $lead->fresh()->status
        );
    }

    public function test_it_rejects_an_unresolvable_driver(): void
    {
        Queue::fake();

        config([
            'lead-delivery.enabled' => true,
            'lead-delivery.driver' => 'unsupported',
        ]);

        $lead = $this->createFailedLead('cliente@example.com');

        $this->artisan('leads:retry-failed')
            ->expectsOutput(
                'The configured lead delivery driver could not be resolved.'
            )
            ->assertExitCode(Command::FAILURE);

        Queue::assertNothingPushed();

        $this->assertSame(
            Lead::STATUS_FAILED,
            $lead->fresh()->status
        );
    }

    public function test_it_retries_only_failed_leads_up_to_the_limit(): void
    {
        Queue::fake();

        config([
            'lead-delivery.enabled' => true,
            'lead-delivery.driver' => 'testing',
        ]);

        $this->app->instance(
            LeadDeliveryDriver::class,
            new NullLeadDeliveryDriver
        );

        $firstLead = $this->createFailedLead(
            'primeiro@example.com'
        );
        $secondLead = $this->createFailedLead(
            'segundo@example.com'
        );
        $thirdLead = $this->createFailedLead(
            'terceiro@example.com'
        );
        $pendingLead = $this->createLead(
            'pendente@example.com'
        );

        $this->artisan('leads:retry-failed', [
            '--limit' => 2,
        ])
            ->expectsOutput(
                '2 failed lead(s) queued for retry.'
            )
            ->assertExitCode(Command::SUCCESS);

        Queue::assertPushed(DeliverLeadJob::class, 2);

        Queue::assertPushed(
            DeliverLeadJob::class,
            fn (DeliverLeadJob $job): bool => $job->lead->is(
                $firstLead
            )
        );

        Queue::assertPushed(
            DeliverLeadJob::class,
            fn (DeliverLeadJob $job): bool => $job->lead->is(
                $secondLead
            )
        );

        Queue::assertNotPushed(
            DeliverLeadJob::class,
            fn (DeliverLeadJob $job): bool => $job->lead->is(
                $thirdLead
            ) || $job->lead->is($pendingLead)
        );

        $this->assertSame(
            Lead::STATUS_RETRYING,
            $firstLead->fresh()->status
        );
        $this->assertSame(
            Lead::STATUS_RETRYING,
            $secondLead->fresh()->status
        );
        $this->assertSame(
            Lead::STATUS_FAILED,
            $thirdLead->fresh()->status
        );
        $this->assertSame(
            Lead::STATUS_PENDING,
            $pendingLead->fresh()->status
        );
    }

    private function createFailedLead(string $email): Lead
    {
        $lead = $this->createLead($email);

        $lead->transitionTo(Lead::STATUS_PROCESSING);
        $lead->transitionTo(Lead::STATUS_FAILED);

        return $lead;
    }

    private function createLead(string $email): Lead
    {
        return Lead::create([
            'type' => Lead::TYPE_CONTACT,
            'name' => 'Cliente Teste',
            'email' => $email,
            'company' => 'Empresa Exemplo',
            'company_registration' => null,
            'phone' => '+55 14 99999-0000',
            'sector' => 'Higiene e Limpeza',
            'message' => 'Solicito informacoes tecnicas.',
            'language' => 'pt',
            'source_page' => 'home',
        ]);
    }
}

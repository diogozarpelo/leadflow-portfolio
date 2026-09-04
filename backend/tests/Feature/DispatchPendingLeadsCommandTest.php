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

class DispatchPendingLeadsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_counts_pending_leads_without_dispatching(): void
    {
        Queue::fake();

        $this->createLead('primeiro@example.com');
        $this->createLead('segundo@example.com');

        $this->artisan('leads:dispatch-pending', [
            '--dry-run' => true,
        ])
            ->expectsOutput(
                '2 pending lead(s) would be dispatched.'
            )
            ->assertExitCode(Command::SUCCESS);

        Queue::assertNothingPushed();
    }

    public function test_it_rejects_an_invalid_limit(): void
    {
        Queue::fake();

        $this->artisan('leads:dispatch-pending', [
            '--limit' => 0,
        ])
            ->expectsOutput(
                'The limit must be an integer between 1 and 1000.'
            )
            ->assertExitCode(Command::INVALID);

        Queue::assertNothingPushed();
    }

    public function test_it_does_not_dispatch_when_delivery_is_disabled(): void
    {
        Queue::fake();

        config([
            'lead-delivery.enabled' => false,
            'lead-delivery.driver' => 'testing',
        ]);

        $this->createLead('cliente@example.com');

        $this->artisan('leads:dispatch-pending')
            ->expectsOutput('Lead delivery is disabled.')
            ->assertExitCode(Command::FAILURE);

        Queue::assertNothingPushed();
    }

    public function test_it_does_not_dispatch_with_the_null_driver(): void
    {
        Queue::fake();

        config([
            'lead-delivery.enabled' => true,
            'lead-delivery.driver' => 'null',
        ]);

        $this->createLead('cliente@example.com');

        $this->artisan('leads:dispatch-pending')
            ->expectsOutput(
                'Lead delivery driver is not configured.'
            )
            ->assertExitCode(Command::FAILURE);

        Queue::assertNothingPushed();
    }

    public function test_it_rejects_an_unresolvable_driver(): void
    {
        Queue::fake();

        config([
            'lead-delivery.enabled' => true,
            'lead-delivery.driver' => 'unsupported',
        ]);

        $this->createLead('cliente@example.com');

        $this->artisan('leads:dispatch-pending')
            ->expectsOutput(
                'The configured lead delivery driver could not be resolved.'
            )
            ->assertExitCode(Command::FAILURE);

        Queue::assertNothingPushed();
    }

    public function test_it_dispatches_only_pending_leads_up_to_the_limit(): void
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

        $firstLead = $this->createLead('primeiro@example.com');
        $secondLead = $this->createLead('segundo@example.com');
        $thirdLead = $this->createLead('terceiro@example.com');
        $sentLead = $this->createLead('enviado@example.com');

        $sentLead->transitionTo(Lead::STATUS_PROCESSING);
        $sentLead->transitionTo(Lead::STATUS_SENT);

        $this->artisan('leads:dispatch-pending', [
            '--limit' => 2,
        ])
            ->expectsOutput('2 pending lead(s) dispatched.')
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
            ) || $job->lead->is($sentLead)
        );
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

<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class LeadDeliveryStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'lead-delivery.enabled' => false,
            'lead-delivery.driver' => 'null',
            'queue.default' => 'database',
            'queue.connections.database.queue' => 'default',
        ]);
    }

    public function test_it_reports_an_empty_operational_status(): void
    {
        $this->artisan('leads:status')
            ->expectsOutputToContain('Delivery enabled: no')
            ->expectsOutputToContain('Delivery driver: null')
            ->expectsOutputToContain('Queue connection: database')
            ->expectsOutputToContain('Queue name: default')
            ->expectsTable(
                ['Lead status', 'Total'],
                [
                    [Lead::STATUS_PENDING, 0],
                    [Lead::STATUS_PROCESSING, 0],
                    [Lead::STATUS_RETRYING, 0],
                    [Lead::STATUS_SENT, 0],
                    [Lead::STATUS_FAILED, 0],
                ]
            )
            ->expectsTable(
                ['Attempt status', 'Total'],
                [
                    [LeadDeliveryAttempt::STATUS_PROCESSING, 0],
                    [LeadDeliveryAttempt::STATUS_SUCCEEDED, 0],
                    [LeadDeliveryAttempt::STATUS_FAILED, 0],
                ]
            )
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_it_reports_aggregate_lead_and_attempt_counts(): void
    {
        $pendingLead = $this->createLead(
            Lead::STATUS_PENDING,
            'pending@example.com'
        );

        $this->createLead(
            Lead::STATUS_PENDING,
            'pending-two@example.com'
        );

        $processingLead = $this->createLead(
            Lead::STATUS_PROCESSING,
            'processing@example.com'
        );

        $this->createLead(
            Lead::STATUS_RETRYING,
            'retrying@example.com'
        );

        $sentLead = $this->createLead(
            Lead::STATUS_SENT,
            'sent@example.com'
        );

        $failedLead = $this->createLead(
            Lead::STATUS_FAILED,
            'failed@example.com'
        );

        $this->createAttempt(
            $processingLead,
            LeadDeliveryAttempt::STATUS_PROCESSING,
            1
        );

        $this->createAttempt(
            $sentLead,
            LeadDeliveryAttempt::STATUS_SUCCEEDED,
            1
        );

        $this->createAttempt(
            $failedLead,
            LeadDeliveryAttempt::STATUS_FAILED,
            1
        );

        $this->createAttempt(
            $failedLead,
            LeadDeliveryAttempt::STATUS_FAILED,
            2
        );

        $this->assertNotNull($pendingLead->getKey());

        $this->artisan('leads:status')
            ->expectsTable(
                ['Lead status', 'Total'],
                [
                    [Lead::STATUS_PENDING, 2],
                    [Lead::STATUS_PROCESSING, 1],
                    [Lead::STATUS_RETRYING, 1],
                    [Lead::STATUS_SENT, 1],
                    [Lead::STATUS_FAILED, 1],
                ]
            )
            ->expectsTable(
                ['Attempt status', 'Total'],
                [
                    [LeadDeliveryAttempt::STATUS_PROCESSING, 1],
                    [LeadDeliveryAttempt::STATUS_SUCCEEDED, 1],
                    [LeadDeliveryAttempt::STATUS_FAILED, 2],
                ]
            )
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_it_does_not_expose_personal_data(): void
    {
        $this->createLead(
            Lead::STATUS_PENDING,
            'sensitive@example.com'
        );

        $exitCode = Artisan::call('leads:status');
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringNotContainsString(
            'Sensitive Person',
            $output
        );
        $this->assertStringNotContainsString(
            'sensitive@example.com',
            $output
        );
        $this->assertStringNotContainsString(
            'Sensitive Company',
            $output
        );
        $this->assertStringNotContainsString(
            '+55 14 99999-0000',
            $output
        );
        $this->assertStringNotContainsString(
            'Sensitive lead message',
            $output
        );
    }

    private function createLead(
        string $status,
        string $email
    ): Lead {
        $lead = Lead::create([
            'type' => Lead::TYPE_CONTACT,
            'name' => 'Sensitive Person',
            'email' => $email,
            'company' => 'Sensitive Company',
            'company_registration' => null,
            'phone' => '+55 14 99999-0000',
            'sector' => 'Testing',
            'location' => null,
            'quantity' => null,
            'message' => 'Sensitive lead message',
            'language' => 'pt',
            'source_page' => 'test',
        ]);

        if ($status !== Lead::STATUS_PENDING) {
            $lead->forceFill([
                'status' => $status,
            ])->save();
        }

        return $lead->refresh();
    }

    private function createAttempt(
        Lead $lead,
        string $status,
        int $attemptNumber
    ): LeadDeliveryAttempt {
        return $lead->deliveryAttempts()->create([
            'attempt_number' => $attemptNumber,
            'driver' => 'testing',
            'status' => $status,
            'http_status' => null,
            'external_id' => null,
            'error_code' => null,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => $status
                === LeadDeliveryAttempt::STATUS_PROCESSING
                    ? null
                    : now(),
        ]);
    }
}

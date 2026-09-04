<?php

namespace Tests\Feature;

use App\Contracts\LeadDeliveryDriver;
use App\Jobs\DeliverLeadJob;
use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use App\Services\LeadDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalCsvLeadDeliveryJobIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_delivers_a_lead_through_the_job_without_duplicate_processing(): void
    {
        Storage::fake('local');

        config([
            'lead-delivery.enabled' => true,
            'lead-delivery.driver' => 'local_csv',
            'lead-delivery.local_csv.disk' => 'local',
            'lead-delivery.local_csv.directory' => 'lead-delivery/job-integration-test',
        ]);

        $this->app->forgetInstance(
            LeadDeliveryDriver::class
        );

        $lead = Lead::create([
            'type' => Lead::TYPE_CONTACT,
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'company' => 'Empresa Exemplo',
            'company_registration' => null,
            'phone' => '+55 14 99999-0000',
            'sector' => 'Higiene e Limpeza',
            'message' => 'Solicito informacoes tecnicas.',
            'language' => 'pt',
            'source_page' => 'home',
        ]);

        $job = new DeliverLeadJob($lead);
        $job->withFakeQueueInteractions();

        $service = app(LeadDeliveryService::class);

        $job->handle($service);

        $path = "lead-delivery/job-integration-test/lead-{$lead->id}.csv";

        Storage::disk('local')->assertExists($path);

        $firstCsv = Storage::disk('local')->get($path);

        $job->handle($service);

        $lead->refresh();

        $attempt = $lead->deliveryAttempts()->sole();
        $files = Storage::disk('local')->allFiles(
            'lead-delivery/job-integration-test'
        );

        $this->assertSame(
            Lead::STATUS_SENT,
            $lead->status
        );

        $this->assertSame(
            LeadDeliveryAttempt::STATUS_SUCCEEDED,
            $attempt->status
        );

        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame('local_csv', $attempt->driver);
        $this->assertSame(
            "local-csv-{$lead->id}",
            $attempt->external_id
        );

        $this->assertSame(
            1,
            $lead->deliveryAttempts()->count()
        );

        $this->assertCount(1, $files);
        $this->assertSame($path, $files[0]);

        $this->assertSame(
            $firstCsv,
            Storage::disk('local')->get($path)
        );

        $this->assertStringContainsString(
            "{$lead->id},contact",
            $firstCsv
        );
    }
}

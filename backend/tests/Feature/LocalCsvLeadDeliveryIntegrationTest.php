<?php

namespace Tests\Feature;

use App\Contracts\LeadDeliveryDriver;
use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use App\Services\LeadDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalCsvLeadDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_delivers_a_lead_through_the_local_csv_driver(): void
    {
        Storage::fake('local');

        config([
            'lead-delivery.enabled' => true,
            'lead-delivery.driver' => 'local_csv',
            'lead-delivery.local_csv.disk' => 'local',
            'lead-delivery.local_csv.directory' => 'lead-delivery/integration-test',
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

        $this->assertSame(
            Lead::STATUS_PENDING,
            $lead->status
        );

        $service = app(LeadDeliveryService::class);

        $service->deliver($lead);

        $lead->refresh();

        $attempt = $lead->deliveryAttempts()->sole();
        $path = "lead-delivery/integration-test/lead-{$lead->id}.csv";

        Storage::disk('local')->assertExists($path);

        $csv = Storage::disk('local')->get($path);
        $lines = explode("\n", trim($csv));

        $header = str_getcsv(
            $lines[0],
            ',',
            '"',
            ''
        );

        $data = str_getcsv(
            $lines[1],
            ',',
            '"',
            ''
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
        $this->assertNull($attempt->http_status);
        $this->assertNotNull($attempt->started_at);
        $this->assertNotNull($attempt->finished_at);

        $this->assertSame([
            'internal_id',
            'type',
            'name',
            'email',
            'company',
            'company_registration',
            'phone',
            'sector',
            'location',
            'quantity',
            'message',
            'language',
            'source_page',
        ], $header);

        $this->assertSame([
            (string) $lead->id,
            Lead::TYPE_CONTACT,
            'Cliente Teste',
            'cliente@example.com',
            'Empresa Exemplo',
            '',
            '+55 14 99999-0000',
            'Higiene e Limpeza',
            '',
            '',
            'Solicito informacoes tecnicas.',
            'pt',
            'home',
        ], $data);
    }
}

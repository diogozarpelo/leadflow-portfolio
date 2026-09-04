<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadDeliveryAttempt;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeadDeliveryAttemptModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_a_delivery_attempt_with_relationships_and_casts(): void
    {
        $lead = $this->createLead();

        $attempt = $lead->deliveryAttempts()->create([
            'attempt_number' => 1,
            'driver' => 'testing',
            'status' => LeadDeliveryAttempt::STATUS_SUCCEEDED,
            'http_status' => 202,
            'external_id' => 'external-123',
            'error_code' => null,
            'error_message' => null,
            'started_at' => now()->subSecond(),
            'finished_at' => now(),
        ]);

        $this->assertInstanceOf(LeadDeliveryAttempt::class, $attempt);
        $this->assertTrue($attempt->lead->is($lead));
        $this->assertTrue(
            $lead->deliveryAttempts()->whereKey($attempt->id)->exists()
        );

        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame(202, $attempt->http_status);
        $this->assertInstanceOf(Carbon::class, $attempt->started_at);
        $this->assertInstanceOf(Carbon::class, $attempt->finished_at);

        $this->assertDatabaseHas('lead_delivery_attempts', [
            'id' => $attempt->id,
            'lead_id' => $lead->id,
            'attempt_number' => 1,
            'driver' => 'testing',
            'status' => LeadDeliveryAttempt::STATUS_SUCCEEDED,
            'http_status' => 202,
            'external_id' => 'external-123',
        ]);
    }

    public function test_attempt_number_must_be_unique_for_each_lead(): void
    {
        $lead = $this->createLead();

        $this->createProcessingAttempt($lead);

        $this->expectException(QueryException::class);

        $this->createProcessingAttempt($lead);
    }

    public function test_deleting_a_lead_also_deletes_its_delivery_attempts(): void
    {
        $lead = $this->createLead();
        $attempt = $this->createProcessingAttempt($lead);

        $lead->delete();

        $this->assertDatabaseMissing('lead_delivery_attempts', [
            'id' => $attempt->id,
        ]);
    }

    private function createLead(): Lead
    {
        return Lead::create([
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
    }

    private function createProcessingAttempt(Lead $lead): LeadDeliveryAttempt
    {
        return $lead->deliveryAttempts()->create([
            'attempt_number' => 1,
            'driver' => 'testing',
            'status' => LeadDeliveryAttempt::STATUS_PROCESSING,
            'http_status' => null,
            'external_id' => null,
            'error_code' => null,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);
    }
}

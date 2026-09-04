<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_a_contact_lead_with_pending_status(): void
    {
        $lead = $this->createLead();

        $this->assertSame(Lead::TYPE_CONTACT, $lead->type);
        $this->assertSame(Lead::STATUS_PENDING, $lead->status);
        $this->assertNull($lead->external_id);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'type' => Lead::TYPE_CONTACT,
            'status' => Lead::STATUS_PENDING,
            'email' => 'cliente@example.com',
            'source_page' => 'home',
        ]);
    }

    public function test_it_assigns_an_external_id_internally(): void
    {
        $lead = $this->createLead();

        $lead->assignExternalId('external-lead-001');

        $this->assertSame(
            'external-lead-001',
            $lead->fresh()->external_id
        );

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'external_id' => 'external-lead-001',
        ]);
    }

    public function test_it_resolves_a_lead_by_external_id(): void
    {
        $expectedLead = $this->createLead();

        $expectedLead->assignExternalId('external-lead-001');

        $resolvedLead = Lead::resolveByExternalId(
            'external-lead-001'
        );

        $this->assertTrue($expectedLead->is($resolvedLead));
    }

    public function test_it_rejects_an_unknown_external_id(): void
    {
        $this->expectException(
            ModelNotFoundException::class
        );

        Lead::resolveByExternalId('unknown-external-id');
    }

    public function test_it_rejects_duplicate_external_ids(): void
    {
        $firstLead = $this->createLead();
        $firstLead->assignExternalId('duplicate-external-id');

        $secondLead = $this->createLead();
        $secondLead->assignExternalId('duplicate-external-id');

        $this->expectException(
            MultipleRecordsFoundException::class
        );

        Lead::resolveByExternalId('duplicate-external-id');
    }

    public function test_external_id_is_not_mass_assignable(): void
    {
        $lead = new Lead;

        $this->assertFalse($lead->isFillable('external_id'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function allowedStatusTransitions(): array
    {
        return [
            'pending to processing' => [
                Lead::STATUS_PENDING,
                Lead::STATUS_PROCESSING,
            ],
            'processing to sent' => [
                Lead::STATUS_PROCESSING,
                Lead::STATUS_SENT,
            ],
            'processing to retrying' => [
                Lead::STATUS_PROCESSING,
                Lead::STATUS_RETRYING,
            ],
            'processing to failed' => [
                Lead::STATUS_PROCESSING,
                Lead::STATUS_FAILED,
            ],
            'retrying to processing' => [
                Lead::STATUS_RETRYING,
                Lead::STATUS_PROCESSING,
            ],
            'retrying to failed' => [
                Lead::STATUS_RETRYING,
                Lead::STATUS_FAILED,
            ],
            'failed to retrying' => [
                Lead::STATUS_FAILED,
                Lead::STATUS_RETRYING,
            ],
        ];
    }

    #[DataProvider('allowedStatusTransitions')]
    public function test_it_performs_an_allowed_status_transition(
        string $currentStatus,
        string $nextStatus
    ): void {
        $lead = $this->createLead();

        $lead->forceFill([
            'status' => $currentStatus,
        ])->save();

        $lead->transitionTo($nextStatus);

        $this->assertSame($nextStatus, $lead->fresh()->status);
    }

    public function test_it_rejects_an_invalid_status_transition(): void
    {
        $lead = $this->createLead();

        try {
            $lead->transitionTo(Lead::STATUS_SENT);

            $this->fail('An invalid status transition was accepted.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Invalid lead status transition from pending to sent.',
                $exception->getMessage()
            );
        }

        $this->assertSame(Lead::STATUS_PENDING, $lead->fresh()->status);
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
}

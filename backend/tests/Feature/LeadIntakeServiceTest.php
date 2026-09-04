<?php

namespace Tests\Feature;

use App\Jobs\DeliverLeadJob;
use App\Models\Lead;
use App\Services\LeadIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadIntakeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_pending_lead_without_dispatching_when_disabled(): void
    {
        Queue::fake();

        $service = app(LeadIntakeService::class);

        $lead = $service->create($this->attributes());

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertSame(Lead::STATUS_PENDING, $lead->status);
        $this->assertSame('Cliente do Serviço', $lead->name);
        $this->assertSame('servico@example.com', $lead->email);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'email' => 'servico@example.com',
            'status' => Lead::STATUS_PENDING,
        ]);

        Queue::assertNotPushed(DeliverLeadJob::class);
    }

    public function test_it_does_not_dispatch_when_enabled_with_the_null_driver(): void
    {
        Queue::fake();

        config([
            'lead-delivery.enabled' => true,
            'lead-delivery.driver' => 'null',
        ]);

        $service = app(LeadIntakeService::class);

        $service->create($this->attributes());

        Queue::assertNotPushed(DeliverLeadJob::class);
    }

    public function test_it_dispatches_when_enabled_and_driver_is_configured(): void
    {
        Queue::fake();

        config([
            'lead-delivery.enabled' => true,
            'lead-delivery.driver' => 'testing',
        ]);

        $service = app(LeadIntakeService::class);

        $lead = $service->create($this->attributes());

        Queue::assertPushed(
            DeliverLeadJob::class,
            fn (DeliverLeadJob $job): bool => $job->lead->is($lead)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(): array
    {
        return [
            'type' => Lead::TYPE_CONTACT,
            'name' => '  Cliente do Serviço  ',
            'email' => 'SERVICO@EXAMPLE.COM',
            'company' => 'Empresa Serviço',
            'company_registration' => null,
            'phone' => '+55 14 99999-0010',
            'sector' => 'Higiene e Limpeza',
            'location' => null,
            'quantity' => null,
            'message' => 'Gostaria de receber informações.',
            'language' => 'pt',
            'source_page' => 'home',
        ];
    }
}

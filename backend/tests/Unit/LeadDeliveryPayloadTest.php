<?php

namespace Tests\Unit;

use App\Data\LeadDeliveryPayload;
use App\Models\Lead;
use PHPUnit\Framework\TestCase;

class LeadDeliveryPayloadTest extends TestCase
{
    public function test_it_creates_a_payload_from_a_lead(): void
    {
        $lead = new Lead;

        $lead->forceFill([
            'id' => 42,
            'type' => Lead::TYPE_SAMPLE_REQUEST,
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'company' => 'Empresa Exemplo',
            'company_registration' => '12.345.678/0001-90',
            'phone' => '+55 14 99999-0000',
            'sector' => 'Higiene e Limpeza',
            'location' => 'Bauru / SP',
            'quantity' => '1 tonelada',
            'message' => 'Solicitação de amostra.',
            'language' => 'pt',
            'source_page' => 'home',
        ]);

        $payload = LeadDeliveryPayload::fromLead($lead);

        $this->assertSame(42, $payload->internalId);
        $this->assertSame(Lead::TYPE_SAMPLE_REQUEST, $payload->type);
        $this->assertSame('Cliente Teste', $payload->name);
        $this->assertSame('cliente@example.com', $payload->email);
        $this->assertSame('Empresa Exemplo', $payload->company);
        $this->assertSame(
            '12.345.678/0001-90',
            $payload->companyRegistration
        );
        $this->assertSame('+55 14 99999-0000', $payload->phone);
        $this->assertSame('Higiene e Limpeza', $payload->sector);
        $this->assertSame('Bauru / SP', $payload->location);
        $this->assertSame('1 tonelada', $payload->quantity);
        $this->assertSame(
            'Solicitação de amostra.',
            $payload->message
        );
        $this->assertSame('pt', $payload->language);
        $this->assertSame('home', $payload->sourcePage);
        $this->assertSame([
            'internal_id' => 42,
            'type' => Lead::TYPE_SAMPLE_REQUEST,
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'company' => 'Empresa Exemplo',
            'company_registration' => '12.345.678/0001-90',
            'phone' => '+55 14 99999-0000',
            'sector' => 'Higiene e Limpeza',
            'location' => 'Bauru / SP',
            'quantity' => '1 tonelada',
            'message' => 'Solicitação de amostra.',
            'language' => 'pt',
            'source_page' => 'home',
        ], $payload->toArray());
    }
}

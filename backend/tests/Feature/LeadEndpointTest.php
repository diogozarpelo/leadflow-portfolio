<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_receives_a_contact_lead(): void
    {
        $response = $this->postJson('/api/leads', [
            'type' => Lead::TYPE_CONTACT,
            'name' => 'Cliente Contato',
            'email' => 'contato@example.com',
            'company' => 'Empresa Contato',
            'company_registration' => null,
            'phone' => '+55 14 99999-0001',
            'sector' => 'Higiene e Limpeza',
            'message' => 'Gostaria de receber informações comerciais.',
            'language' => 'pt',
            'source_page' => 'home',
            'status' => 'sent',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Lead received successfully.')
            ->assertJsonPath('lead.status', Lead::STATUS_PENDING)
            ->assertJsonStructure([
                'lead' => ['id', 'status'],
            ]);

        $this->assertDatabaseHas('leads', [
            'type' => Lead::TYPE_CONTACT,
            'status' => Lead::STATUS_PENDING,
            'email' => 'contato@example.com',
            'sector' => 'Higiene e Limpeza',
        ]);

        $this->assertDatabaseMissing('leads', [
            'status' => 'sent',
        ]);
    }

    public function test_it_receives_a_sample_request(): void
    {
        $response = $this->postJson('/api/leads', [
            'type' => Lead::TYPE_SAMPLE_REQUEST,
            'name' => 'Cliente Amostra',
            'email' => 'amostra@example.com',
            'company' => 'Empresa Amostra',
            'company_registration' => '12.345.678/0001-90',
            'phone' => '+55 19 99999-0002',
            'location' => 'São Paulo / SP / Brasil',
            'message' => 'Solicito uma amostra técnica de SLES 70%.',
            'language' => 'pt',
            'source_page' => 'blog',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('lead.status', Lead::STATUS_PENDING);

        $this->assertDatabaseHas('leads', [
            'type' => Lead::TYPE_SAMPLE_REQUEST,
            'email' => 'amostra@example.com',
            'location' => 'São Paulo / SP / Brasil',
            'source_page' => 'blog',
        ]);
    }

    public function test_it_receives_a_whatsapp_lead(): void
    {
        $response = $this->postJson('/api/leads', [
            'type' => Lead::TYPE_WHATSAPP,
            'name' => 'Cliente WhatsApp',
            'email' => 'whatsapp@example.com',
            'company' => 'Empresa WhatsApp',
            'company_registration' => '98.765.432/0001-10',
            'phone' => '+55 11 99999-0003',
            'quantity' => '1 tonelada',
            'language' => 'es',
            'source_page' => 'home',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('lead.status', Lead::STATUS_PENDING);

        $this->assertDatabaseHas('leads', [
            'type' => Lead::TYPE_WHATSAPP,
            'email' => 'whatsapp@example.com',
            'quantity' => '1 tonelada',
            'language' => 'es',
        ]);
    }

    public function test_it_rejects_an_invalid_whatsapp_lead(): void
    {
        $response = $this->postJson('/api/leads', [
            'type' => Lead::TYPE_WHATSAPP,
            'name' => '',
            'email' => 'email-invalido',
            'company' => '',
            'company_registration' => '',
            'phone' => '',
            'language' => 'fr',
            'source_page' => 'unknown',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'company',
                'company_registration',
                'phone',
                'quantity',
                'language',
                'source_page',
            ]);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_it_rate_limits_repeated_lead_submissions(): void
    {
        $payload = [
            'type' => Lead::TYPE_CONTACT,
            'name' => 'Teste de Limite',
            'email' => 'limite@example.com',
            'company' => 'Empresa Limite',
            'company_registration' => null,
            'phone' => '+55 14 99999-0004',
            'sector' => 'Higiene e Limpeza',
            'message' => 'Teste do limite de solicitações.',
            'language' => 'pt',
            'source_page' => 'home',
        ];

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/leads', $payload)
                ->assertCreated();
        }

        $this->postJson('/api/leads', $payload)
            ->assertStatus(429);

        $this->assertDatabaseCount('leads', 5);
    }

    public function test_it_rejects_a_bot_that_fills_the_honeypot(): void
    {
        $payload = [
            'type' => Lead::TYPE_CONTACT,
            'name' => 'Robô de Spam',
            'email' => 'robo@example.com',
            'company' => 'Empresa Spam',
            'company_registration' => null,
            'phone' => '+55 14 99999-0005',
            'sector' => 'Higiene e Limpeza',
            'message' => 'Mensagem automática.',
            'language' => 'pt',
            'source_page' => 'home',
            'website' => 'https://spam.example',
        ];

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.20',
            ])
            ->postJson('/api/leads', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['website']);

        $this->assertDatabaseCount('leads', 0);
    }
}

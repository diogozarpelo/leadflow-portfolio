<?php

namespace Tests\Unit;

use App\Data\LeadDeliveryPayload;
use App\Services\LeadDelivery\LeadDeliveryCsvEncoder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LeadDeliveryCsvEncoderTest extends TestCase
{
    public function test_it_encodes_a_payload_with_header_and_escaped_values(): void
    {
        $payload = new LeadDeliveryPayload(
            internalId: 42,
            type: 'contact',
            name: 'Cliente,Teste',
            email: 'cliente@example.com',
            company: 'Empresa "Exemplo"',
            companyRegistration: null,
            phone: '5514999990000',
            sector: null,
            location: null,
            quantity: null,
            message: 'Solicitação',
            language: 'pt',
            sourcePage: 'home',
        );

        $csv = (new LeadDeliveryCsvEncoder)->encode($payload);

        $expected = implode("\n", [
            'internal_id,type,name,email,company,company_registration,phone,sector,location,quantity,message,language,source_page',
            '42,contact,"Cliente,Teste",cliente@example.com,"Empresa ""Exemplo""",,5514999990000,,,,Solicitação,pt,home',
            '',
        ]);

        $this->assertSame($expected, $csv);
    }

    public function test_it_supports_a_configurable_delimiter(): void
    {
        $payload = new LeadDeliveryPayload(
            internalId: 7,
            type: 'contact',
            name: 'Cliente',
            email: 'cliente@example.com',
            company: 'Empresa',
            companyRegistration: null,
            phone: '5514999990000',
            sector: null,
            location: null,
            quantity: null,
            message: null,
            language: 'pt',
            sourcePage: 'home',
        );

        $csv = (new LeadDeliveryCsvEncoder)->encode(
            $payload,
            delimiter: ';'
        );

        $this->assertStringStartsWith(
            'internal_id;type;name;email;company;',
            $csv
        );

        $this->assertStringContainsString(
            "\n7;contact;Cliente;cliente@example.com;Empresa;",
            $csv
        );
    }

    public function test_it_preserves_line_breaks_inside_an_escaped_field(): void
    {
        $payload = new LeadDeliveryPayload(
            internalId: 8,
            type: 'contact',
            name: 'Cliente',
            email: 'cliente@example.com',
            company: 'Empresa',
            companyRegistration: null,
            phone: '5514999990000',
            sector: null,
            location: null,
            quantity: null,
            message: "Linha 1\nLinha 2",
            language: 'pt',
            sourcePage: 'home',
        );

        $csv = (new LeadDeliveryCsvEncoder)->encode($payload);

        $expectedCell = '"'."Linha 1\nLinha 2".'"';

        $this->assertStringContainsString(
            $expectedCell,
            $csv
        );
    }

    public function test_it_rejects_an_invalid_delimiter(): void
    {
        $payload = new LeadDeliveryPayload(
            internalId: 9,
            type: 'contact',
            name: 'Cliente',
            email: 'cliente@example.com',
            company: 'Empresa',
            companyRegistration: null,
            phone: '5514999990000',
            sector: null,
            location: null,
            quantity: null,
            message: null,
            language: 'pt',
            sourcePage: 'home',
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        (new LeadDeliveryCsvEncoder)->encode(
            $payload,
            delimiter: '::'
        );
    }
}

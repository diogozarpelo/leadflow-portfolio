<?php

namespace Tests\Unit;

use App\Contracts\LeadDeliveryDriver;
use App\Data\LeadDeliveryPayload;
use App\Data\LeadDeliveryResult;
use App\Exceptions\LeadDeliveryException;
use App\Services\LeadDelivery\NullLeadDeliveryDriver;
use PHPUnit\Framework\TestCase;

class LeadDeliveryDriverTest extends TestCase
{
    public function test_null_driver_implements_the_delivery_contract(): void
    {
        $driver = new NullLeadDeliveryDriver;

        $this->assertInstanceOf(LeadDeliveryDriver::class, $driver);
        $this->assertSame('null', $driver->name());
    }

    public function test_delivery_result_exposes_normalized_metadata(): void
    {
        $result = new LeadDeliveryResult(
            externalId: 'external-123',
            httpStatus: 202,
        );

        $this->assertSame('external-123', $result->externalId);
        $this->assertSame(202, $result->httpStatus);
    }

    public function test_null_driver_reports_a_non_retryable_configuration_failure(): void
    {
        $driver = new NullLeadDeliveryDriver;

        try {
            $driver->deliver($this->payload());

            $this->fail('The null driver accepted a delivery.');
        } catch (LeadDeliveryException $exception) {
            $this->assertSame(
                'Lead delivery is not configured.',
                $exception->getMessage()
            );
            $this->assertFalse($exception->retryable);
            $this->assertNull($exception->httpStatus);
            $this->assertSame(
                'delivery_not_configured',
                $exception->errorCode
            );
        }
    }

    private function payload(): LeadDeliveryPayload
    {
        return new LeadDeliveryPayload(
            internalId: 1,
            type: 'contact',
            name: 'Cliente Teste',
            email: 'cliente@example.com',
            company: 'Empresa Exemplo',
            companyRegistration: null,
            phone: '+55 14 99999-0000',
            sector: 'Higiene e Limpeza',
            location: null,
            quantity: null,
            message: 'Solicitação de contato.',
            language: 'pt',
            sourcePage: 'home',
        );
    }
}

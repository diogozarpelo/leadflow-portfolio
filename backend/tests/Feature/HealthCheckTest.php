<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_api_root_returns_service_information(): void
    {
        $response = $this->getJson('/');

        $response
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'leadflow-backend',
                'message' => 'LeadFlow Industrial Backend API',
                'health' => '/api/health',
            ]);
    }

    public function test_health_endpoint_returns_successful_response(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'leadflow-backend',
            ])
            ->assertJsonStructure([
                'timestamp',
            ]);
    }
}

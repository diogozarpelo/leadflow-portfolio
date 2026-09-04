<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_it_allows_the_configured_origins(): void
    {
        $allowedOrigins = [
            'https://leadflow.example',
            'https://www.leadflow.example',
            'http://127.0.0.1:5500',
            'http://localhost:5500',
        ];

        foreach ($allowedOrigins as $origin) {
            $response = $this
                ->withHeaders([
                    'Origin' => $origin,
                    'Access-Control-Request-Method' => 'POST',
                    'Access-Control-Request-Headers' => 'content-type',
                ])
                ->options('/api/leads');

            $response
                ->assertSuccessful()
                ->assertHeader('Access-Control-Allow-Origin', $origin);
        }
    }

    public function test_it_does_not_allow_an_unknown_origin(): void
    {
        $response = $this
            ->withHeaders([
                'Origin' => 'https://site-desconhecido.example',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'content-type',
            ])
            ->options('/api/leads');

        $response
            ->assertSuccessful()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}

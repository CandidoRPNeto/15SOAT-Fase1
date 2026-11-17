<?php

namespace Tests\Feature;

use App\Http\Middleware\AssignCorrelationId;
use Tests\TestCase;

class CorrelationIdTest extends TestCase
{
    public function test_response_carries_a_correlation_id_header(): void
    {
        $response = $this->getJson('/up');

        $response->assertHeader(AssignCorrelationId::HEADER);
    }

    public function test_response_reuses_the_gateway_supplied_correlation_id(): void
    {
        $response = $this->withHeader(AssignCorrelationId::HEADER, 'gateway-abc-123')
            ->getJson('/up');

        $response->assertHeader(AssignCorrelationId::HEADER, 'gateway-abc-123');
    }
}

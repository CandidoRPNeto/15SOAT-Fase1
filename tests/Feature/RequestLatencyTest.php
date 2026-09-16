<?php

namespace Tests\Feature;

use Tests\TestCase;

class RequestLatencyTest extends TestCase
{
    public function test_response_carries_a_non_negative_response_time_header(): void
    {
        $response = $this->getJson('/up');

        $response->assertHeader('X-Response-Time-Ms');

        $durationMs = (int) $response->headers->get('X-Response-Time-Ms');
        $this->assertGreaterThanOrEqual(0, $durationMs);
    }
}

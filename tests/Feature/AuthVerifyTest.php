<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_identity_headers_for_a_client(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->getJson('/api/v1/auth/verify')
            ->assertOk()
            ->assertHeader('X-User-Id', (string) $client->id)
            ->assertHeader('X-User-Role', 'client')
            ->assertHeader('X-User-Client-Id', (string) $client->id);
    }

    public function test_staff_has_empty_client_id_header(): void
    {
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)->getJson('/api/v1/auth/verify')
            ->assertOk()
            ->assertHeader('X-User-Role', 'mechanic')
            ->assertHeader('X-User-Client-Id', '');
    }

    public function test_rejects_missing_token(): void
    {
        $this->getJson('/api/v1/auth/verify')->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalCpfLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.lambda_auth.internal_api_key' => 'test-internal-key']);
    }

    public function test_rejects_request_without_the_internal_api_key(): void
    {
        $response = $this->postJson('/internal/clients/cpf-lookup', ['cpf_cnpj' => '123.456.789-00']);

        $response->assertStatus(401);
    }

    public function test_rejects_request_with_the_wrong_internal_api_key(): void
    {
        $response = $this->withHeader('X-Internal-Api-Key', 'wrong-key')
            ->postJson('/internal/clients/cpf-lookup', ['cpf_cnpj' => '123.456.789-00']);

        $response->assertStatus(401);
    }

    public function test_finds_a_client_by_cpf_regardless_of_punctuation(): void
    {
        $client = User::factory()->client()->create(['cpf_cnpj' => '123.456.789-00']);

        // Lambda manda só dígitos; a coluna guarda formatado — o lookup
        // precisa casar os dois.
        $response = $this->withHeader('X-Internal-Api-Key', 'test-internal-key')
            ->postJson('/internal/clients/cpf-lookup', ['cpf_cnpj' => '12345678900']);

        $response->assertOk()->assertJson([
            'exists' => true,
            'user_id' => $client->id,
            'status' => 'active',
        ]);
    }

    public function test_returns_blocked_status_for_a_blocked_client(): void
    {
        User::factory()->client()->blocked()->create(['cpf_cnpj' => '111.222.333-44']);

        $response = $this->withHeader('X-Internal-Api-Key', 'test-internal-key')
            ->postJson('/internal/clients/cpf-lookup', ['cpf_cnpj' => '111.222.333-44']);

        $response->assertOk()->assertJson(['status' => 'blocked']);
    }

    public function test_returns_404_when_cpf_does_not_match_any_client(): void
    {
        $response = $this->withHeader('X-Internal-Api-Key', 'test-internal-key')
            ->postJson('/internal/clients/cpf-lookup', ['cpf_cnpj' => '000.000.000-00']);

        $response->assertStatus(404)->assertJson(['exists' => false]);
    }

    public function test_does_not_match_a_receptionist_or_mechanic_by_cpf(): void
    {
        // cpf_cnpj é preenchível pra qualquer role (users table), mas o
        // lookup é escopado a role=client de propósito — o Lambda só
        // autentica clientes.
        User::factory()->receptionist()->create(['cpf_cnpj' => '999.888.777-66']);

        $response = $this->withHeader('X-Internal-Api-Key', 'test-internal-key')
            ->postJson('/internal/clients/cpf-lookup', ['cpf_cnpj' => '999.888.777-66']);

        $response->assertStatus(404);
    }
}

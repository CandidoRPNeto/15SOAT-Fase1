<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->receptionist = User::factory()->receptionist()->create();
    }

    public function test_receptionist_can_list_clients(): void
    {
        User::factory()->client()->count(3)->create();

        $this->actingAs($this->receptionist)
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_receptionist_can_create_client(): void
    {
        $this->actingAs($this->receptionist)
            ->postJson('/api/v1/clients', [
                'name' => 'João Teste',
                'email' => 'joao@test.com',
                'password' => 'password123',
                'cpf_cnpj' => '111.222.333-44',
                'phone' => '(11) 99999-0000',
            ])
            ->assertCreated()
            ->assertJsonPath('role', 'client');
    }

    public function test_client_cannot_access_clients_list(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->getJson('/api/v1/clients')
            ->assertForbidden();
    }

    public function test_receptionist_can_search_client_by_cpf(): void
    {
        User::factory()->client()->create(['cpf_cnpj' => '999.888.777-66']);
        User::factory()->client()->count(2)->create();

        $this->actingAs($this->receptionist)
            ->getJson('/api/v1/clients?cpf_cnpj=999.888.777-66')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_receptionist_can_update_client(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($this->receptionist)
            ->putJson("/api/v1/clients/{$client->id}", ['name' => 'Nome Atualizado'])
            ->assertOk()
            ->assertJsonPath('name', 'Nome Atualizado');
    }

    public function test_receptionist_can_delete_client(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($this->receptionist)
            ->deleteJson("/api/v1/clients/{$client->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $client->id]);
    }

    public function test_unauthenticated_cannot_access_clients(): void
    {
        $this->getJson('/api/v1/clients')->assertUnauthorized();
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->receptionist)
            ->postJson('/api/v1/clients', [])
            ->assertUnprocessable();
    }
}

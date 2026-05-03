<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    private User $mechanic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mechanic = User::factory()->mechanic()->create();
    }

    public function test_mechanic_can_list_vehicles(): void
    {
        Vehicle::factory()->count(3)->create();

        $this->actingAs($this->mechanic)
            ->getJson('/api/v1/vehicles')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_mechanic_can_create_vehicle(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($this->mechanic)
            ->postJson('/api/v1/vehicles', [
                'client_id' => $client->id,
                'plate' => 'ABC-1234',
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2022,
                'color' => 'Prata',
            ])
            ->assertCreated()
            ->assertJsonPath('plate', 'ABC-1234');
    }

    public function test_mechanic_can_update_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->mechanic)
            ->putJson("/api/v1/vehicles/{$vehicle->id}", ['color' => 'Azul'])
            ->assertOk()
            ->assertJsonPath('color', 'Azul');
    }

    public function test_mechanic_can_delete_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->mechanic)
            ->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertNoContent();
    }

    public function test_client_cannot_manage_vehicles(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->getJson('/api/v1/vehicles')
            ->assertForbidden();
    }

    public function test_plate_must_be_unique(): void
    {
        Vehicle::factory()->create(['plate' => 'XYZ-9999']);
        $client = User::factory()->client()->create();

        $this->actingAs($this->mechanic)
            ->postJson('/api/v1/vehicles', [
                'client_id' => $client->id,
                'plate' => 'XYZ-9999',
                'brand' => 'Honda',
                'model' => 'Civic',
                'year' => 2021,
            ])
            ->assertUnprocessable();
    }
}

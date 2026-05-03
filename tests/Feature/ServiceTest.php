<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $mechanic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mechanic = User::factory()->mechanic()->create();
    }

    public function test_mechanic_can_list_services(): void
    {
        Service::factory()->count(4)->create();

        $this->actingAs($this->mechanic)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonPath('meta.total', 4);
    }

    public function test_mechanic_can_create_service(): void
    {
        $this->actingAs($this->mechanic)
            ->postJson('/api/v1/services', [
                'name' => 'Troca de óleo',
                'price' => 120.00,
                'avg_execution_minutes' => 60,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Troca de óleo');
    }

    public function test_mechanic_can_update_service(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->mechanic)
            ->putJson("/api/v1/services/{$service->id}", ['price' => 150.00])
            ->assertOk()
            ->assertJsonPath('price', '150.00');
    }

    public function test_mechanic_can_delete_service(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->mechanic)
            ->deleteJson("/api/v1/services/{$service->id}")
            ->assertNoContent();
    }

    public function test_filter_active_services(): void
    {
        Service::factory()->count(3)->create(['active' => true]);
        Service::factory()->count(2)->inactive()->create();

        $this->actingAs($this->mechanic)
            ->getJson('/api/v1/services?active=true')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }
}

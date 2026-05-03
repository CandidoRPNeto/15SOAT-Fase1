<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartTest extends TestCase
{
    use RefreshDatabase;

    private User $mechanic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mechanic = User::factory()->mechanic()->create();
    }

    public function test_mechanic_can_list_parts(): void
    {
        Part::factory()->count(5)->create();

        $this->actingAs($this->mechanic)
            ->getJson('/api/v1/parts')
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    }

    public function test_mechanic_can_create_part(): void
    {
        $this->actingAs($this->mechanic)
            ->postJson('/api/v1/parts', [
                'name' => 'Filtro de óleo',
                'price' => 35.00,
                'stock_quantity' => 50,
                'part_number' => 'FO-001',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Filtro de óleo')
            ->assertJsonPath('stock_quantity', 50);
    }

    public function test_mechanic_can_update_stock(): void
    {
        $part = Part::factory()->create(['stock_quantity' => 10]);

        $this->actingAs($this->mechanic)
            ->putJson("/api/v1/parts/{$part->id}", ['stock_quantity' => 25])
            ->assertOk()
            ->assertJsonPath('stock_quantity', 25);
    }

    public function test_filter_low_stock(): void
    {
        Part::factory()->count(2)->create(['stock_quantity' => 2]);
        Part::factory()->count(3)->create(['stock_quantity' => 20]);

        $this->actingAs($this->mechanic)
            ->getJson('/api/v1/parts?low_stock=true')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_mechanic_can_delete_part(): void
    {
        $part = Part::factory()->create();

        $this->actingAs($this->mechanic)
            ->deleteJson("/api/v1/parts/{$part->id}")
            ->assertNoContent();
    }
}

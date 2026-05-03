<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    private User $mechanic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mechanic = User::factory()->mechanic()->create();
    }

    public function test_mechanic_can_list_items(): void
    {
        Item::factory()->count(5)->create();

        $this->actingAs($this->mechanic)
            ->getJson('/api/v1/items')
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    }

    public function test_mechanic_can_filter_items_by_type(): void
    {
        Item::factory()->count(3)->supply()->create();
        Item::factory()->count(2)->part()->create();

        $this->actingAs($this->mechanic)
            ->getJson('/api/v1/items?type=insumo')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_mechanic_can_create_item_as_supply(): void
    {
        $this->actingAs($this->mechanic)
            ->postJson('/api/v1/items', [
                'name' => 'Óleo 5W30',
                'price' => 32.00,
                'stock_quantity' => 100,
                'type' => 'insumo',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Óleo 5W30')
            ->assertJsonPath('type', 'insumo')
            ->assertJsonPath('stock_quantity', 100);
    }

    public function test_mechanic_can_create_item_as_part(): void
    {
        $this->actingAs($this->mechanic)
            ->postJson('/api/v1/items', [
                'name' => 'Filtro de óleo',
                'price' => 35.00,
                'stock_quantity' => 50,
                'part_number' => 'FO-001',
                'type' => 'peca',
            ])
            ->assertCreated()
            ->assertJsonPath('type', 'peca')
            ->assertJsonPath('type_label', 'Peça');
    }

    public function test_store_fails_without_type(): void
    {
        $this->actingAs($this->mechanic)
            ->postJson('/api/v1/items', [
                'name' => 'Filtro de óleo',
                'price' => 35.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_fails_with_invalid_type(): void
    {
        $this->actingAs($this->mechanic)
            ->postJson('/api/v1/items', [
                'name' => 'Filtro',
                'price' => 10.00,
                'type' => 'componente',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_mechanic_can_update_stock(): void
    {
        $item = Item::factory()->create(['stock_quantity' => 10]);

        $this->actingAs($this->mechanic)
            ->putJson("/api/v1/items/{$item->id}", ['stock_quantity' => 25])
            ->assertOk()
            ->assertJsonPath('stock_quantity', 25);
    }

    public function test_mechanic_can_update_type(): void
    {
        $item = Item::factory()->part()->create();

        $this->actingAs($this->mechanic)
            ->putJson("/api/v1/items/{$item->id}", ['type' => 'insumo'])
            ->assertOk()
            ->assertJsonPath('type', 'insumo');
    }

    public function test_filter_low_stock(): void
    {
        Item::factory()->count(2)->create(['stock_quantity' => 2]);
        Item::factory()->count(3)->create(['stock_quantity' => 20]);

        $this->actingAs($this->mechanic)
            ->getJson('/api/v1/items?low_stock=true')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_mechanic_can_delete_item(): void
    {
        $item = Item::factory()->create();

        $this->actingAs($this->mechanic)
            ->deleteJson("/api/v1/items/{$item->id}")
            ->assertNoContent();
    }

    public function test_client_cannot_access_items(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->getJson('/api/v1/items')
            ->assertForbidden();
    }
}

<?php

namespace Database\Factories;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'part_number' => strtoupper(fake()->unique()->bothify('??-####-??')),
            'price' => fake()->randomFloat(2, 10, 500),
            'stock_quantity' => fake()->numberBetween(1, 100),
            'active' => true,
            'type' => fake()->randomElement([ItemType::SUPPLY, ItemType::PART]),
        ];
    }

    public function supply(): static
    {
        return $this->state(['type' => ItemType::SUPPLY]);
    }

    public function part(): static
    {
        return $this->state(['type' => ItemType::PART]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock_quantity' => 0]);
    }
}

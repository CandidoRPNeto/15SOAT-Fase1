<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $services = [
            ['Troca de óleo', 60],
            ['Alinhamento', 45],
            ['Balanceamento', 30],
            ['Troca de pneu', 20],
            ['Revisão completa', 240],
            ['Troca de freios', 90],
            ['Diagnóstico eletrônico', 60],
            ['Troca de filtro de ar', 15],
        ];

        [$name, $minutes] = fake()->randomElement($services);

        return [
            'name' => $name . ' ' . fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 50, 800),
            'avg_execution_minutes' => $minutes,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => User::factory()->client(),
            'plate' => strtoupper(fake()->bothify('???-####')),
            'brand' => fake()->randomElement(['Toyota', 'Honda', 'Volkswagen', 'Chevrolet', 'Ford', 'Hyundai', 'Fiat', 'Renault']),
            'model' => fake()->randomElement(['Corolla', 'Civic', 'Gol', 'Onix', 'Ka', 'HB20', 'Uno', 'Sandero']),
            'year' => fake()->numberBetween(2010, 2024),
            'color' => fake()->randomElement(['Branco', 'Preto', 'Prata', 'Cinza', 'Vermelho', 'Azul']),
        ];
    }
}

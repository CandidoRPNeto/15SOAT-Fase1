<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::CLIENT,
            // Explícito, não confiar no default da migration — instância
            // Eloquent em memória não é repopulada a partir do default do
            // DB depois do create() (mesma pegadinha já documentada pro
            // status da OS, ver memória de padrões de teste do projeto).
            'status' => UserStatus::ACTIVE,
            'cpf_cnpj' => fake()->unique()->numerify('###.###.###-##'),
            'phone' => fake()->phoneNumber(),
            'remember_token' => Str::random(10),
        ];
    }

    public function receptionist(): static
    {
        return $this->state(['role' => UserRole::RECEPTIONIST]);
    }

    public function mechanic(): static
    {
        return $this->state(['role' => UserRole::MECHANIC]);
    }

    public function client(): static
    {
        return $this->state(['role' => UserRole::CLIENT]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function blocked(): static
    {
        return $this->state(['status' => UserStatus::BLOCKED]);
    }
}

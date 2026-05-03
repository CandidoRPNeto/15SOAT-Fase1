<?php

namespace Database\Factories;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    public function definition(): array
    {
        $client = User::factory()->client()->create();

        return [
            'client_id' => $client->id,
            'vehicle_id' => Vehicle::factory()->state(['client_id' => $client->id]),
            'status' => ServiceOrderStatus::RECEIVED,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function received(): static
    {
        return $this->state(['status' => ServiceOrderStatus::RECEIVED]);
    }

    public function awaitingApproval(): static
    {
        return $this->state([
            'status' => ServiceOrderStatus::AWAITING_APPROVAL,
            'total_amount' => fake()->randomFloat(2, 100, 2000),
            'budget_sent_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => ServiceOrderStatus::APPROVED,
            'total_amount' => fake()->randomFloat(2, 100, 2000),
        ]);
    }

    public function finalized(): static
    {
        return $this->state([
            'status' => ServiceOrderStatus::FINALIZED,
            'total_amount' => fake()->randomFloat(2, 100, 2000),
            'finalized_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => ServiceOrderStatus::DELIVERED,
            'total_amount' => fake()->randomFloat(2, 100, 2000),
            'paid_at' => now(),
            'finalized_at' => now()->subHours(2),
            'delivered_at' => now(),
        ]);
    }
}

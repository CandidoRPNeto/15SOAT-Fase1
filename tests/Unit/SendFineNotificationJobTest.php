<?php

namespace Tests\Unit;

use App\Contracts\MessagingServiceInterface;
use App\Jobs\SendFineNotificationJob;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendFineNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_notifies_overdue_orders(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);

        $overdueOrder = ServiceOrder::factory()->finalized()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'finalized_at' => now()->subHours(25),
            'paid_at' => null,
        ]);

        ServiceOrder::factory()->finalized()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'finalized_at' => now()->subHours(12),
            'paid_at' => null,
        ]);

        $messaging = Mockery::mock(MessagingServiceInterface::class);
        $messaging->shouldReceive('notifyPickupOverdue')
            ->once()
            ->with(Mockery::on(fn ($o) => $o->id === $overdueOrder->id));

        $job = new SendFineNotificationJob();
        $job->handle($messaging);
    }

    public function test_job_does_not_notify_paid_orders(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);

        ServiceOrder::factory()->finalized()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'finalized_at' => now()->subHours(25),
            'paid_at' => now(),
        ]);

        $messaging = Mockery::mock(MessagingServiceInterface::class);
        $messaging->shouldReceive('notifyPickupOverdue')->never();

        $job = new SendFineNotificationJob();
        $job->handle($messaging);
    }
}

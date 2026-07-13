<?php

namespace Tests\Unit;

use App\Application\ServiceOrder\OpenServiceOrder;
use App\Contracts\MessagingServiceInterface;
use App\Infrastructure\Persistence\Eloquent\EloquentServiceOrderRepository;
use App\Models\Item;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    private OpenServiceOrder $useCase;

    private User $client;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(MessagingServiceInterface::class, function ($mock) {
            $mock->shouldReceive('notifyOrderCreated')->andReturn(['success' => true, 'message_id' => 'MSG-1', 'message' => 'sent']);
        });

        $this->useCase = new OpenServiceOrder(
            new EloquentServiceOrderRepository,
            app(MessagingServiceInterface::class),
        );

        $this->client = User::factory()->client()->create();
        $this->vehicle = Vehicle::factory()->create(['client_id' => $this->client->id]);
    }

    public function test_creates_order_with_received_status(): void
    {
        $order = $this->useCase->execute([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->assertNotNull($order->id);
        $this->assertSame('received', $order->status->value);
    }

    public function test_attaches_manually_informed_items(): void
    {
        $item = Item::factory()->create(['price' => 40.00]);

        $order = $this->useCase->execute([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 2,
        ]);
    }

    public function test_attaches_service_and_auto_includes_its_catalog_items(): void
    {
        $service = Service::factory()->create(['price' => 150.00]);
        $item = Item::factory()->create(['price' => 25.00]);
        ServiceItem::create(['service_id' => $service->id, 'item_id' => $item->id, 'quantity' => 3]);

        $order = $this->useCase->execute([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
            'services' => [
                ['service_id' => $service->id],
            ],
        ]);

        $this->assertDatabaseHas('service_order_services', [
            'service_order_id' => $order->id,
            'service_id' => $service->id,
        ]);
        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 3,
        ]);
    }
}

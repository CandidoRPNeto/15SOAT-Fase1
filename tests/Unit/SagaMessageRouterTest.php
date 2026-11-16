<?php

namespace Tests\Unit;

use App\Application\Saga\SagaMessageRouter;
use App\Domain\Messaging\MessageEnvelope;
use App\Domain\Saga\SagaMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\SagaDriver;
use Tests\TestCase;

class SagaMessageRouterTest extends TestCase
{
    use RefreshDatabase;

    public function test_binds_to_billing_and_execution_events(): void
    {
        $this->assertSame(['evt.execution.*', 'evt.billing.*'], app(SagaMessageRouter::class)->bindings());
    }

    public function test_rejects_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported message type 'evt.unknown'");

        app(SagaMessageRouter::class)->route(MessageEnvelope::create('evt.unknown', 's', 'c', []));
    }

    public function test_rejects_commands_it_only_sends(): void
    {
        $order = (new SagaDriver)->openOrder();
        $sagaId = (new SagaDriver)->saga($order)->id;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is a command the OS Service sends');

        app(SagaMessageRouter::class)->route(MessageEnvelope::create(SagaMessage::CREATE_BUDGET->value, $sagaId, 'c', []));
    }
}

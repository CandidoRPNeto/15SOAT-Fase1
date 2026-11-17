<?php

namespace Tests\Unit;

use App\Application\Messaging\OutboxRecorder;
use App\Application\Ports\MessageConsumer;
use App\Application\Ports\MessagePublisher;
use App\Domain\Messaging\MessageEnvelope;
use App\Domain\Saga\SagaMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\InMemoryMessageConsumer;
use Tests\Fakes\InMemoryMessagePublisher;
use Tests\TestCase;

class BusWorkCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_relays_outbox_and_consumes_queue_for_given_iterations(): void
    {
        $publisher = new InMemoryMessagePublisher;
        $consumer = new InMemoryMessageConsumer;
        $this->app->instance(MessagePublisher::class, $publisher);
        $this->app->instance(MessageConsumer::class, $consumer);

        (new OutboxRecorder)->record(MessageEnvelope::create('cmd.execution.enqueue_diagnosis', 's', 'c', []));
        $consumer->queue[] = MessageEnvelope::create(SagaMessage::DIAGNOSIS_ENQUEUED->value, 'missing-saga', 'c', []);

        $this->artisan('bus:work', ['--iterations' => 2, '--poll' => 0])->assertSuccessful();

        $this->assertSame(['cmd.execution.enqueue_diagnosis'], $publisher->publishedTypes());
        $this->assertSame(2, $consumer->polls);
        // Unknown saga → handler throws → message dead-lettered, not lost.
        $this->assertCount(1, $consumer->deadLettered);
    }
}

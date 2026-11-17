#!/usr/bin/env bash
# Copies the per-repo messaging library (ADR-015) from this repo (the
# reference copy) into a sibling service repo. Re-run after changing any
# file below, then run that repo's tests.
#
# Usage: scripts/sync-messaging-lib.sh ../15SOAT-Fase1-billing [--no-eloquent]
#   --no-eloquent  skip Eloquent outbox/inbox models + migration (Mongo repos
#                  provide their own).
set -euo pipefail

target="${1:?usage: $0 <target-repo> [--no-eloquent]}"
eloquent=true
[[ "${2:-}" == "--no-eloquent" ]] && eloquent=false
[[ -f "$target/artisan" ]] || { echo "not a Laravel repo: $target" >&2; exit 1; }

src="$(cd "$(dirname "$0")/.." && pwd)"
files=(
  app/Domain/Messaging/MessageEnvelope.php
  app/Application/Ports/MessagePublisher.php
  app/Application/Ports/MessageConsumer.php
  app/Application/Ports/MessageRouter.php
  app/Application/Messaging/InboxProcessor.php
  app/Application/Messaging/OutboxRecorder.php
  app/Application/Messaging/OutboxRelay.php
  app/Infrastructure/Messaging/RabbitMq/RabbitMqConnectionFactory.php
  app/Infrastructure/Messaging/RabbitMq/RabbitMqMessagePublisher.php
  app/Infrastructure/Messaging/RabbitMq/RabbitMqMessageConsumer.php
  app/Application/Ports/TraceContext.php
  app/Infrastructure/Tracing/DatadogTraceContext.php
  app/Infrastructure/Tracing/NullTraceContext.php
  app/Infrastructure/Tracing/ServeCommandTracerEnv.php
  app/Console/Commands/BusWorkCommand.php
  app/Http/Middleware/AssignCorrelationId.php
  app/Http/Middleware/LogRequestLatency.php
  tests/Fakes/InMemoryMessagePublisher.php
  tests/Fakes/InMemoryMessageConsumer.php
  tests/Fakes/RecordingTraceContext.php
  tests/Unit/TraceContextAdapterTest.php
  tests/Fakes/AcknowledgingAmqpMessage.php
  tests/Unit/RabbitMqConsumerTraceTest.php
  tests/Unit/ServeCommandDatadogEnvTest.php
  tests/Unit/MessageEnvelopeTest.php
  tests/Unit/AssignCorrelationIdTest.php
  tests/Feature/RabbitMqRoundTripTest.php
)
if $eloquent; then
  files+=(
    app/Models/OutboxMessage.php
    app/Models/ProcessedMessage.php
    database/migrations/2026_11_16_000001_create_messaging_tables.php
    database/migrations/2026_11_17_000001_add_trace_context_to_outbox_messages.php
    tests/Unit/OutboxTraceContextTest.php
    tests/Unit/OutboxRelayTest.php
    tests/Unit/InboxProcessorTest.php
  )
fi

for f in "${files[@]}"; do
  mkdir -p "$target/$(dirname "$f")"
  cp "$src/$f" "$target/$f"
  echo "synced $f"
done

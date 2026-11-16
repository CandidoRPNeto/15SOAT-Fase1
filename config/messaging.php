<?php

/*
 * RabbitMQ settings (ADR-011). One topic exchange carries every message; the
 * routing key is the message type (cmd.<target>.<verb> / evt.<producer>.<fact>).
 * Queue bindings come from the MessageRouter, not from here.
 */
return [
    'driver' => env('MESSAGING_DRIVER', 'rabbitmq'),

    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', 'rabbitmq.platform.svc.cluster.local'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'workshop'),
        'password' => env('RABBITMQ_PASSWORD', 'workshop'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'workshop'),
        'dead_letter_exchange' => env('RABBITMQ_DLX', 'workshop.dlx'),
        'queue' => env('RABBITMQ_QUEUE', 'os.inbox'),
        'prefetch' => (int) env('RABBITMQ_PREFETCH', 10),
    ],

    'relay_batch_size' => (int) env('OUTBOX_RELAY_BATCH', 50),

    'saga' => [
        'step_timeout_seconds' => (int) env('SAGA_STEP_TIMEOUT_SECONDS', 120),
        'max_attempts' => (int) env('SAGA_MAX_ATTEMPTS', 3),
    ],
];

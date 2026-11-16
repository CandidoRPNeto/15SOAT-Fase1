<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orchestrated saga state (ADR-010), status history and stock reservations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saga_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('service_order_id')->unique()->constrained('service_orders');
            $table->string('status', 20)->default('running');
            $table->string('correlation_id', 64);
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('saga_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('saga_instance_id')->constrained('saga_instances');
            $table->string('name', 40);
            $table->string('status', 20);
            $table->string('command_type', 80)->nullable();
            $table->json('command_payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('detail')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['saga_instance_id', 'name']);
        });

        Schema::create('service_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason', 255);
            $table->timestamp('created_at');
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders');
            $table->foreignId('item_id')->constrained('items');
            $table->unsignedInteger('quantity');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('service_order_status_history');
        Schema::dropIfExists('saga_steps');
        Schema::dropIfExists('saga_instances');
    }
};

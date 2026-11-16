<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional outbox + idempotent inbox (ADR-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            // Auto-increment id gives the relay a stable publish order;
            // created_at ties within the same second.
            $table->id();
            $table->uuid('message_id')->unique();
            $table->string('type', 80);
            $table->json('body');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('processed_messages', function (Blueprint $table) {
            $table->uuid('message_id')->primary();
            $table->string('type', 80);
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_messages');
        Schema::dropIfExists('outbox_messages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Propagation headers of the span that recorded the message (ADR-016), so
 * the relay can publish it as part of the same trace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->json('trace_context')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropColumn('trace_context');
        });
    }
};

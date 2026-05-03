<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->enum('status', [
                'received',
                'in_diagnosis',
                'awaiting_approval',
                'approved',
                'cancelled',
                'in_execution',
                'finalized',
                'delivered',
            ])->default('received');
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('budget_sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only timeline. Duplicate events are ignored via event_hash.
        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('event_hash', 64);
            $table->timestamp('occurred_at')->nullable();
            $table->string('original_status')->nullable();
            $table->enum('normalized_status', [
                'placed', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery',
                'delivered', 'cancelled', 'return_requested', 'return_approved',
                'return_pickup_scheduled', 'return_picked_up', 'return_received',
                'return_completed', 'refund_processing', 'refunded', 'return_rejected', 'unknown',
            ])->nullable();
            $table->string('description', 500)->nullable();
            $table->enum('source', ['runner', 'manual'])->default('runner');
            $table->timestamp('created_at')->nullable();

            $table->unique(['shipment_id', 'event_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
    }
};

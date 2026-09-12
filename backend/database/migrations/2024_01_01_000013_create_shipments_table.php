<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('original_status')->nullable();
            $table->enum('normalized_status', [
                'placed', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery',
                'delivered', 'cancelled', 'return_requested', 'return_approved',
                'return_pickup_scheduled', 'return_picked_up', 'return_received',
                'return_completed', 'refund_processing', 'refunded', 'return_rejected', 'unknown',
            ])->default('unknown');
            $table->timestamp('expected_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_observed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};

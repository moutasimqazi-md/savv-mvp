<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table is plural "returns"; the Eloquent model is Savv\Models\OrderReturn
        // because "Return" is a reserved word in PHP.
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider_return_id')->nullable();
            $table->string('original_status')->nullable();
            $table->enum('normalized_status', [
                'placed', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery',
                'delivered', 'cancelled', 'return_requested', 'return_approved',
                'return_pickup_scheduled', 'return_picked_up', 'return_received',
                'return_completed', 'refund_processing', 'refunded', 'return_rejected', 'unknown',
            ])->default('unknown');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'provider_return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};

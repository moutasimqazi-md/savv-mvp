<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('INR');
            $table->string('original_status')->nullable();
            $table->enum('normalized_status', [
                'placed', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery',
                'delivered', 'cancelled', 'return_requested', 'return_approved',
                'return_pickup_scheduled', 'return_picked_up', 'return_received',
                'return_completed', 'return_rejected', 'refund_processing', 'refunded', 'unknown',
            ])->default('unknown');
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('masked_refund_method', 100)->nullable();
            $table->timestamps();

            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};

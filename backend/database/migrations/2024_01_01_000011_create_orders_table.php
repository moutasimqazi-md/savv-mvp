<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_connection_id')->nullable()
                ->constrained('provider_connections')->nullOnDelete();
            $table->enum('provider', ['amazon_in', 'flipkart']);
            $table->string('provider_order_id');
            $table->timestamp('ordered_at')->nullable();
            $table->string('original_status')->nullable();
            $table->enum('normalized_status', [
                'placed', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery',
                'delivered', 'cancelled', 'return_requested', 'return_approved',
                'return_pickup_scheduled', 'return_picked_up', 'return_received',
                'return_completed', 'refund_processing', 'refunded', 'return_rejected', 'unknown',
            ])->default('unknown');
            $table->char('currency', 3)->default('INR');
            // Money stored as integer minor units (paise). Never floating point.
            $table->bigInteger('subtotal_minor')->nullable();
            $table->bigInteger('shipping_fee_minor')->nullable();
            $table->bigInteger('discount_minor')->nullable();
            $table->bigInteger('tax_minor')->nullable();
            $table->bigInteger('total_minor')->nullable();
            $table->timestamp('expected_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('official_order_url', 2048)->nullable();
            $table->timestamp('source_observed_at')->nullable();
            $table->foreignId('last_import_batch_id')->nullable()
                ->constrained('import_batches')->nullOnDelete();
            $table->string('parser_version', 32)->nullable();
            $table->boolean('has_user_corrections')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'provider', 'provider_order_id']);
            $table->index(['user_id', 'normalized_status']);
            $table->index(['user_id', 'ordered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

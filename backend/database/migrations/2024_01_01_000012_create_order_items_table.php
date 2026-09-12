<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider_item_id')->nullable();
            // Deterministic fallback identity when provider_item_id is absent:
            // hash(normalized title + variant + quantity + line_total).
            $table->string('fingerprint', 64)->nullable();
            $table->string('title', 500);
            $table->string('variant', 500)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('unit_price_minor')->nullable();
            $table->bigInteger('line_total_minor')->nullable();
            $table->string('product_image_url', 2048)->nullable();
            $table->string('official_product_url', 2048)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'provider_item_id']);
            $table->index(['order_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

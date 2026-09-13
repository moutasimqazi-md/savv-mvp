<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array PROVIDER_ENUM_TABLES = [
        'consents', 'provider_connections', 'import_sessions', 'import_batches', 'orders',
    ];

    public function up(): void
    {
        // Flipkart support was removed before any orders referenced it in
        // this environment; drop the handful of session/consent rows that
        // do reference it so the enum can be safely narrowed.
        foreach (self::PROVIDER_ENUM_TABLES as $table) {
            DB::table($table)->where('provider', 'flipkart')->delete();
        }

        foreach (self::PROVIDER_ENUM_TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `provider` ENUM('amazon_in', 'claude') NOT NULL");
        }

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_connection_id')->nullable()
                ->constrained('provider_connections')->nullOnDelete();
            $table->enum('provider', ['amazon_in', 'claude']);
            // Not every subscription page exposes a stable id - dedup falls
            // back to (user_id, provider, plan_name) when this is null; see
            // Savv\Services\SubscriptionConfirmationService.
            $table->string('provider_subscription_id')->nullable();
            $table->string('plan_name');
            $table->string('original_status')->nullable();
            $table->enum('normalized_status', ['active', 'trialing', 'past_due', 'cancelled', 'unknown'])
                ->default('unknown');
            $table->bigInteger('price_minor')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->enum('billing_cycle', ['monthly', 'yearly', 'unknown'])->default('unknown');
            $table->timestamp('renewal_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('official_billing_url', 2048)->nullable();
            $table->timestamp('source_observed_at')->nullable();
            $table->foreignId('last_import_batch_id')->nullable()
                ->constrained('import_batches')->nullOnDelete();
            $table->string('parser_version', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'provider']);
        });

        Schema::create('subscription_previews', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('import_session_id')->constrained()->cascadeOnDelete();
            $table->string('provider_subscription_key');
            $table->boolean('selected')->default(true);
            $table->string('parser_version', 32)->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->json('normalized_payload');
            $table->json('field_warnings')->nullable();
            $table->timestamps();

            $table->unique(['import_session_id', 'provider_subscription_key'], 'sub_previews_session_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_previews');
        Schema::dropIfExists('subscriptions');

        foreach (self::PROVIDER_ENUM_TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `provider` ENUM('amazon_in', 'flipkart') NOT NULL");
        }
    }
};

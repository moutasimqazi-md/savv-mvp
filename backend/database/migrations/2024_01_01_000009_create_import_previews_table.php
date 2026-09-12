<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unconfirmed, normalized scan results shown to the user before they
        // choose what to import. Deleted on cancel, expiry, completion, or
        // by the cleanup command. Never holds raw/complete HTML.
        Schema::create('import_previews', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('import_session_id')->constrained()->cascadeOnDelete();
            $table->string('provider_order_id');
            $table->boolean('selected')->default(true);
            $table->string('parser_version', 32)->nullable();
            $table->timestamp('observed_at')->nullable();
            // Normalized order + items + shipment + return + refund draft,
            // already validated/sanitized by the runner and re-validated by
            // Laravel. Source metadata only, never raw HTML.
            $table->json('normalized_payload');
            $table->json('field_warnings')->nullable();
            $table->timestamps();

            $table->unique(['import_session_id', 'provider_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_previews');
    }
};

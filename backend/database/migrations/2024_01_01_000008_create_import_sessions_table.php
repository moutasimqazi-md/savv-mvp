<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A temporary, isolated-Chromium import session. Must never contain
        // cookies, passwords, OTPs, browser-profile contents, auth headers,
        // or complete HTML. Only lifecycle/bookkeeping fields are stored.
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consent_id')->nullable()->constrained('consents')->nullOnDelete();
            $table->enum('provider', ['amazon_in', 'flipkart']);
            $table->enum('status', [
                'requested', 'starting', 'ready', 'awaiting_login', 'ready_to_scan',
                'scanning', 'preview_ready', 'importing', 'completed', 'cancelled',
                'expired', 'failed', 'terminating', 'terminated',
            ])->default('requested');

            // Opaque references to the runner-side process; never a secret.
            $table->string('runner_process_ref', 64)->nullable();
            $table->string('display_ref', 64)->nullable();

            // Hash of the current single-use, short-lived browser-view token.
            // The raw token is only ever returned once to the browser and
            // never persisted in plaintext.
            $table->string('view_token_hash', 64)->nullable();
            $table->timestamp('view_token_expires_at')->nullable();

            $table->string('safe_error_code', 64)->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};

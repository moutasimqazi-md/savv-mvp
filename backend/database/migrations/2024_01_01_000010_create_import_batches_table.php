<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The confirmed, permanent record of one import (from a runner scan,
        // a manual form, or a CSV upload).
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_session_id')->nullable()
                ->constrained('import_sessions')->nullOnDelete();
            $table->foreignId('provider_connection_id')->nullable()
                ->constrained('provider_connections')->nullOnDelete();
            $table->enum('provider', ['amazon_in', 'flipkart']);
            $table->enum('status', ['pending', 'processing', 'completed', 'completed_with_errors', 'failed'])
                ->default('pending');
            $table->enum('source', ['runner', 'manual', 'csv'])->default('runner');
            $table->string('parser_version', 32)->nullable();
            $table->unsignedInteger('submitted_order_count')->default(0);
            $table->unsignedInteger('imported_order_count')->default(0);
            $table->unsignedInteger('failed_order_count')->default(0);
            $table->string('error_summary')->nullable();
            // Source metadata only (e.g. page type, scan duration, page count) - never raw HTML.
            $table->json('source_metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};

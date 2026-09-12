<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manual overrides. These take the highest merge precedence (see
        // Savv\Services\MergeService) and are never silently overwritten by
        // a later import without explicit confirmation.
        Schema::create('user_corrections', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('correctable_type');
            $table->unsignedBigInteger('correctable_id');
            $table->string('field', 100);
            $table->text('previous_value')->nullable();
            $table->text('corrected_value')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['correctable_type', 'correctable_id']);
            $table->index(['user_id', 'correctable_type', 'correctable_id', 'field'], 'user_corrections_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_corrections');
    }
};

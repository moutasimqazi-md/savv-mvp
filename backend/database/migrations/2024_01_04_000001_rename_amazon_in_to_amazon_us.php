<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array PROVIDER_ENUM_TABLES = [
        'consents', 'provider_connections', 'import_sessions', 'import_batches', 'orders',
    ];

    public function up(): void
    {
        foreach (self::PROVIDER_ENUM_TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `provider` ENUM('amazon_in', 'amazon_us', 'claude', 'walmart') NOT NULL");
            DB::table($table)->where('provider', 'amazon_in')->update(['provider' => 'amazon_us']);
            DB::statement("ALTER TABLE `{$table}` MODIFY `provider` ENUM('amazon_us', 'claude', 'walmart') NOT NULL");
        }

        // Orders carry the marketplace's own currency; existing Amazon India
        // rows were seeded/imported in INR, but the site they now represent
        // (amazon.com) only ever produces USD. Only affects demo/dev data -
        // a real deployment migrating this in production wouldn't rewrite
        // historical currency figures.
        DB::table('orders')->where('provider', 'amazon_us')->where('currency', 'INR')->update(['currency' => 'USD']);
    }

    public function down(): void
    {
        foreach (self::PROVIDER_ENUM_TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `provider` ENUM('amazon_in', 'amazon_us', 'claude', 'walmart') NOT NULL");
            DB::table($table)->where('provider', 'amazon_us')->update(['provider' => 'amazon_in']);
            DB::statement("ALTER TABLE `{$table}` MODIFY `provider` ENUM('amazon_in', 'claude', 'walmart') NOT NULL");
        }
    }
};

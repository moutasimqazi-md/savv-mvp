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
            DB::statement("ALTER TABLE `{$table}` MODIFY `provider` ENUM('amazon_in', 'claude', 'walmart') NOT NULL");
        }
    }

    public function down(): void
    {
        DB::table('consents')->where('provider', 'walmart')->delete();
        DB::table('provider_connections')->where('provider', 'walmart')->delete();
        DB::table('import_sessions')->where('provider', 'walmart')->delete();
        DB::table('import_batches')->where('provider', 'walmart')->delete();
        DB::table('orders')->where('provider', 'walmart')->delete();

        foreach (self::PROVIDER_ENUM_TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `provider` ENUM('amazon_in', 'claude') NOT NULL");
        }
    }
};

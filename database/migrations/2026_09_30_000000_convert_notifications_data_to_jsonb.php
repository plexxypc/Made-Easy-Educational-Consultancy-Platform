<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert `notifications.data` from text to jsonb on PostgreSQL.
     *
     * Filament's notification bell filters with `data->>'format'`, and PostgreSQL
     * has no `->>` operator for text. SQLite (used by the test suite) reads JSON
     * from text columns, so this is a no-op there.
     *
     * @return void
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }
};

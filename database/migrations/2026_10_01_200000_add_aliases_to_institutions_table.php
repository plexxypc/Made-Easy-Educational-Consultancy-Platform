<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alternate names for an institution (e.g. "UNILAG" for "University of
     * Lagos"), stored as a JSON list. Only the CSV import uses them, to match
     * a line to an existing institution instead of creating a duplicate.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->json('aliases')->nullable()->after('name_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->dropColumn('aliases');
        });
    }
};

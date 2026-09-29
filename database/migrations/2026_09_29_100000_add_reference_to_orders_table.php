<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer-facing order reference.
     *
     * New orders get a random code such as "ME-7K3Q9A" (Order::generateReference()).
     * Existing orders keep the reference customers were already given, which was
     * their order number, so they are backfilled with the id as text ("12").
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('reference', 20)->nullable()->after('id');
        });

        DB::table('orders')->update(['reference' => DB::raw('CAST(id AS VARCHAR(20))')]);

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('reference', 20)->nullable(false)->change();
            $table->unique('reference');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many units the order is for. Only result checkers (instant services)
     * are bought in quantities above 1; every other order stays at 1.
     *
     * MONEY: orders.total_amount (KOBO) is service price × quantity when placed.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedSmallInteger('quantity')->default(1)->after('service_id');
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
            $table->dropColumn('quantity');
        });
    }
};

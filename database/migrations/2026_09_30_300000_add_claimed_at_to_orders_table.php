<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a guest order was added to an account by its owner (ClaimGuestOrders).
     * Null for orders placed while signed in, and for unclaimed guest orders.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('user_id');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('claimed_at');
        });
    }
};

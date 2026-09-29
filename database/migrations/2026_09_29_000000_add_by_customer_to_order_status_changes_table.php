<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mark status changes made by the customer (responding to "needs action")
     * rather than staff. `changed_by` alone can't tell: guest responses have no
     * user, and staff rows become null if the staff account is deleted.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('order_status_changes', function (Blueprint $table) {
            $table->boolean('by_customer')->default(false)->after('changed_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('order_status_changes', function (Blueprint $table) {
            $table->dropColumn('by_customer');
        });
    }
};

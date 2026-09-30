<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the customer answered "Not now" to the guest-order claim prompt.
     * Guest orders placed before this time are no longer offered; newer ones
     * are (see FindClaimableGuestOrders). Cleared when the email changes.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('guest_claims_dismissed_at')->nullable()->after('messages_read_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('guest_claims_dismissed_at');
        });
    }
};

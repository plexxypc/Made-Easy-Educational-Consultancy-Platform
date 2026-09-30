<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When staff were reminded that this booking still has no meeting link
     * (RemindStaffOfMissingMeetingLinks); set once so the reminder isn't repeated.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('consultation_bookings', function (Blueprint $table) {
            $table->timestamp('meeting_link_reminder_sent_at')->nullable()->after('meeting_link_added_at');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::table('consultation_bookings', function (Blueprint $table) {
            $table->dropColumn('meeting_link_reminder_sent_at');
        });
    }
};

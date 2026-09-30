<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consultation availability, added by admins.
     *
     *  - `date` + `start_time` are wall-clock times in
     *    config('madeeasy.consultations.timezone') (Africa/Lagos), not UTC.
     *  - `capacity`: how many bookings the slot takes (default 1). Bookings
     *    point at their slot (consultation_bookings.consultation_slot_id), so
     *    a slot can hold several when capacity > 1; "taken" is computed.
     *  - Slots are cancelled (cancelled_at), not deleted, once anyone booked them.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('consultation_slots', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('consultation_type', 20);
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['date', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_slots');
    }
};

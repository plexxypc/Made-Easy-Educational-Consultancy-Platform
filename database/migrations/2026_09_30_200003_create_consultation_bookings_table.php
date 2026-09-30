<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer's booking of a consultation slot.
     *
     *  - One booking per order (order_id unique). Contact details are NOT
     *    repeated here: they are the order's (user, or guest_* columns).
     *  - status: pending_payment, confirmed, completed, no_show, cancelled.
     *  - hold_expires_at: while pending_payment, the slot is held until then.
     *  - needs_rebooking_at/reason: the customer PAID but the slot could no
     *    longer be confirmed (their hold ran out and it was taken, or it was
     *    cancelled or had passed). Staff reschedule or cancel; status stays
     *    pending_payment until then.
     *  - meeting_link: added by staff for online / Google Meet slots.
     *  - FKs restrict deletes so a booking always keeps its order and slot.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('consultation_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('consultation_slot_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending_payment');
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('needs_rebooking_at')->nullable();
            $table->string('needs_rebooking_reason', 255)->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->foreignId('meeting_link_added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('meeting_link_added_at')->nullable();
            $table->foreignId('previous_slot_id')->nullable()->constrained('consultation_slots')->nullOnDelete();
            $table->unsignedSmallInteger('reschedule_count')->default(0);
            $table->timestamp('rescheduled_at')->nullable();
            $table->timestamp('closed_at')->nullable()->comment('completed, no-show or cancelled at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();

            // Capacity checks count a slot's bookings by status.
            $table->index(['consultation_slot_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_bookings');
    }
};

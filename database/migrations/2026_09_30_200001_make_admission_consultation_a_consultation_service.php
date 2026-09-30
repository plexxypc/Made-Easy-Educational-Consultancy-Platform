<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The seeded "Admission Consultation" service becomes a consultation
     * booking (fulfilment_type "consultation"), booked on /book-consultation
     * instead of the generic request form.
     *
     * Deliberately does NOT touch is_active or the price: an admin
     * reactivates the service by hand once booking has been tested.
     *
     * @return void
     */
    public function up(): void
    {
        DB::table('services')
            ->where('slug', 'admission-consultation')
            ->where('fulfilment_type', 'manual')
            ->update(['fulfilment_type' => 'consultation', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('services')
            ->where('fulfilment_type', 'consultation')
            ->update(['fulfilment_type' => 'manual', 'updated_at' => now()]);
    }
};

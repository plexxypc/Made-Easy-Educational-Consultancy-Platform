<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-service low-stock threshold and alert state for result checker PINs.
     *
     *  - pin_low_stock_threshold: "low" at or below this many available PINs.
     *    Null = use config('madeeasy.pins.low_stock_threshold').
     *  - pin_stock_level / pin_stock_level_changed_at: the level last seen by
     *    CheckPinStockLevel (ok, low, out; null = never checked, treated as ok),
     *    so staff are alerted once per crossing rather than on every check.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedInteger('pin_low_stock_threshold')->nullable()->after('customer_fields');
            $table->string('pin_stock_level', 10)->nullable()->after('pin_low_stock_threshold');
            $table->timestamp('pin_stock_level_changed_at')->nullable()->after('pin_stock_level');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['pin_low_stock_threshold', 'pin_stock_level', 'pin_stock_level_changed_at']);
        });
    }
};

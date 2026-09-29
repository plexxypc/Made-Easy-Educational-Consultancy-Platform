<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer orders for a single service.
     *
     * MONEY: `total_amount` is an integer amount in KOBO. It is copied from the
     * service price when the order is placed (or set by staff for quote services),
     * so later price changes never alter existing orders.
     *
     * `user_id` is nullable for guest checkout; guest_* columns are used instead.
     * Orders are soft-deleted so payment/audit history is never lost.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending_payment')->index();
            $table->unsignedBigInteger('total_amount')->comment('kobo');
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable()->index();
            $table->string('guest_phone', 30)->nullable();
            $table->json('field_answers')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

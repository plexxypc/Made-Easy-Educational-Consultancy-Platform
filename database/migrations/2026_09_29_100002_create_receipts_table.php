<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Receipts for verified payments (one per successful payment).
     *
     * The customer and service details are copied at payment time so the
     * receipt never changes if the service is renamed or the customer updates
     * their profile later. MONEY: `amount` is an integer in KOBO.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('order_reference', 20);
            $table->string('payment_reference', 100);
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('service_name');
            $table->unsignedBigInteger('amount')->comment('kobo');
            $table->string('currency', 3)->default('NGN');
            $table->string('payment_status', 20);
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};

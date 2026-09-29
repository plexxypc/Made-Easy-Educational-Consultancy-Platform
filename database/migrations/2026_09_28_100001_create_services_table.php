<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purchasable services.
     *
     * MONEY: `price` is an integer amount in KOBO (₦1 = 100 kobo), never a float.
     * For fulfilment_type = quote the price is 0; staff set the order total instead.
     *
     * `customer_fields` is a JSON list of field definitions the customer must fill in:
     *   [{"key": "jamb_score", "label": "JAMB Score", "type": "number", "required": true,
     *     "min": 0, "max": 400,       // optional, type "number" only
     *     "options": ["UTME", "DE"]  // required for type "select"
     *   }, ...]
     * Types: text, textarea, number, phone, email, select.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            // Restrict: a category with services can't be deleted out from under them.
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price')->default(0)->comment('kobo');
            $table->boolean('is_active')->default(true)->index();
            $table->string('fulfilment_type', 20)->index();
            $table->json('customer_fields')->nullable();
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
        Schema::dropIfExists('services');
    }
};

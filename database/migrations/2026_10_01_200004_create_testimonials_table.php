<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer testimonials (PDF section 15). Only "approved" ones are ever
     * shown publicly; customers submit from a completed order (one per
     * order, enforced by the unique index), staff may also add one by hand
     * (order_id NULL). Deleting an order keeps its testimonial, unlinked.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('customer_name', 80);
            $table->text('body');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};

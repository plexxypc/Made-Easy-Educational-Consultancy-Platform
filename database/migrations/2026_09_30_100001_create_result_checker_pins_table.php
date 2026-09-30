<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Result checker PIN stock (e.g. WAEC Result Checker scratch-card PINs).
     *
     *  - `pin` and `serial_number` are ENCRYPTED at rest (Laravel "encrypted"
     *    cast, APP_KEY), so they are text columns holding ciphertext.
     *  - Encryption is non-deterministic, so `pin_hash` (HMAC-SHA256 of the
     *    normalised PIN, keyed with APP_KEY) is what the unique index and the
     *    duplicate checks use. Unique across ALL services: re-uploading a batch
     *    under the wrong service is caught too.
     *  - status: available -> allocated (reserved for an order) -> revealed
     *    (delivered to the customer). order_id is null until allocated.
     *  - FKs restrict deletes: a PIN that was sold keeps its order/service.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('result_checker_pins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->text('pin')->comment('encrypted');
            $table->text('serial_number')->nullable()->comment('encrypted');
            $table->string('pin_hash', 64)->unique();
            $table->string('status', 20)->default('available');
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('batch_id', 26)->nullable()->index()->comment('ULID shared by one upload');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();

            // Allocation looks for the oldest available PIN of a service.
            $table->index(['service_id', 'status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('result_checker_pins');
    }
};

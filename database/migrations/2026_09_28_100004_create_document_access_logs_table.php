<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log: one row each time a staff/admin user opens a customer document.
     *
     * Both foreign keys RESTRICT deletes, so audit rows can't be removed as a side
     * effect of deleting a document or a staff account. Deactivate/demote instead.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('document_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('viewed_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('document_access_logs');
    }
};

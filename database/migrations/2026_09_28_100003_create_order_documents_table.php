<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files uploaded against an order (NIN slips, birth certificates, ...).
     *
     * `stored_path` is relative to the PRIVATE "local" disk (storage/app/private).
     * Files are only ever served through OrderDocumentController, never publicly.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('order_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->comment('bytes');
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('order_documents');
    }
};

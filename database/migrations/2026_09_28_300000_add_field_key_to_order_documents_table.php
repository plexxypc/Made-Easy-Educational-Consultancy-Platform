<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link an uploaded document to the service field ("file" type) it answers,
     * e.g. "passport_photo". Null for documents not tied to a form field.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('order_documents', function (Blueprint $table) {
            $table->string('field_key', 50)->nullable()->after('order_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('order_documents', function (Blueprint $table) {
            $table->dropColumn('field_key');
        });
    }
};

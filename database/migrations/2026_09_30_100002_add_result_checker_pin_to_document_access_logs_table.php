<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reuse the sensitive-access audit table for result checker PINs, rather
     * than adding a second audit table: each row now records EITHER an order
     * document view OR a PIN view (exactly one of the two ids is set).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('document_access_logs', function (Blueprint $table): void {
            $table->foreignId('order_document_id')->nullable()->change();
            $table->foreignId('result_checker_pin_id')
                ->nullable()
                ->after('order_document_id')
                ->constrained()
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_access_logs ADD CONSTRAINT document_access_logs_one_subject CHECK (num_nonnulls(order_document_id, result_checker_pin_id) = 1)');
        }
    }

    /**
     * Reverse the migrations. PIN audit rows can't exist without the column,
     * so they are deleted first (this loses that audit history).
     *
     * @return void
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_access_logs DROP CONSTRAINT IF EXISTS document_access_logs_one_subject');
        }

        DB::table('document_access_logs')->whereNull('order_document_id')->delete();

        Schema::table('document_access_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('result_checker_pin_id');
            $table->foreignId('order_document_id')->nullable(false)->change();
        });
    }
};

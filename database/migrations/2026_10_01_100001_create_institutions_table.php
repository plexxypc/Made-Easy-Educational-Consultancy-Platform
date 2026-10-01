<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tertiary institutions for the course & cut-off search (PDF section 11).
     *
     * name_key is the name lowercased with spaces squeezed (Institution::
     * name_key); it is what the CSV import matches on, and unique so
     * "University of Lagos" and "university of  lagos" can't both exist.
     *
     * type, ownership and state are NULLABLE because the CSV import may
     * create an institution from its name alone. The admin form requires
     * them; an institution without a state is left out of state-filtered
     * searches until someone fills it in.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('type', 32)->nullable();
            $table->string('ownership', 16)->nullable();
            $table->string('state', 32)->nullable()->index();
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
        Schema::dropIfExists('institutions');
    }
};

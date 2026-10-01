<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What one institution asks for one course: the JAMB (UTME) cut-off mark
     * and the number of O'Level credits. One row per institution + course;
     * re-importing a pair updates it (e.g. a new admission year).
     *
     * Institutions and courses can't be deleted while they still have
     * requirements (restrict), so a delete never silently drops data.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('admission_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('cut_off_mark'); // JAMB UTME score, 0–400.
            $table->unsignedTinyInteger('olevel_credits_required'); // 1–9 subjects.
            $table->timestamps();

            $table->unique(['institution_id', 'course_id']);
            $table->index(['course_id', 'cut_off_mark']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_requirements');
    }
};

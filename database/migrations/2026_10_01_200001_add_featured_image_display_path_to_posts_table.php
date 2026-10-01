<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resized, compressed copy of the featured image that the public site
     * serves (public disk). featured_image_path keeps the original upload.
     * NULL means no copy could be made, and the original is served.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('featured_image_display_path')->nullable()->after('featured_image_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('featured_image_display_path');
        });
    }
};

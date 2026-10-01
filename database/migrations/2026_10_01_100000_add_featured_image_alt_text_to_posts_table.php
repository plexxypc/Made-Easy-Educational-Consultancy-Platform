<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alt text for a post's featured image, read out by screen readers on
     * the public post page. Required by the editor whenever an image is set.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('featured_image_alt_text')->nullable()->after('featured_image_path');
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
            $table->dropColumn('featured_image_alt_text');
        });
    }
};

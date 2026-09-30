<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Updates (blog) posts.
     *
     *  - `published_at`: NULL = draft; in the future = scheduled; in the past
     *    = public. Nothing needs to run at the scheduled time: public queries
     *    compare against now().
     *  - `body`: HTML from the admin rich text editor. Always rendered through
     *    Post::body_html(), which sanitises it.
     *  - `featured_image_path`: path on the PUBLIC disk (marketing content,
     *    not a customer document).
     *  - `author_id`: the staff/admin user who created the post; kept (NULL)
     *    if that account is ever deleted.
     *  - Categories with posts can't be deleted (restrict).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_category_id')->constrained('post_categories')->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->string('featured_image_path')->nullable();
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->timestamp('published_at')->nullable()->index();
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
        Schema::dropIfExists('posts');
    }
};

<?php

namespace Tests\Feature\Updates;

use App\Actions\Posts\MakeFeaturedImageDisplayCopy;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Featured images: a resized WebP copy is served and the original kept;
 * and the slug warning on published posts.
 */
class FeaturedImageResizeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP support is required (installed in the Docker image).');
        }

        Storage::fake('public');
    }

    /**
     * A wide photo is scaled to the max width as WebP; the original stays.
     * A small image keeps its size (recompressed only).
     *
     * @return void
     */
    public function test_large_images_are_scaled_down_and_the_original_kept(): void
    {
        $max_width = (int) config('madeeasy.blog.display_image_max_width');
        $original_path = $this->store_jpeg(2400, 1600);
        $original_bytes = Storage::disk('public')->size($original_path);

        $post = Post::factory()->create(['featured_image_path' => $original_path]);

        $this->assertSame(MakeFeaturedImageDisplayCopy::path_for($original_path), $post->featured_image_display_path);
        Storage::disk('public')->assertExists($original_path);
        $this->assertSame($original_bytes, Storage::disk('public')->size($original_path));

        $size = getimagesizefromstring((string) Storage::disk('public')->get($post->featured_image_display_path));
        $this->assertSame([$max_width, 800], [$size[0], $size[1]]);
        $this->assertSame('image/webp', $size['mime']);
        $this->assertLessThan($original_bytes, Storage::disk('public')->size($post->featured_image_display_path));
        $this->assertSame(Storage::disk('public')->url($post->featured_image_display_path), $post->featuredImageUrl());

        $small = Post::factory()->create(['featured_image_path' => $this->store_jpeg(600, 400)]);
        $small_size = getimagesizefromstring((string) Storage::disk('public')->get($small->featured_image_display_path));
        $this->assertSame([600, 400], [$small_size[0], $small_size[1]]);

        // Removing the image removes the copy; deleting the post removes both files.
        $display_path = $post->featured_image_display_path;
        $post->update(['featured_image_path' => null]);
        $this->assertNull($post->refresh()->featured_image_display_path);
        Storage::disk('public')->assertMissing($display_path);
        Storage::disk('public')->assertMissing($original_path);

        $small_paths = [$small->featured_image_path, $small->featured_image_display_path];
        $small->delete();
        foreach ($small_paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    /**
     * The backfill command makes copies for posts saved before resizing existed.
     *
     * @return void
     */
    public function test_backfill_command(): void
    {
        $post = Post::factory()->create();
        $post->forceFill(['featured_image_path' => $this->store_jpeg(1600, 900)])->saveQuietly();
        $this->assertNull($post->refresh()->featured_image_display_path);

        $this->artisan('posts:make-display-images')->expectsOutputToContain('Display copies made: 1.')->assertSuccessful();

        Storage::disk('public')->assertExists((string) $post->refresh()->featured_image_display_path);
    }

    /**
     * Editing the slug of a published post shows a warning; the title no
     * longer rewrites the slug once a post is live. Drafts are unaffected.
     *
     * @return void
     */
    public function test_slug_change_warning_on_published_posts(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $published = Post::factory()->create(['title' => 'WAEC Timetable', 'slug' => 'waec-timetable', 'published_at' => now()->subDay()]);
        $draft = Post::factory()->create(['title' => 'NECO Dates', 'slug' => 'neco-dates', 'published_at' => null]);

        $this->assertNull(PostForm::slug_change_warning($published, 'waec-timetable'));
        $this->assertNull(PostForm::slug_change_warning($draft, 'neco-dates-2027'));
        $this->assertSame('Published post: saving breaks links to /updates/waec-timetable', PostForm::slug_change_warning($published, 'waec-2027-timetable'));

        Livewire::test(EditPost::class, ['record' => $published->getRouteKey()])
            ->assertDontSee('saving breaks links')
            ->fillForm(['title' => 'WAEC Timetable (updated)'])
            ->assertSchemaStateSet(['slug' => 'waec-timetable'])
            ->fillForm(['slug' => 'waec-2027-timetable'])
            ->assertSee('Published post: saving breaks links to /updates/waec-timetable')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Post address changed');

        Livewire::test(EditPost::class, ['record' => $draft->getRouteKey()])
            ->fillForm(['title' => 'NECO Dates 2027'])
            ->assertSchemaStateSet(['slug' => 'neco-dates-2027'])
            ->assertDontSee('saving breaks links');
    }

    /**
     * A JPEG of the given size on the public disk, with some detail so the
     * compression comparison is meaningful.
     *
     * @param  int  $width
     * @param  int  $height
     * @return string Path on the public disk.
     */
    private function store_jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x += 20) {
            imagefilledrectangle($image, $x, 0, $x + 19, $height, (int) imagecolorallocate($image, ($x * 7) % 256, ($x * 3) % 256, ($x * 11) % 256));
        }

        ob_start();
        imagejpeg($image, null, 95);
        $path = Post::IMAGE_DIRECTORY.'/'.uniqid('photo', true).'.jpg';
        Storage::disk('public')->put($path, (string) ob_get_clean());

        return $path;
    }
}

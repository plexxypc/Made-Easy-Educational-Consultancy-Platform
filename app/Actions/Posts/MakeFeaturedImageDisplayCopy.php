<?php

namespace App\Actions\Posts;

use App\Models\Post;
use GdImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Make the copy of a post's featured image that the public site serves.
 *
 * Most readers are on mobile data, so the original upload (often a phone
 * photo of several MB) is not shown directly. Instead a WebP copy is saved
 * next to it, scaled down to config('madeeasy.blog.display_image_max_width')
 * (never up) and compressed. The original stays untouched on the public disk
 * so a better copy can be made later (e.g. a different size).
 *
 * If no copy can be made (GD missing, unreadable file, or an image too large
 * to decode safely) the display path is cleared and the site falls back to
 * the original, so saving a post never fails because of this.
 */
class MakeFeaturedImageDisplayCopy
{
    /**
     * Folder on the public disk for display copies.
     *
     * @var string
     */
    public const DIRECTORY = Post::IMAGE_DIRECTORY.'/display';

    /**
     * Where the display copy of an original lives (same base name, .webp).
     *
     * @param  string  $original_path
     * @return string
     */
    public static function path_for(string $original_path): string
    {
        return self::DIRECTORY.'/'.pathinfo($original_path, PATHINFO_FILENAME).'.webp';
    }

    /**
     * Create (or remove) the display copy and record it on the post without
     * firing model events.
     *
     * @param  Post  $post
     * @return string|null The display path, or null if the original is served.
     */
    public function handle(Post $post): ?string
    {
        $disk = Storage::disk('public');
        $old_path = $post->featured_image_display_path;
        $new_path = filled($post->featured_image_path) ? $this->make($post->featured_image_path) : null;

        if (filled($old_path) && $old_path !== $new_path) {
            $disk->delete($old_path);
        }

        $post->forceFill(['featured_image_display_path' => $new_path])->saveQuietly();

        return $new_path;
    }

    /**
     * @param  string  $original_path  On the public disk.
     * @return string|null
     */
    private function make(string $original_path): ?string
    {
        if (! function_exists('imagewebp')) {
            Log::warning('Featured image served at full size: the GD extension with WebP support is not installed.', ['path' => $original_path]);

            return null;
        }

        $disk = Storage::disk('public');
        $contents = $disk->get($original_path);
        $size = $contents === null ? false : @getimagesizefromstring($contents);

        if ($size === false) {
            Log::warning('Featured image served at full size: it could not be read as an image.', ['path' => $original_path]);

            return null;
        }

        if ((int) config('madeeasy.blog.display_image_max_pixels') < $size[0] * $size[1]) {
            Log::warning('Featured image served at full size: too many pixels to resize safely.', ['path' => $original_path, 'width' => $size[0], 'height' => $size[1]]);

            return null;
        }

        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            Log::warning('Featured image served at full size: GD could not decode it.', ['path' => $original_path]);

            return null;
        }

        $image = $this->upright($image, $contents, $size[2]);
        $image = $this->scaled_down($image, (int) config('madeeasy.blog.display_image_max_width'));

        ob_start();
        imagewebp($image, null, (int) config('madeeasy.blog.display_image_quality'));
        $webp = (string) ob_get_clean();

        $path = self::path_for($original_path);
        $disk->put($path, $webp, 'public');

        return $path;
    }

    /**
     * Apply the EXIF orientation of phone photos, which browsers would
     * otherwise show sideways once the EXIF data is gone from the copy.
     *
     * @param  GdImage  $image
     * @param  string  $contents
     * @param  int  $image_type  IMAGETYPE_* constant.
     * @return GdImage
     */
    private function upright(GdImage $image, string $contents, int $image_type): GdImage
    {
        if ($image_type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $contents);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    /**
     * Scale to at most $max_width wide, keeping the aspect ratio and any
     * transparency. Smaller images keep their size (only recompressed).
     *
     * @param  GdImage  $image
     * @param  int  $max_width
     * @return GdImage
     */
    private function scaled_down(GdImage $image, int $max_width): GdImage
    {
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        if (imagesx($image) <= $max_width) {
            return $image;
        }

        $scaled = imagescale($image, $max_width, -1, IMG_BICUBIC);

        if (! $scaled instanceof GdImage) {
            return $image;
        }

        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);

        return $scaled;
    }
}

<?php

namespace App\Console\Commands;

use App\Actions\Posts\MakeFeaturedImageDisplayCopy;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * One-off backfill: posts saved before display copies existed (or before GD
 * was installed) have none until they are saved again. Safe to re-run.
 */
class MakePostDisplayImages extends Command
{
    /**
     * @var string
     */
    protected $signature = 'posts:make-display-images';

    /**
     * @var string
     */
    protected $description = 'Create the resized copy of featured images for posts that lack one.';

    /**
     * @param  MakeFeaturedImageDisplayCopy  $make_featured_image_display_copy
     * @return int
     */
    public function handle(MakeFeaturedImageDisplayCopy $make_featured_image_display_copy): int
    {
        $made = 0;
        $failed = 0;

        Post::query()->whereNotNull('featured_image_path')->lazyById()->each(function (Post $post) use ($make_featured_image_display_copy, &$made, &$failed): void {
            if (! $post->needsDisplayImage()) {
                return;
            }

            $make_featured_image_display_copy->handle($post) === null ? $failed++ : $made++;
        });

        $this->info("Display copies made: {$made}. Still served at full size: {$failed} (see the log).");

        return self::SUCCESS;
    }
}

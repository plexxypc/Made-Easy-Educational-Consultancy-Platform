<?php

namespace App\Actions\Posts;

use App\Models\Post;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * "Publish now" and "Unpublish" from the post list (admins only).
 * Unpublishing turns the post back into a draft; its URL then 404s.
 */
class SetPostPublishDate
{
    /**
     * @param  Post  $post
     * @param  DateTimeInterface|null  $published_at  NULL = back to draft.
     * @param  User  $actor
     * @return Post
     *
     * @throws AuthorizationException
     */
    public function handle(Post $post, ?DateTimeInterface $published_at, User $actor): Post
    {
        Gate::forUser($actor)->authorize('publish', Post::class);

        $post->forceFill(['published_at' => $published_at])->save();

        Log::info($published_at === null ? 'Post unpublished.' : 'Post published.', [
            'post_id' => $post->id,
            'user_id' => $actor->id,
            'published_at' => $published_at?->format(DATE_ATOM),
        ]);

        return $post;
    }
}

<?php

namespace App\Actions\Posts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Form data for creating or editing an Updates post, made safe for the actor.
 *
 * Only users allowed to publish (PostPolicy::publish, admins) may set the
 * publish date. For anyone else it is ignored even if the request carries
 * one (the field is also disabled in the form), so a staff member's post is
 * always saved as a draft. The author is whoever creates the post.
 */
class SavePost
{
    /**
     * @param  array<string, mixed>  $data
     * @param  User  $actor
     * @return array<string, mixed>
     */
    public function for_create(array $data, User $actor): array
    {
        if (! Gate::forUser($actor)->allows('publish', Post::class)) {
            $data['published_at'] = null;
        }

        return [...$data, 'author_id' => $actor->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Post  $post
     * @param  User  $actor
     * @return array<string, mixed>
     */
    public function for_update(array $data, Post $post, User $actor): array
    {
        unset($data['author_id']);

        if (! Gate::forUser($actor)->allows('publish', Post::class)) {
            $data['published_at'] = $post->published_at;
        }

        return $data;
    }
}

<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Updates (blog) posts in the back office.
 *
 * Decision: staff and admins both write posts, but only ADMINS publish.
 * Posts are public statements from the business (deadlines, cut-off marks),
 * so what goes live gets a second pair of eyes. Staff therefore:
 *  - create posts and edit or delete DRAFTS;
 *  - can't set or change the publish date (PostPolicy::publish), and the
 *    form drops it for them (SavePost), whatever the browser sends;
 *  - can't edit or delete a post once it is scheduled or published, since
 *    changing live content is the same as publishing it.
 * Customers never reach the back office (User::canAccessPanel).
 */
class PostPolicy
{
    /**
     * @param  User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * @param  User  $user
     * @param  Post  $post
     * @return bool
     */
    public function view(User $user, Post $post): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * @param  User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * @param  User  $user
     * @param  Post  $post
     * @return bool
     */
    public function update(User $user, Post $post): bool
    {
        return $user->isAdmin() || ($user->isStaff() && $post->published_at === null);
    }

    /**
     * Set, change or clear the publish date (publish now, schedule, unpublish).
     *
     * @param  User  $user
     * @return bool
     */
    public function publish(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @param  Post  $post
     * @return bool
     */
    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    /**
     * @param  User  $user
     * @return bool
     */
    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}

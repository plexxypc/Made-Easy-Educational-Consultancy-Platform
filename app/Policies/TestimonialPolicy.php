<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

/**
 * Testimonial moderation: staff and admins approve or reject (it is the
 * same day-to-day work as handling orders). Staff may add one received
 * another way (e.g. on WhatsApp), which starts as pending like any other.
 * Editing is limited to typo fixes on the customer's words; deleting is
 * admin-only (rejecting hides one just as well, and keeps the record).
 */
class TestimonialPolicy
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
     * @param  Testimonial  $testimonial
     * @return bool
     */
    public function view(User $user, Testimonial $testimonial): bool
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
     * @param  Testimonial  $testimonial
     * @return bool
     */
    public function update(User $user, Testimonial $testimonial): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Approve or reject.
     *
     * @param  User  $user
     * @param  Testimonial  $testimonial
     * @return bool
     */
    public function moderate(User $user, Testimonial $testimonial): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * @param  User  $user
     * @param  Testimonial  $testimonial
     * @return bool
     */
    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $user->isAdmin();
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

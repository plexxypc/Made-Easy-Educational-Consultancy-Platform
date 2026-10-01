<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

/**
 * Course & cut-off search data in the back office: staff and admins.
 */
class CoursePolicy
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
     * @param  Course  $course
     * @return bool
     */
    public function view(User $user, Course $course): bool
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
     * @param  Course  $course
     * @return bool
     */
    public function update(User $user, Course $course): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Only once it has no admission requirements left (FK restrict).
     *
     * @param  User  $user
     * @param  Course  $course
     * @return bool
     */
    public function delete(User $user, Course $course): bool
    {
        return $user->isStaffOrAdmin() && ! $course->admissionRequirements()->exists();
    }
}

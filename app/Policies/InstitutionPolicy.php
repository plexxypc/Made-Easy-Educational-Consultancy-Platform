<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\User;

/**
 * Course & cut-off search data in the back office: staff and admins.
 * The public search reads institutions without going through this policy.
 */
class InstitutionPolicy
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
     * @param  Institution  $institution
     * @return bool
     */
    public function view(User $user, Institution $institution): bool
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
     * @param  Institution  $institution
     * @return bool
     */
    public function update(User $user, Institution $institution): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Only once it has no admission requirements left (FK restrict).
     *
     * @param  User  $user
     * @param  Institution  $institution
     * @return bool
     */
    public function delete(User $user, Institution $institution): bool
    {
        return $user->isStaffOrAdmin() && ! $institution->admissionRequirements()->exists();
    }
}

<?php

namespace App\Policies;

use App\Models\AdmissionRequirement;
use App\Models\User;

/**
 * Cut-off marks and credit requirements in the back office: staff and
 * admins, including the CSV import. Bulk delete is admin only.
 */
class AdmissionRequirementPolicy
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
     * @param  AdmissionRequirement  $admission_requirement
     * @return bool
     */
    public function view(User $user, AdmissionRequirement $admission_requirement): bool
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
     * Bulk CSV import (ImportAdmissionRequirements).
     *
     * @param  User  $user
     * @return bool
     */
    public function import(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * @param  User  $user
     * @param  AdmissionRequirement  $admission_requirement
     * @return bool
     */
    public function update(User $user, AdmissionRequirement $admission_requirement): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * @param  User  $user
     * @param  AdmissionRequirement  $admission_requirement
     * @return bool
     */
    public function delete(User $user, AdmissionRequirement $admission_requirement): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Clearing many rows at once (e.g. removing the sample data) is admin only.
     *
     * @param  User  $user
     * @return bool
     */
    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}

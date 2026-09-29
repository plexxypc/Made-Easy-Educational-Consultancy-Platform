<?php

namespace App\Policies;

use App\Models\ServiceCategory;
use App\Models\User;

/**
 * Back-office catalogue management: admin only. The public catalogue pages
 * read categories without going through this policy.
 */
class ServiceCategoryPolicy
{
    /**
     * @param  User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @param  ServiceCategory  $service_category
     * @return bool
     */
    public function view(User $user, ServiceCategory $service_category): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @param  ServiceCategory  $service_category
     * @return bool
     */
    public function update(User $user, ServiceCategory $service_category): bool
    {
        return $user->isAdmin();
    }

    /**
     * Categories that still contain services can't be deleted (FK restrict).
     *
     * @param  User  $user
     * @param  ServiceCategory  $service_category
     * @return bool
     */
    public function delete(User $user, ServiceCategory $service_category): bool
    {
        return $user->isAdmin() && ! $service_category->services()->exists();
    }
}

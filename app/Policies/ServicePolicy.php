<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/**
 * Back-office service management: admin only. The public catalogue pages
 * read active services without going through this policy.
 */
class ServicePolicy
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
     * @param  Service  $service
     * @return bool
     */
    public function view(User $user, Service $service): bool
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
     * @param  Service  $service
     * @return bool
     */
    public function update(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }

    /**
     * Services with orders can't be deleted (FK restrict); deactivate them instead.
     *
     * @param  User  $user
     * @param  Service  $service
     * @return bool
     */
    public function delete(User $user, Service $service): bool
    {
        return $user->isAdmin() && ! $service->orders()->withTrashed()->exists();
    }
}

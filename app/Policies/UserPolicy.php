<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management in the back office: admins can list users and change
 * roles. Accounts are created by registration, not from the admin panel.
 */
class UserPolicy
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
     * @param  User  $target_user
     * @return bool
     */
    public function view(User $user, User $target_user): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * No generic edit form; roles change via changeRole().
     *
     * @param  User  $user
     * @param  User  $target_user
     * @return bool
     */
    public function update(User $user, User $target_user): bool
    {
        return false;
    }

    /**
     * @param  User  $user
     * @param  User  $target_user
     * @return bool
     */
    public function delete(User $user, User $target_user): bool
    {
        return false;
    }

    /**
     * Change another user's role. Admins can't change their own (lockout guard).
     *
     * @param  User  $user
     * @param  User  $target_user
     * @return bool
     */
    public function changeRole(User $user, User $target_user): bool
    {
        return $user->isAdmin() && ! $target_user->is($user);
    }
}

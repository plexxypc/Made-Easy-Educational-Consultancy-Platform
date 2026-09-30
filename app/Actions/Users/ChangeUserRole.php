<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\User;

/**
 * Change a user's role (admin only).
 *
 * An admin may not change their own role, which prevents accidentally locking
 * themselves (and possibly everyone) out of the back office.
 */
class ChangeUserRole
{
    /**
     * Apply the role change.
     *
     * @param  User  $target_user  User whose role changes.
     * @param  UserRole  $new_role  New role.
     * @param  User  $actor  Admin making the change.
     * @return User The updated user.
     *
     * @throws BusinessRuleException If the actor isn't an admin or targets themselves.
     */
    public function handle(User $target_user, UserRole $new_role, User $actor): User
    {
        if (! $actor->isAdmin()) {
            throw new BusinessRuleException('Only admins can change roles.');
        }

        if ($target_user->is($actor) && $new_role !== $target_user->role) {
            throw new BusinessRuleException('You cannot change your own role. Ask another admin.');
        }

        $target_user->role = $new_role;
        $target_user->save();

        return $target_user;
    }
}

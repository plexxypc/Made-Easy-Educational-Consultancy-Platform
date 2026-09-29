<?php

namespace App\Models\Concerns;

use App\Enums\UserRole;

/**
 * Role checks for the User model.
 *
 * Usable anywhere a user instance is available:
 *   - Blade/Livewire:   auth()->user()->hasRole('admin', 'staff')  or  @role('admin') ... @endrole
 *   - Routes:           ->middleware('role:admin,staff')
 *   - Filament/policies: $user->isStaffOrAdmin()
 *
 * @property UserRole $role
 */
trait HasRole
{
    /**
     * Initialize the trait: new, unsaved users default to the customer role,
     * matching the database column default.
     *
     * @return void
     */
    public function initializeHasRole(): void
    {
        $this->attributes['role'] ??= UserRole::Customer->value;
        $this->mergeCasts(['role' => UserRole::class]);
    }

    /**
     * Determine whether the user has any of the given roles.
     *
     * @param  UserRole|string  ...$roles  Enum cases or raw values ('customer', 'staff', 'admin').
     * @return bool
     */
    public function hasRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $expected_role = $role instanceof UserRole ? $role : UserRole::tryFrom($role);

            if ($expected_role !== null && $this->role === $expected_role) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user is an admin.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user is a staff member.
     *
     * @return bool
     */
    public function isStaff(): bool
    {
        return $this->hasRole(UserRole::Staff);
    }

    /**
     * Determine whether the user is a customer.
     *
     * @return bool
     */
    public function isCustomer(): bool
    {
        return $this->hasRole(UserRole::Customer);
    }

    /**
     * Determine whether the user is staff or admin (i.e. back-office access).
     *
     * @return bool
     */
    public function isStaffOrAdmin(): bool
    {
        return $this->hasRole(UserRole::Staff, UserRole::Admin);
    }
}

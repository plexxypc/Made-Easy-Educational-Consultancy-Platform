<?php

namespace App\Policies;

use App\Models\ResultCheckerPin;
use App\Models\User;

/**
 * Result checker PIN stock.
 *
 *  - The stock screen and uploads are admin only.
 *  - Revealing one PIN is allowed to staff and admin, only for a PIN already
 *    allocated to an order, and every reveal is audited (LogPinAccess).
 *  - Only never-allocated PINs may be deleted (e.g. a mistaken upload).
 *  - PINs are never edited.
 */
class ResultCheckerPinPolicy
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
     * @param  ResultCheckerPin  $result_checker_pin
     * @return bool
     */
    public function view(User $user, ResultCheckerPin $result_checker_pin): bool
    {
        return $user->isAdmin();
    }

    /**
     * See the plaintext PIN on the order it was allocated to.
     *
     * @param  User  $user
     * @param  ResultCheckerPin  $result_checker_pin
     * @return bool
     */
    public function reveal(User $user, ResultCheckerPin $result_checker_pin): bool
    {
        return $user->isStaffOrAdmin() && $result_checker_pin->order_id !== null;
    }

    /**
     * Upload PINs.
     *
     * @param  User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @param  ResultCheckerPin  $result_checker_pin
     * @return bool
     */
    public function update(User $user, ResultCheckerPin $result_checker_pin): bool
    {
        return false;
    }

    /**
     * @param  User  $user
     * @param  ResultCheckerPin  $result_checker_pin
     * @return bool
     */
    public function delete(User $user, ResultCheckerPin $result_checker_pin): bool
    {
        return $user->isAdmin() && $result_checker_pin->order_id === null;
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

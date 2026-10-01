<?php

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

/**
 * FAQs: admins only. Edits are live on the public site at once, and the
 * answers are statements from the business (prices, timelines), so the
 * same rule as publishing Updates posts applies: admins decide what goes
 * live. Staff can still read them.
 */
class FaqPolicy
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
     * @param  Faq  $faq
     * @return bool
     */
    public function view(User $user, Faq $faq): bool
    {
        return $user->isStaffOrAdmin();
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
     * @param  Faq  $faq
     * @return bool
     */
    public function update(User $user, Faq $faq): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @param  Faq  $faq
     * @return bool
     */
    public function delete(User $user, Faq $faq): bool
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

    /**
     * Drag-and-drop reordering in the table.
     *
     * @param  User  $user
     * @return bool
     */
    public function reorder(User $user): bool
    {
        return $user->isAdmin();
    }
}

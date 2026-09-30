<?php

namespace App\Policies;

use App\Models\ConsultationSlot;
use App\Models\User;

/**
 * Consultation availability is managed by admins only. Staff see bookings
 * (ConsultationBookingPolicy), not the slot screen.
 *
 *  - Slots are never edited once created: cancel and add a new one.
 *  - A slot nobody ever booked may be deleted (e.g. a typo); any other is
 *    cancelled instead, which keeps the booking history.
 */
class ConsultationSlotPolicy
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
     * @param  ConsultationSlot  $consultation_slot
     * @return bool
     */
    public function view(User $user, ConsultationSlot $consultation_slot): bool
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
     * @param  ConsultationSlot  $consultation_slot
     * @return bool
     */
    public function update(User $user, ConsultationSlot $consultation_slot): bool
    {
        return false;
    }

    /**
     * @param  User  $user
     * @param  ConsultationSlot  $consultation_slot
     * @return bool
     */
    public function cancel(User $user, ConsultationSlot $consultation_slot): bool
    {
        return $user->isAdmin() && ! $consultation_slot->isCancelled();
    }

    /**
     * @param  User  $user
     * @param  ConsultationSlot  $consultation_slot
     * @return bool
     */
    public function delete(User $user, ConsultationSlot $consultation_slot): bool
    {
        return $user->isAdmin() && ! $consultation_slot->bookings()->exists();
    }

    /**
     * @param  User  $user
     * @return bool
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }
}

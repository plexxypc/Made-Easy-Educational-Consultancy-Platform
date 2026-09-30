<?php

namespace App\Policies;

use App\Models\ConsultationBooking;
use App\Models\User;

/**
 * Consultation bookings in the back office: staff and admin may view and
 * manage them (complete, no-show, cancel, reschedule, meeting link).
 * Which changes are valid is decided by the App\Actions\Consultations
 * classes, not here. Bookings are created only by customers booking and
 * paying, and are never edited directly or deleted.
 */
class ConsultationBookingPolicy
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
     * @param  ConsultationBooking  $consultation_booking
     * @return bool
     */
    public function view(User $user, ConsultationBooking $consultation_booking): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Complete / no-show / cancel / reschedule / meeting link.
     *
     * @param  User  $user
     * @param  ConsultationBooking  $consultation_booking
     * @return bool
     */
    public function manage(User $user, ConsultationBooking $consultation_booking): bool
    {
        return $user->isStaffOrAdmin() && ! $consultation_booking->status->isFinal();
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
     * @param  User  $user
     * @param  ConsultationBooking  $consultation_booking
     * @return bool
     */
    public function update(User $user, ConsultationBooking $consultation_booking): bool
    {
        return false;
    }

    /**
     * @param  User  $user
     * @param  ConsultationBooking  $consultation_booking
     * @return bool
     */
    public function delete(User $user, ConsultationBooking $consultation_booking): bool
    {
        return false;
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

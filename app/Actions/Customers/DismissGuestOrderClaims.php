<?php

namespace App\Actions\Customers;

use App\Models\User;

/**
 * "Not now" on the guest-order claim prompt, remembered on the user
 * (users.guest_claims_dismissed_at) so it holds across sessions and devices.
 * Nothing is linked or changed on the orders themselves. See
 * FindClaimableGuestOrders for what a dismissal hides.
 */
class DismissGuestOrderClaims
{
    /**
     * Hide the prompt for the guest orders placed up to now.
     *
     * @param  User  $user
     * @return void
     */
    public function handle(User $user): void
    {
        $user->forceFill(['guest_claims_dismissed_at' => now()])->save();
    }

    /**
     * Show the dismissed orders in the prompt again (customer's own choice).
     *
     * @param  User  $user
     * @return void
     */
    public function restore(User $user): void
    {
        $user->forceFill(['guest_claims_dismissed_at' => null])->save();
    }
}

<?php

namespace App\Actions\Customers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Guest orders a signed-in customer may add to their account.
 *
 * Business rules (guest order claiming):
 *  - Only for an account whose email is VERIFIED; nothing is offered before.
 *  - Only guest orders (no account yet) whose guest_email is the account's
 *    email. Compared after trimming, ignoring letter case: "Ada@Example.com"
 *    and "ada@example.com" are the same mailbox. Assumption: the request said
 *    "exact match"; case is the only difference tolerated.
 *  - Never by phone number, and never by reference + contact details typed
 *    in by the user: that would let anyone who knows those details take over
 *    someone else's order (the risk avoided in guest tracking).
 *  - Deleted orders are never offered.
 *  - Finding orders links nothing: the customer must accept (ClaimGuestOrders).
 *  - "Not now" (DismissGuestOrderClaims) stops the prompt for the orders it
 *    was shown for: only guest orders placed AFTER the dismissal bring it
 *    back. Assumption: a new guest order is a new question worth asking.
 *    The customer can bring the dismissed ones back themselves (restore).
 */
class FindClaimableGuestOrders
{
    /**
     * Orders to show in the claim prompt.
     *
     * @param  User  $user
     * @return Collection<int, Order> Newest first; empty if the email isn't verified.
     */
    public function handle(User $user): Collection
    {
        if (! $user->hasVerifiedEmail()) {
            return new Collection;
        }

        return self::query_for($user)
            ->when($user->guest_claims_dismissed_at, fn (Builder $query) => $query->where('created_at', '>', $user->guest_claims_dismissed_at))
            ->with('service')
            ->latest()
            ->latest('id')
            ->get();
    }

    /**
     * How many matching guest orders are hidden by a "Not now".
     *
     * @param  User  $user
     * @return int
     */
    public function dismissed_count(User $user): int
    {
        if (! $user->hasVerifiedEmail() || $user->guest_claims_dismissed_at === null) {
            return 0;
        }

        return self::query_for($user)->where('created_at', '<=', $user->guest_claims_dismissed_at)->count();
    }

    /**
     * The claimable-orders query (without the verification or dismissal checks).
     *
     * @param  User  $user
     * @return Builder<Order>
     */
    public static function query_for(User $user): Builder
    {
        return Order::query()
            ->whereNull('user_id')
            ->whereNotNull('guest_email')
            ->whereRaw('lower(trim(guest_email)) = ?', [self::normalise_email($user->email)]);
    }

    /**
     * @param  string  $email
     * @return string
     */
    public static function normalise_email(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}

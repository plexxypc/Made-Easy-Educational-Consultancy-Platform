<?php

namespace App\Actions\Customers;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Add guest orders to the signed-in customer's account, after they accepted
 * the dashboard prompt.
 *
 * Business rules:
 *  - Only the orders the customer was shown (their ids are posted back), and
 *    only those that are STILL claimable for them (FindClaimableGuestOrders:
 *    verified email, same email, no account yet). Anything else in the list
 *    (another customer's order, one already claimed, a made-up id) is ignored.
 *  - Idempotent: each order is locked and re-checked, so a double submit or
 *    two tabs claim each order once; the second claim finds nothing to do.
 *  - A claimed order is an ordinary account order: user_id is set (so
 *    OrderPolicy::track lets the owner in without a lookup) and claimed_at
 *    records when. The guest_* contact details are kept as history.
 */
class ClaimGuestOrders
{
    /**
     * @param  User  $user
     * @param  array<int, int|string>  $order_ids  Orders shown in the prompt.
     * @return int Orders added to the account now.
     *
     * @throws BusinessRuleException If the account's email isn't verified.
     */
    public function handle(User $user, array $order_ids): int
    {
        if (! $user->hasVerifiedEmail()) {
            throw new BusinessRuleException('Verify your email address before adding requests to your account.');
        }

        $order_ids = array_values(array_unique(array_filter(array_map('intval', $order_ids), fn (int $id): bool => $id > 0)));

        if ($order_ids === []) {
            return 0;
        }

        $claimed = DB::transaction(function () use ($user, $order_ids): array {
            $orders = FindClaimableGuestOrders::query_for($user)
                ->whereKey($order_ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                $order->forceFill(['user_id' => $user->id, 'claimed_at' => now()])->save();
            }

            return $orders->modelKeys();
        });

        if ($claimed !== []) {
            Log::info('Guest orders claimed by their account owner.', ['user_id' => $user->id, 'order_ids' => $claimed]);
        }

        return count($claimed);
    }
}

<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Who may open an order's tracking page.
 *
 *  - The signed-in customer who owns it (OrderPolicy::track).
 *  - A visitor who proved reference + email/phone on the lookup form in this
 *    browser session. That proof is kept in the session only, so it ends when
 *    the session expires (config/session.php lifetime) or on logout.
 *
 * Staff don't use this page; they manage orders in the back office.
 */
class TrackedOrders
{
    /**
     * @var string
     */
    private const SESSION_KEY = 'tracked_order_ids';

    /**
     * Remember that this session proved it may view the order.
     *
     * @param  Request  $request
     * @param  Order  $order
     * @return void
     */
    public static function grant(Request $request, Order $order): void
    {
        $order_ids = self::granted_ids($request);

        if (! in_array($order->id, $order_ids, true)) {
            $request->session()->put(self::SESSION_KEY, [...$order_ids, $order->id]);
        }
    }

    /**
     * Whether the current visitor may view (and respond to) the order.
     *
     * @param  Request  $request
     * @param  Order  $order
     * @return bool
     */
    public static function allows(Request $request, Order $order): bool
    {
        $user = $request->user();

        if ($user !== null && $user->can('track', $order)) {
            return true;
        }

        return in_array($order->id, self::granted_ids($request), true);
    }

    /**
     * Whether access comes from the account (owner) rather than a lookup.
     *
     * @param  Request  $request
     * @param  Order  $order
     * @return bool
     */
    public static function is_owner(Request $request, Order $order): bool
    {
        return $request->user()?->can('track', $order) ?? false;
    }

    /**
     * @param  Request  $request
     * @return array<int, int>
     */
    private static function granted_ids(Request $request): array
    {
        return array_map('intval', (array) $request->session()->get(self::SESSION_KEY, []));
    }
}

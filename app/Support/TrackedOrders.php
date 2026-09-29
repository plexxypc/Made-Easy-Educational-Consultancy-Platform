<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Who may open an order's tracking page.
 *
 *  - The signed-in customer who owns it (OrderPolicy::track).
 *  - A visitor who proved reference + email/phone on the lookup form in this
 *    browser session, and has used the order page within the last
 *    config('madeeasy.order_lookups.guest_idle_minutes') minutes (default 30).
 *    Each allowed request resets the timer; after that the visitor must look
 *    the order up again. So a shared or borrowed phone doesn't stay signed in
 *    to someone else's order. The proof also ends with the session or on logout.
 *
 * Staff don't use this page; they manage orders in the back office.
 */
class TrackedOrders
{
    /**
     * Session value: [order_id => unix timestamp of last activity].
     *
     * @var string
     */
    private const SESSION_KEY = 'tracked_orders';

    /**
     * Remember that this session proved it may view the order.
     *
     * @param  Request  $request
     * @param  Order  $order
     * @return void
     */
    public static function grant(Request $request, Order $order): void
    {
        $grants = self::active_grants($request);
        $grants[$order->id] = now()->getTimestamp();

        $request->session()->put(self::SESSION_KEY, $grants);
    }

    /**
     * Whether the current visitor may view (and respond to / pay for) the order.
     * A guest grant that is still active is refreshed (sliding idle timeout).
     *
     * @param  Request  $request
     * @param  Order  $order
     * @return bool
     */
    public static function allows(Request $request, Order $order): bool
    {
        if (self::is_owner($request, $order)) {
            return true;
        }

        $grants = self::active_grants($request);

        if (! array_key_exists($order->id, $grants)) {
            $request->session()->put(self::SESSION_KEY, $grants);

            return false;
        }

        $grants[$order->id] = now()->getTimestamp();
        $request->session()->put(self::SESSION_KEY, $grants);

        return true;
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
     * Grants used within the idle timeout; expired ones are dropped.
     *
     * @param  Request  $request
     * @return array<int, int>
     */
    private static function active_grants(Request $request): array
    {
        $oldest_allowed = now()->subMinutes((int) config('madeeasy.order_lookups.guest_idle_minutes'))->getTimestamp();
        $grants = (array) $request->session()->get(self::SESSION_KEY, []);

        return array_filter($grants, fn (mixed $last_active): bool => is_int($last_active) && $last_active >= $oldest_allowed);
    }
}

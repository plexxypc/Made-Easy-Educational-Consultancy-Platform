<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Customers: only their own orders. Staff/admin: all orders. Deleting: admin only.
 *
 * Guest orders (user_id = null) are visible to staff/admin only through this
 * policy. Guests reach their own order via the reference + contact lookup
 * (App\Support\TrackedOrders), not via a policy check.
 */
class OrderPolicy
{
    /**
     * Any signed-in user may list orders; the list itself must be scoped with
     * Order::query()->visibleTo($user).
     *
     * @param  User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * View a single order.
     *
     * @param  User  $user
     * @param  Order  $order
     * @return bool
     */
    public function view(User $user, Order $order): bool
    {
        return $user->isStaffOrAdmin() || $order->isOwnedBy($user);
    }

    /**
     * Use the customer tracking page ("My Requests" detail, respond to needs action).
     * Owner only: staff/admin work on orders in the back office instead.
     *
     * @param  User  $user
     * @param  Order  $order
     * @return bool
     */
    public function track(User $user, Order $order): bool
    {
        return $order->isOwnedBy($user) && ! $order->trashed();
    }

    /**
     * Any signed-in user may place an order.
     *
     * @param  User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Update an order (status changes, notes). Back office only; customers
     * change their orders through dedicated actions, not generic updates.
     *
     * @param  User  $user
     * @param  Order  $order
     * @return bool
     */
    public function update(User $user, Order $order): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Use the status actions (Mark In Progress / Completed / Needs Action / Cancel).
     * Which transitions are valid is decided by ChangeOrderStatus, not here.
     *
     * @param  User  $user
     * @param  Order  $order
     * @return bool
     */
    public function changeStatus(User $user, Order $order): bool
    {
        return $user->isStaffOrAdmin() && ! $order->trashed();
    }

    /**
     * "Deliver PIN manually" on a paid result checker order that is still owed PINs.
     * Which orders qualify is decided by Order::undeliveredPinCount(), not here.
     *
     * @param  User  $user
     * @param  Order  $order
     * @return bool
     */
    public function deliverPins(User $user, Order $order): bool
    {
        return $user->isStaffOrAdmin() && ! $order->trashed();
    }

    /**
     * Set the staff-determined total of a quote-type order.
     *
     * @param  User  $user
     * @param  Order  $order
     * @return bool
     */
    public function setTotal(User $user, Order $order): bool
    {
        return $user->isAdmin() && ! $order->trashed();
    }

    /**
     * Soft-delete an order.
     *
     * @param  User  $user
     * @param  Order  $order
     * @return bool
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    /**
     * Restore a soft-deleted order.
     *
     * @param  User  $user
     * @param  Order  $order
     * @return bool
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    /**
     * Permanently delete an order.
     *
     * @param  User  $user
     * @param  Order  $order
     * @return bool
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}

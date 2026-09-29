<?php

namespace App\Policies;

use App\Models\OrderDocument;
use App\Models\User;

/**
 * Customers: only documents on their own orders. Staff/admin: all documents
 * (every staff/admin view is audited; see LogDocumentAccess). Deleting: admin only.
 *
 * Upload permissions (create) are added with the upload feature.
 */
class OrderDocumentPolicy
{
    /**
     * Any signed-in user may list documents; lists must be scoped to visible orders.
     *
     * @param  User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * View (open/download) a single document.
     *
     * @param  User  $user
     * @param  OrderDocument  $order_document
     * @return bool
     */
    public function view(User $user, OrderDocument $order_document): bool
    {
        if ($user->isStaffOrAdmin()) {
            return true;
        }

        $order = $order_document->order;

        return $order !== null && $order->isOwnedBy($user);
    }

    /**
     * Delete a document.
     *
     * @param  User  $user
     * @param  OrderDocument  $order_document
     * @return bool
     */
    public function delete(User $user, OrderDocument $order_document): bool
    {
        return $user->isAdmin();
    }
}

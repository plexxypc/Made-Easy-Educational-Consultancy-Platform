<?php

namespace App\Actions\Documents;

use App\Models\DocumentAccessLog;
use App\Models\OrderDocument;
use App\Models\User;

/**
 * Records that a user opened an order document (project security rule 5).
 *
 * Only staff/admin views are logged; customers opening their own uploads are
 * not an access-to-someone-else's-data event. Call this from every code path
 * that serves document contents (controller now, Filament later).
 */
class LogDocumentAccess
{
    /**
     * Write an audit row if the viewer is staff/admin.
     *
     * @param  OrderDocument  $order_document  The document being viewed.
     * @param  User  $viewer  The authenticated user viewing it.
     * @param  string|null  $ip_address  Request IP, if available.
     * @return DocumentAccessLog|null  The log row, or null if no log was required.
     */
    public function handle(OrderDocument $order_document, User $viewer, ?string $ip_address = null): ?DocumentAccessLog
    {
        if (! $viewer->isStaffOrAdmin()) {
            return null;
        }

        return DocumentAccessLog::create([
            'order_document_id' => $order_document->id,
            'user_id' => $viewer->id,
            'ip_address' => $ip_address,
        ]);
    }
}

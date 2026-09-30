<?php

namespace App\Actions\Pins;

use App\Models\DocumentAccessLog;
use App\Models\ResultCheckerPin;
use App\Models\User;

/**
 * Records that a user saw a result checker PIN in plaintext (project security
 * rule 5), in the same audit table and with the same rules as LogDocumentAccess.
 *
 * Only staff/admin views are logged; a customer seeing the PIN they bought is
 * not an access-to-someone-else's-data event. Call this from every code path
 * that shows a PIN to staff (the "Show PIN" action on the order screen).
 */
class LogPinAccess
{
    /**
     * Write an audit row if the viewer is staff/admin.
     *
     * @param  ResultCheckerPin  $result_checker_pin  The PIN being viewed.
     * @param  User  $viewer  The authenticated user viewing it.
     * @param  string|null  $ip_address  Request IP, if available.
     * @return DocumentAccessLog|null The log row, or null if no log was required.
     */
    public function handle(ResultCheckerPin $result_checker_pin, User $viewer, ?string $ip_address = null): ?DocumentAccessLog
    {
        if (! $viewer->isStaffOrAdmin()) {
            return null;
        }

        return DocumentAccessLog::create([
            'result_checker_pin_id' => $result_checker_pin->id,
            'user_id' => $viewer->id,
            'ip_address' => $ip_address,
        ]);
    }
}

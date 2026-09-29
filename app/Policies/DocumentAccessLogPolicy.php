<?php

namespace App\Policies;

use App\Models\DocumentAccessLog;
use App\Models\User;

/**
 * The document access audit log is read-only and admin-only. Rows are only
 * ever written by LogDocumentAccess.
 */
class DocumentAccessLogPolicy
{
    /**
     * @param  User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @param  DocumentAccessLog  $document_access_log
     * @return bool
     */
    public function view(User $user, DocumentAccessLog $document_access_log): bool
    {
        return $user->isAdmin();
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
     * @param  DocumentAccessLog  $document_access_log
     * @return bool
     */
    public function update(User $user, DocumentAccessLog $document_access_log): bool
    {
        return false;
    }

    /**
     * @param  User  $user
     * @param  DocumentAccessLog  $document_access_log
     * @return bool
     */
    public function delete(User $user, DocumentAccessLog $document_access_log): bool
    {
        return false;
    }
}

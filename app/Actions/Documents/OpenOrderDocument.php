<?php

namespace App\Actions\Documents;

use App\Models\OrderDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve an order document from the private disk and audit the access.
 *
 * Shared by OrderDocumentController (GET /documents/{id}) and the Filament
 * order screen, so both paths write identical document_access_logs rows via
 * LogDocumentAccess. Callers MUST authorise ('view' policy) before calling.
 */
class OpenOrderDocument
{
    /**
     * @param  LogDocumentAccess  $log_document_access
     */
    public function __construct(private readonly LogDocumentAccess $log_document_access) {}

    /**
     * Log the view (for staff/admin) and stream the file.
     *
     * @param  OrderDocument  $order_document  Document to serve.
     * @param  User  $viewer  Authenticated, already-authorised viewer.
     * @param  string|null  $ip_address  Request IP for the audit row.
     * @param  bool  $as_download  true = attachment, false = inline (browser preview).
     * @return StreamedResponse
     */
    public function handle(OrderDocument $order_document, User $viewer, ?string $ip_address, bool $as_download = false): StreamedResponse
    {
        $disk = Storage::disk(OrderDocument::DISK);

        abort_unless($disk->exists($order_document->stored_path), Response::HTTP_NOT_FOUND);

        $this->log_document_access->handle($order_document, $viewer, $ip_address);

        return $disk->response(
            $order_document->stored_path,
            $order_document->original_filename,
            [
                'Content-Type' => $order_document->mime_type,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $as_download ? 'attachment' : 'inline',
        );
    }
}

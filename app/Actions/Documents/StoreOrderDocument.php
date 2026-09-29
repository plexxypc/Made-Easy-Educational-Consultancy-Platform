<?php

namespace App\Actions\Documents;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Store a customer's uploaded file as an order_document on the private disk.
 *
 * This is the only place uploads are written. Files are named by UUID with an
 * extension derived from the detected content type (never the client's
 * filename), under order-documents/{order_id}/ on OrderDocument::DISK. They are
 * served back only through OpenOrderDocument (policy-checked, audited).
 */
class StoreOrderDocument
{
    /**
     * @param  Order  $order
     * @param  UploadedFile  $file  Already validated by the form request.
     * @param  string|null  $field_key  Service "file" field this answers.
     * @return OrderDocument
     *
     * @throws BusinessRuleException  If the detected MIME type is not allowed.
     */
    public function handle(Order $order, UploadedFile $file, ?string $field_key = null): OrderDocument
    {
        // Re-check server-side from the file contents, not the client-sent type.
        $mime_type = (string) $file->getMimeType();

        if (! in_array($mime_type, config('madeeasy.uploads.allowed_mime_types'), true)) {
            throw new BusinessRuleException("Files of type [{$mime_type}] are not accepted.");
        }

        $extension = $file->guessExtension() ?? 'bin';
        $stored_path = $file->storeAs(
            "order-documents/{$order->id}",
            Str::uuid().'.'.$extension,
            OrderDocument::DISK,
        );

        if ($stored_path === false) {
            throw new BusinessRuleException('The file could not be saved. Please try again.');
        }

        return $order->documents()->create([
            'field_key' => $field_key,
            'original_filename' => self::safe_filename($file->getClientOriginalName(), $extension),
            'stored_path' => $stored_path,
            'mime_type' => $mime_type,
            'size' => (int) $file->getSize(),
        ]);
    }

    /**
     * Display/download name: the client's name without path parts or control
     * characters, capped in length, keeping a sensible extension.
     *
     * @param  string  $client_filename
     * @param  string  $extension
     * @return string
     */
    private static function safe_filename(string $client_filename, string $extension): string
    {
        $name = pathinfo(basename(str_replace('\\', '/', $client_filename)), PATHINFO_FILENAME);
        $name = Str::limit(trim((string) preg_replace('/[^\pL\pN _.()-]+/u', '', $name)), 150, '');

        return ($name !== '' ? $name : 'document').'.'.$extension;
    }
}

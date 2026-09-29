<?php

namespace App\Http\Requests;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * Customer response to a "needs action" order: a note and/or documents.
 *
 * Access as for the order page (TrackedOrderRequest). Uploads use the same
 * type/size limits as the request form; the MIME type is re-checked from file
 * contents in StoreOrderDocument.
 */
class RespondToOrderRequest extends TrackedOrderRequest
{
    /**
     * Assumption: up to 5 documents per response keeps a single POST within
     * post_max_size (26M) at the default 5 MB per file.
     *
     * @var int
     */
    public const MAX_DOCUMENTS = 5;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:5000', 'required_without:documents'],
            'documents' => ['nullable', 'array', 'max:'.self::MAX_DOCUMENTS],
            'documents.*' => [
                'required',
                File::types(config('madeeasy.uploads.allowed_extensions'))
                    ->max(config('madeeasy.uploads.max_kilobytes')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'note' => 'message',
            'documents' => 'documents',
            'documents.*' => 'document',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required_without' => 'Add a message or upload at least one document.',
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedDocuments(): array
    {
        return array_values(array_filter(
            (array) $this->file('documents', []),
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }
}

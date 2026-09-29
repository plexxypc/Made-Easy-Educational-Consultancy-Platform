<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Support\TrackedOrders;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * Customer response to a "needs action" order: a note and/or documents.
 *
 * Uploads use the same type/size limits as the request form; the MIME type is
 * re-checked from file contents in StoreOrderDocument.
 */
class RespondToOrderRequest extends FormRequest
{
    /**
     * Assumption: up to 5 documents per response keeps a single POST within
     * post_max_size (26M) at the default 5 MB per file.
     *
     * @var int
     */
    public const MAX_DOCUMENTS = 5;

    /**
     * Owner of the order, or a visitor who looked it up in this session.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return TrackedOrders::allows($this, $this->order());
    }

    /**
     * Not allowed (e.g. the guest's session expired): back to the lookup form
     * rather than a bare 403, without saving anything.
     *
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()
                ->route('orders.track')
                ->withInput(['reference' => (string) $this->order()->id])
                ->with('status', 'Please look up your request again before responding.'),
        );
    }

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

    /**
     * The order being responded to (from the route).
     *
     * @return Order
     */
    public function order(): Order
    {
        return $this->route('order');
    }
}

<?php

namespace App\Actions\Orders;

use App\Actions\Documents\StoreOrderDocument;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\ServiceRequestReceivedMail;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Create an order: from the public "Request this service" form, from
 * "Buy Result Checker", or from /book-consultation (both of which then go
 * straight to payment). One code path, so every order is created and
 * tracked the same way.
 *
 * Business rules:
 *  - The order starts in pending_payment. Nothing here takes or implies payment.
 *  - total_amount is the service price × quantity (0 for quote services,
 *    which then await an admin "Set Total").
 *  - Only result checkers may be ordered in quantities above 1, up to
 *    config('madeeasy.pins.max_quantity_per_order'). Each unit gets its own PIN.
 *  - Contact details always go in guest_*; user_id is set only when a signed-in
 *    customer submits (staff/admin testing the site don't become owners).
 *  - Uploaded files become order_documents via StoreOrderDocument. If anything
 *    fails, the order is rolled back and files already written are deleted.
 *  - The "request received" email goes to generic requests only. Result
 *    checker and consultation customers go straight to Paystack, and their
 *    receipt plus PIN / booking email already confirm the order; a third
 *    email saying "not paid yet" only confused them.
 */
class CreateServiceRequest
{
    /**
     * @param  StoreOrderDocument  $store_order_document
     */
    public function __construct(
        private readonly StoreOrderDocument $store_order_document,
    ) {}

    /**
     * @param  Service  $service
     * @param  array{name: string, email: string, phone: string}  $contact
     * @param  array<string, mixed>  $answers  Non-file answers keyed by field key.
     * @param  array<string, UploadedFile>  $files  Uploaded files keyed by field key.
     * @param  User|null  $submitted_by  Signed-in user, if any.
     * @param  int  $quantity  Units (result checkers only; otherwise 1).
     * @return Order
     *
     * @throws BusinessRuleException
     */
    public function handle(Service $service, array $contact, array $answers, array $files = [], ?User $submitted_by = null, int $quantity = 1): Order
    {
        if (! ($service->acceptsRequests() || self::is_purchasable_result_checker($service) || self::is_bookable_consultation($service))) {
            throw new BusinessRuleException('This service is not currently available to request.');
        }

        $max_quantity = $service->isResultChecker() ? (int) config('madeeasy.pins.max_quantity_per_order') : 1;

        if ($quantity < 1 || $quantity > $max_quantity) {
            throw new BusinessRuleException($max_quantity === 1
                ? 'This service can only be requested one at a time.'
                : "Choose a quantity between 1 and {$max_quantity}.");
        }

        $stored_paths = [];

        try {
            $order = DB::transaction(function () use ($service, $contact, $answers, $files, $submitted_by, $quantity, &$stored_paths): Order {
                $order = new Order([
                    'service_id' => $service->id,
                    'guest_name' => $contact['name'],
                    'guest_email' => $contact['email'],
                    'guest_phone' => $contact['phone'],
                ]);

                $order->forceFill([
                    'user_id' => $submitted_by?->isCustomer() ? $submitted_by->id : null,
                    'status' => OrderStatus::PendingPayment,
                    'quantity' => $quantity,
                    'total_amount' => $service->price * $quantity,
                ])->save();

                foreach ($files as $field_key => $file) {
                    $document = $this->store_order_document->handle($order, $file, $field_key);
                    $stored_paths[] = $document->stored_path;
                    $answers[$field_key] = $document->original_filename;
                }

                $order->field_answers = $answers;
                $order->save();

                return $order;
            });
        } catch (Throwable $exception) {
            Storage::disk(OrderDocument::DISK)->delete($stored_paths);

            throw $exception;
        }

        if (! $service->isResultChecker() && ! $service->isConsultation()) {
            Mail::to($order->guest_email)->queue(new ServiceRequestReceivedMail($order));
        }

        return $order;
    }

    /**
     * Active, priced result checker (sold through "Buy Result Checker").
     *
     * @param  Service  $service
     * @return bool
     */
    private static function is_purchasable_result_checker(Service $service): bool
    {
        return $service->is_active && $service->isResultChecker() && $service->price > 0;
    }

    /**
     * Active, priced consultation (booked through /book-consultation, which
     * claims the slot in the same transaction; see BookConsultation).
     *
     * @param  Service  $service
     * @return bool
     */
    private static function is_bookable_consultation(Service $service): bool
    {
        return $service->is_active && $service->isConsultation() && $service->price > 0;
    }
}

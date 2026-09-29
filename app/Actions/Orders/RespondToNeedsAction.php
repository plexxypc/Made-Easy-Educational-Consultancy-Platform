<?php

namespace App\Actions\Orders;

use App\Actions\Documents\StoreOrderDocument;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Mail\NeedsActionResponseReceivedMail;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderStatusChange;
use App\Models\User;
use App\Notifications\CustomerRespondedToOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The customer answers a "needs action" request with a note and/or documents.
 *
 * Business rules:
 *  - Only an order currently in needs_action can be responded to.
 *  - At least a note or one document is required.
 *  - Documents are stored through StoreOrderDocument (same private path as the
 *    request form); the note and the transition are recorded as a customer
 *    OrderStatusChange (by_customer = true).
 *  - The order returns to the status it had before (Order::statusBeforeNeedsAction()).
 *  - All staff and admins get a notification (Filament bell, optional email),
 *    and the customer gets a queued confirmation email.
 *
 * Caller must have checked access (TrackedOrders::allows()).
 */
class RespondToNeedsAction
{
    /**
     * @param  StoreOrderDocument  $store_order_document
     */
    public function __construct(
        private readonly StoreOrderDocument $store_order_document,
    ) {}

    /**
     * @param  Order  $order
     * @param  string|null  $note  The customer's message.
     * @param  array<int, UploadedFile>  $files  Additional documents.
     * @param  User|null  $responder  Signed-in owner, or null for a guest.
     * @return OrderStatusChange
     *
     * @throws BusinessRuleException
     */
    public function handle(Order $order, ?string $note, array $files = [], ?User $responder = null): OrderStatusChange
    {
        $note = filled($note) ? trim($note) : null;

        if ($note === null && $files === []) {
            throw new BusinessRuleException('Add a message or upload at least one document.');
        }

        $stored_paths = [];

        try {
            $status_change = DB::transaction(function () use ($order, $note, $files, $responder, &$stored_paths): OrderStatusChange {
                $locked_order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

                if ($locked_order->status !== OrderStatus::NeedsAction) {
                    throw new BusinessRuleException('This request is not waiting for anything from you.');
                }

                foreach ($files as $file) {
                    $stored_paths[] = $this->store_order_document->handle($locked_order, $file)->stored_path;
                }

                $restored_status = $locked_order->statusBeforeNeedsAction();
                $locked_order->forceFill(['status' => $restored_status])->save();

                return OrderStatusChange::create([
                    'order_id' => $locked_order->id,
                    'changed_by' => $responder !== null && $locked_order->isOwnedBy($responder) ? $responder->id : null,
                    'by_customer' => true,
                    'from_status' => OrderStatus::NeedsAction,
                    'to_status' => $restored_status,
                    'note' => $note,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk(OrderDocument::DISK)->delete($stored_paths);

            throw $exception;
        }

        $order->refresh();

        Notification::send(
            User::query()->whereIn('role', [UserRole::Staff->value, UserRole::Admin->value])->get(),
            new CustomerRespondedToOrder($order, $status_change, count($files)),
        );

        if ($order->customer_email !== null) {
            Mail::to($order->customer_email)->queue(new NeedsActionResponseReceivedMail(
                $order,
                $status_change,
                array_map(fn (UploadedFile $file): string => $file->getClientOriginalName(), $files),
            ));
        }

        return $status_change;
    }
}

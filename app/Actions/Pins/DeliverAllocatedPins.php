<?php

namespace App\Actions\Pins;

use App\Enums\OrderStatus;
use App\Enums\PinStatus;
use App\Mail\ResultCheckerPinsMail;
use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\ResultCheckerPin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Hand the customer every PIN allocated to their order but not yet delivered.
 *
 * Business rules:
 *  - allocated -> revealed, and the PINs are emailed (queued) to the customer.
 *    They also appear on the order tracking page / My Requests from now on.
 *  - Once every unit ordered has a PIN, the order is marked completed
 *    automatically (history entry with no staff member); no manual work.
 *  - Safe to call repeatedly: only PINs still "allocated" are sent, so a PIN
 *    is never emailed twice, and a failed earlier delivery is picked up.
 *  - Nothing is delivered for an order that can no longer receive PINs
 *    (cancelled or deleted in the meantime); its PINs stay allocated for staff.
 */
class DeliverAllocatedPins
{
    /**
     * @param  Order  $order
     * @return int How many PINs were delivered now.
     */
    public function handle(Order $order): int
    {
        [$pin_ids, $still_owed] = DB::transaction(function () use ($order): array {
            $locked_order = Order::query()->with('service')->whereKey($order->getKey())->lockForUpdate()->first();

            if ($locked_order === null || ! $locked_order->canReceivePins()) {
                return [[], 0];
            }

            $pin_ids = $locked_order->pins()->where('status', PinStatus::Allocated->value)->pluck('id')->all();

            if ($pin_ids === []) {
                return [[], 0];
            }

            ResultCheckerPin::query()->whereKey($pin_ids)->update([
                'status' => PinStatus::Revealed->value,
                'revealed_at' => now(),
                'updated_at' => now(),
            ]);

            $still_owed = max(0, $locked_order->quantity - $locked_order->pins()->count());

            if ($still_owed === 0) {
                $previous_status = $locked_order->status;
                $locked_order->forceFill(['status' => OrderStatus::Completed])->save();

                OrderStatusChange::create([
                    'order_id' => $locked_order->id,
                    'changed_by' => null,
                    'from_status' => $previous_status,
                    'to_status' => OrderStatus::Completed,
                    'note' => trans_choice(
                        'Your result checker PIN has been delivered. It is shown on this page and was sent to your email.|Your :count result checker PINs have been delivered. They are shown on this page and were sent to your email.',
                        $locked_order->quantity,
                    ),
                ]);
            }

            return [$pin_ids, $still_owed];
        });

        $order->refresh();

        if ($pin_ids !== [] && $order->customer_email !== null) {
            $pins = ResultCheckerPin::query()->whereKey($pin_ids)->orderBy('id')->get();

            Mail::to($order->customer_email)->queue(new ResultCheckerPinsMail($order, $pins, $still_owed));
        }

        return count($pin_ids);
    }
}

<?php

namespace App\Actions\Pins;

use App\Enums\PinStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\ResultCheckerPin;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * "Deliver PIN manually" with a PIN staff obtained themselves (e.g. bought one
 * card from the exam body while stock is out). Fills ONE owed unit.
 *
 * The PIN is stored exactly like stock (encrypted, hashed) but created
 * straight away as allocated to this order, then delivered the normal way.
 * It is refused if the same PIN is already in stock or was sold before.
 */
class DeliverOneOffPin
{
    /**
     * @param  DeliverAllocatedPins  $deliver_allocated_pins
     */
    public function __construct(
        private readonly DeliverAllocatedPins $deliver_allocated_pins,
    ) {}

    /**
     * @param  Order  $order  A paid result checker order still owed PINs.
     * @param  string  $pin
     * @param  string|null  $serial_number
     * @param  User  $staff  Staff/admin entering it.
     * @return ResultCheckerPin
     *
     * @throws BusinessRuleException
     */
    public function handle(Order $order, string $pin, ?string $serial_number, User $staff): ResultCheckerPin
    {
        if (! $staff->isStaffOrAdmin()) {
            throw new BusinessRuleException('Only staff and admins can deliver PINs.');
        }

        $pin = ResultCheckerPin::normalise($pin);
        $serial_number = filled($serial_number) ? ResultCheckerPin::normalise($serial_number) : null;

        if (! ImportPinBatch::is_valid_value($pin) || ($serial_number !== null && ! ImportPinBatch::is_valid_value($serial_number))) {
            throw new BusinessRuleException('PINs and serial numbers must be 4–'.ResultCheckerPin::MAX_LENGTH.' letters, digits or dashes.');
        }

        $hash = ResultCheckerPin::hash($pin);

        if (ResultCheckerPin::query()->where('pin_hash', $hash)->exists()) {
            throw new BusinessRuleException('That PIN is already in stock or has been sold before. Check it and try again.');
        }

        try {
            $result_checker_pin = DB::transaction(function () use ($order, $pin, $serial_number, $hash, $staff): ResultCheckerPin {
                $locked_order = Order::query()->with('service')->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

                if ($locked_order->undeliveredPinCount() === 0) {
                    throw new BusinessRuleException('This order is not waiting for any PINs.');
                }

                $result_checker_pin = new ResultCheckerPin(['service_id' => $locked_order->service_id]);
                $result_checker_pin->forceFill([
                    'pin' => $pin,
                    'serial_number' => $serial_number,
                    'pin_hash' => $hash,
                    'status' => PinStatus::Allocated,
                    'order_id' => $locked_order->id,
                    'added_by' => $staff->id,
                    'allocated_at' => now(),
                ])->save();

                return $result_checker_pin;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException('That PIN is already in stock or has been sold before. Check it and try again.');
        }

        $this->deliver_allocated_pins->handle($order);

        return $result_checker_pin->refresh();
    }
}

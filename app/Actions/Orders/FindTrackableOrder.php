<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Support\PhoneNumber;

/**
 * Find an order from the public "Track your request" form.
 *
 * Business rule: the reference alone is not enough (it appears in emails and
 * old orders have sequential numbers). The visitor must also give the email or phone the
 * order was placed with. Email matches guest_email or the account email
 * (case-insensitive); phone matches guest_phone after normalising. A wrong
 * reference and a wrong contact give the same result (null), so the form
 * doesn't reveal which orders exist. Soft-deleted orders are never found.
 */
class FindTrackableOrder
{
    /**
     * @param  string  $reference  As typed, e.g. "ME-7K3Q9A", "me7k3q9a" or an old "#12" (Order::normaliseReference()).
     * @param  string  $contact  Email address or phone number.
     * @return ?Order
     */
    public function handle(string $reference, string $contact): ?Order
    {
        $reference = Order::normaliseReference($reference);
        $contact = trim($contact);

        if ($reference === '' || $contact === '') {
            return null;
        }

        $order = Order::query()->with('user')->where('reference', $reference)->first();

        if ($order === null) {
            return null;
        }

        return self::contact_matches($order, $contact) ? $order : null;
    }

    /**
     * @param  Order  $order
     * @param  string  $contact
     * @return bool
     */
    private static function contact_matches(Order $order, string $contact): bool
    {
        if (str_contains($contact, '@')) {
            $submitted_email = mb_strtolower($contact);

            return collect([$order->guest_email, $order->user?->email])
                ->filter()
                ->contains(fn (string $email): bool => hash_equals(mb_strtolower($email), $submitted_email));
        }

        return $order->guest_phone !== null && PhoneNumber::matches($contact, $order->guest_phone);
    }
}

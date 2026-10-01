<?php

namespace App\Support;

/**
 * wa.me click-to-chat links to the business WhatsApp number
 * (config('madeeasy.contact.whatsapp'), set in .env). Plain links only: no
 * WhatsApp SDK or Business API, nothing loaded from WhatsApp until a
 * visitor taps the button.
 */
class WhatsApp
{
    /**
     * The number in wa.me form: international, digits only, e.g.
     * "+234 803 123 4567" or "0803 123 4567" -> "2348031234567".
     * Null if the configured value isn't a usable number.
     *
     * @return string|null
     */
    public static function number(): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) config('madeeasy.contact.whatsapp'));

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '234'.substr($digits, 1);
        }

        return strlen($digits) >= 10 ? $digits : null;
    }

    /**
     * Chat link with a pre-filled message, or null without a usable number.
     *
     * @param  string|null  $message  Defaults to generic_message().
     * @return string|null
     */
    public static function link(?string $message = null): ?string
    {
        $number = self::number();

        if ($number === null) {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message ?? self::generic_message());
    }

    /**
     * @return string
     */
    public static function generic_message(): string
    {
        return "Hi Made Easy, I'd like to make an enquiry.";
    }

    /**
     * @param  string  $service_name
     * @return string
     */
    public static function service_message(string $service_name): string
    {
        return "Hi Made Easy, I'd like to know more about {$service_name}.";
    }

    /**
     * @param  string  $category_name
     * @return string
     */
    public static function category_message(string $category_name): string
    {
        return "Hi Made Easy, I'd like to know more about your {$category_name}.";
    }

    /**
     * @param  string  $display_reference
     * @return string
     */
    public static function order_message(string $display_reference): string
    {
        return "Hi Made Easy, I have a question about my request {$display_reference}.";
    }

    /**
     * @return string
     */
    public static function tutoring_message(): string
    {
        return "Hi Made Easy, I'm interested in online Mathematics tutoring. Please tell me about the classes, times and fees.";
    }
}

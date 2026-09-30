<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a service is delivered once ordered.
 *
 * - Instant:      fulfilled automatically after verified payment (e.g. result checker PINs).
 * - Manual:       fixed price; staff carry out the work after payment.
 * - Quote:        price depends on the case; staff review the request and set the
 *                 order total before the customer pays. services.price is 0 for these.
 * - Consultation: fixed price; booked into a consultation slot on
 *                 /book-consultation and confirmed once payment is verified.
 */
enum FulfilmentType: string implements HasLabel
{
    case Instant = 'instant';
    case Manual = 'manual';
    case Quote = 'quote';
    case Consultation = 'consultation';

    /**
     * Human-readable label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Instant => 'Instant',
            self::Manual => 'Manual',
            self::Quote => 'Quote',
            self::Consultation => 'Consultation booking',
        };
    }
}

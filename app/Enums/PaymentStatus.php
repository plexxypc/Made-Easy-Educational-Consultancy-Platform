<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * State of one Paystack payment attempt.
 *
 *   initiated  -> successful (verified charge.success webhook, amount matches)
 *   initiated  -> failed     (Paystack refused to start the transaction)
 *
 * A verified charge that can't be applied (wrong amount, order not payable)
 * stays "initiated" with payments.review_reason set: see Payment::needsReview().
 */
enum PaymentStatus: string implements HasColor, HasLabel
{
    case Initiated = 'initiated';
    case Successful = 'successful';
    case Failed = 'failed';

    /**
     * Human-readable label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Initiated => 'Initiated',
            self::Successful => 'Successful',
            self::Failed => 'Failed',
        };
    }

    /**
     * Badge colour in Filament.
     *
     * @return string
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Initiated => 'gray',
            self::Successful => 'success',
            self::Failed => 'danger',
        };
    }
}

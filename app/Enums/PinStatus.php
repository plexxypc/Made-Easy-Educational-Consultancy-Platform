<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle of a result checker PIN in stock.
 *
 *   available -> allocated -> revealed
 *
 * - Available: in stock, not tied to any order.
 * - Allocated: reserved for a paid order inside the allocation transaction.
 * - Revealed:  delivered to the customer (PIN email queued; shown on their
 *              order page). A PIN never goes back to available.
 */
enum PinStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Allocated = 'allocated';
    case Revealed = 'revealed';

    /**
     * Human-readable label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Allocated => 'Allocated',
            self::Revealed => 'Delivered',
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
            self::Available => 'success',
            self::Allocated => 'warning',
            self::Revealed => 'gray',
        };
    }
}

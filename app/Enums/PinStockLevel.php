<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A result checker's stock level, as last recorded by CheckPinStockLevel.
 *
 * Stored on services.pin_stock_level so a staff alert fires once when the
 * level gets WORSE (ok -> low, ok/low -> out), not on every later check.
 */
enum PinStockLevel: string implements HasColor, HasLabel
{
    case Ok = 'ok';
    case Low = 'low';
    case Out = 'out';

    /**
     * The level for a number of available PINs.
     *
     * @param  int  $available
     * @param  int  $low_stock_threshold
     * @return self
     */
    public static function for_count(int $available, int $low_stock_threshold): self
    {
        return match (true) {
            $available <= 0 => self::Out,
            $available <= $low_stock_threshold => self::Low,
            default => self::Ok,
        };
    }

    /**
     * Higher is worse.
     *
     * @return int
     */
    public function severity(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Low => 1,
            self::Out => 2,
        };
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Ok => 'In stock',
            self::Low => 'Low stock',
            self::Out => 'Out of stock',
        };
    }

    /**
     * @return string
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Low => 'warning',
            self::Out => 'danger',
        };
    }
}

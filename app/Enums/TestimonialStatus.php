<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Moderation state of a testimonial. Only Approved is ever shown publicly
 * (Testimonial::approved()); new submissions always start as Pending.
 */
enum TestimonialStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * @return string
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'gray',
        };
    }
}

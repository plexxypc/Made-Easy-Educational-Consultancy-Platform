<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How a consultation slot is held.
 *
 * Assumption: "online" is a video call on a platform staff choose (e.g.
 * Zoom), "google_meet" specifically Google Meet. Both need a joining link,
 * which staff add by hand ("Add meeting link"); none is generated. WhatsApp
 * calls go to the phone number on the order.
 */
enum ConsultationType: string implements HasColor, HasLabel
{
    case Online = 'online';
    case WhatsApp = 'whatsapp';
    case GoogleMeet = 'google_meet';

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Online => 'Online video call',
            self::WhatsApp => 'WhatsApp call',
            self::GoogleMeet => 'Google Meet',
        };
    }

    /**
     * @return string
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Online => 'info',
            self::WhatsApp => 'success',
            self::GoogleMeet => 'warning',
        };
    }

    /**
     * Whether staff must send a joining link for this type.
     *
     * @return bool
     */
    public function needsMeetingLink(): bool
    {
        return $this !== self::WhatsApp;
    }

    /**
     * What the customer should expect, for emails and the order page.
     *
     * @return string
     */
    public function customerInstructions(): string
    {
        return match ($this) {
            self::Online => "We'll send you a link to join the video call before your consultation.",
            self::WhatsApp => "We'll call you on WhatsApp at the time booked, on the phone number you gave us.",
            self::GoogleMeet => "We'll send you the Google Meet link before your consultation.",
        };
    }
}

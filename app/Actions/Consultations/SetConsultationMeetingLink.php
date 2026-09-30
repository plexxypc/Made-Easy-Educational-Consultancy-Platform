<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationBookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\ConsultationBookingMail;
use App\Models\ConsultationBooking;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Staff add (or replace) the joining link of an online / Google Meet
 * consultation, and the customer is emailed it.
 *
 * Business rules:
 *  - Links are created by staff in Google Meet (or the video tool used)
 *    and pasted here; nothing is generated automatically. Until then the
 *    customer sees a clearly marked "to be added by our team" placeholder.
 *  - Confirmed bookings of a type that needs a link only.
 *  - https links only, so a mistyped or unsafe value isn't sent out.
 *    Assumption: any https host is accepted (not only meet.google.com),
 *    since "online" slots may use another tool.
 */
class SetConsultationMeetingLink
{
    /**
     * @var int
     */
    public const MAX_LENGTH = 500;

    /**
     * @param  ConsultationBooking  $booking
     * @param  string  $meeting_link
     * @param  User  $actor  Staff/admin.
     * @return ConsultationBooking
     *
     * @throws BusinessRuleException
     */
    public function handle(ConsultationBooking $booking, string $meeting_link, User $actor): ConsultationBooking
    {
        if (! $actor->isStaffOrAdmin()) {
            throw new BusinessRuleException('Only staff and admins can add meeting links.');
        }

        $meeting_link = trim($meeting_link);

        if (! self::is_valid_link($meeting_link)) {
            throw new BusinessRuleException('Enter the full meeting link, starting with https://');
        }

        $booking->refresh()->loadMissing(['slot', 'order']);

        if ($booking->status !== ConsultationBookingStatus::Confirmed) {
            throw new BusinessRuleException('Meeting links can only be added to confirmed bookings.');
        }

        if (! $booking->slot->consultation_type->needsMeetingLink()) {
            throw new BusinessRuleException("{$booking->slot->consultation_type->getLabel()} consultations don't use a meeting link.");
        }

        $booking->forceFill([
            'meeting_link' => $meeting_link,
            'meeting_link_added_by' => $actor->id,
            'meeting_link_added_at' => now(),
        ])->save();

        if ($booking->order->customer_email !== null) {
            Mail::to($booking->order->customer_email)->queue(new ConsultationBookingMail($booking, ConsultationBookingMail::MEETING_LINK));
        }

        return $booking;
    }

    /**
     * @param  string  $meeting_link
     * @return bool
     */
    public static function is_valid_link(string $meeting_link): bool
    {
        return strlen($meeting_link) <= self::MAX_LENGTH
            && filter_var($meeting_link, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($meeting_link, PHP_URL_SCHEME)) === 'https'
            && filled(parse_url($meeting_link, PHP_URL_HOST));
    }
}

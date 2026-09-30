<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationBookingStatus;
use App\Enums\ConsultationType;
use App\Enums\UserRole;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\User;
use App\Notifications\ConsultationMeetingLinkMissing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Warn staff about confirmed video consultations starting within the next
 * 24 hours that still have no meeting link. Run every 15 minutes by the
 * scheduler (consultations:remind-missing-meeting-links).
 *
 * Business rules:
 *  - Applies to every type that needs a link: "online" and "google_meet".
 *    Assumption: the request said "online", but a Google Meet booking without
 *    a link is the same problem (it's the placeholder case), so both are covered.
 *  - Confirmed bookings only, on slots that aren't cancelled and haven't
 *    started. Unpaid holds and bookings waiting for a new time are skipped.
 *  - Once per booking: meeting_link_reminder_sent_at is set under a row lock
 *    before staff are notified, so overlapping runs can't both send it.
 *    Rescheduling to a new time clears it (RescheduleConsultationBooking), so
 *    the new time gets its own reminder.
 *  - Staff and admins get a bell notification and an email.
 */
class RemindStaffOfMissingMeetingLinks
{
    /**
     * How far ahead to look, in hours.
     *
     * @var int
     */
    public const HOURS_AHEAD = 24;

    /**
     * @return int Bookings staff were reminded about in this run.
     */
    public function handle(): int
    {
        $window_end = now()->addHours(self::HOURS_AHEAD);
        $local_today = now(ConsultationSlot::timezone())->startOfDay();

        $candidates = ConsultationBooking::query()
            ->with('slot')
            ->where('status', ConsultationBookingStatus::Confirmed->value)
            ->whereNull('meeting_link_reminder_sent_at')
            ->where(fn (Builder $query) => $query->whereNull('meeting_link')->orWhere('meeting_link', ''))
            ->whereHas('slot', fn (Builder $slot_query) => $slot_query
                ->whereNull('cancelled_at')
                ->whereIn('consultation_type', self::types_needing_link())
                ->whereDate('date', '>=', $local_today->toDateString())
                ->whereDate('date', '<=', $local_today->copy()->addDays(2)->toDateString()))
            ->get()
            ->filter(fn (ConsultationBooking $booking): bool => $booking->slot->startsAt()->isFuture()
                && $booking->slot->startsAt()->lessThanOrEqualTo($window_end));

        $staff = null;
        $reminded = 0;

        foreach ($candidates as $booking) {
            if (! $this->mark_reminded($booking)) {
                continue;
            }

            $staff ??= User::query()->whereIn('role', [UserRole::Staff->value, UserRole::Admin->value])->get();
            Notification::send($staff, new ConsultationMeetingLinkMissing($booking->refresh()->load(['slot', 'order'])));
            $reminded++;
        }

        return $reminded;
    }

    /**
     * Claim the reminder for this booking; false if it no longer needs one.
     *
     * @param  ConsultationBooking  $booking
     * @return bool
     */
    private function mark_reminded(ConsultationBooking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $locked = ConsultationBooking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if ($locked === null
                || $locked->status !== ConsultationBookingStatus::Confirmed
                || $locked->meeting_link_reminder_sent_at !== null
                || filled($locked->meeting_link)) {
                return false;
            }

            $locked->forceFill(['meeting_link_reminder_sent_at' => now()])->save();

            return true;
        });
    }

    /**
     * @return array<int, string>
     */
    private static function types_needing_link(): array
    {
        return array_values(array_map(
            fn (ConsultationType $type): string => $type->value,
            array_filter(ConsultationType::cases(), fn (ConsultationType $type): bool => $type->needsMeetingLink()),
        ));
    }
}

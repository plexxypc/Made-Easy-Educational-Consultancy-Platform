<?php

namespace Tests\Feature\Consultations;

use App\Actions\Consultations\RescheduleConsultationBooking;
use App\Enums\ConsultationType;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ConsultationMeetingLinkMissing;
use DateTimeInterface;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Staff are reminded, once per booking, about confirmed video consultations
 * starting within 24 hours that still have no meeting link.
 */
class MeetingLinkReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var Service
     */
    private Service $consultation;

    /**
     * @var User
     */
    private User $staff_user;

    /**
     * @var User
     */
    private User $admin_user;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        $this->staff_user = User::factory()->staff()->create();
        $this->admin_user = User::factory()->admin()->create();
        $this->consultation = Service::factory()->create(['fulfilment_type' => FulfilmentType::Consultation, 'price' => 1_500_000]);
    }

    /**
     * The command is on the scheduler.
     *
     * @return void
     */
    public function test_reminder_is_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn (ScheduledEvent $event): string => (string) $event->command);

        $this->assertTrue($commands->contains(fn (string $command): bool => str_contains($command, 'consultations:remind-missing-meeting-links')));
    }

    /**
     * Only the right bookings are reminded about, and each only once however
     * often the check runs; a booking entering the window later gets its own.
     *
     * @return void
     */
    public function test_reminder_fires_once_per_booking(): void
    {
        $online_soon = $this->booking(ConsultationType::Online, now()->addHours(20));
        $meet_soon = $this->booking(ConsultationType::GoogleMeet, now()->addHours(3));
        $has_link = $this->booking(ConsultationType::Online, now()->addHours(5), meeting_link: 'https://meet.example.com/abc');
        $whatsapp = $this->booking(ConsultationType::WhatsApp, now()->addHours(5));
        $later = $this->booking(ConsultationType::Online, now()->addHours(30));
        $unpaid = $this->booking(ConsultationType::Online, now()->addHours(4), confirmed: false);
        $cancelled_slot = $this->booking(ConsultationType::Online, now()->addHours(6));
        $cancelled_slot->slot->forceFill(['cancelled_at' => now()])->save();

        $this->artisan('consultations:remind-missing-meeting-links')
            ->expectsOutputToContain('2 booking(s)')
            ->assertSuccessful();

        $this->assert_reminded([$online_soon, $meet_soon]);
        $this->assertNotNull($online_soon->refresh()->meeting_link_reminder_sent_at);
        foreach ([$has_link, $whatsapp, $later, $unpaid, $cancelled_slot] as $not_reminded) {
            $this->assertNull($not_reminded->refresh()->meeting_link_reminder_sent_at);
        }

        // Running again (every 15 minutes) sends nothing more.
        $this->artisan('consultations:remind-missing-meeting-links')->expectsOutputToContain('0 booking(s)');
        $this->travel(15)->minutes();
        $this->artisan('consultations:remind-missing-meeting-links')->expectsOutputToContain('0 booking(s)');
        $this->assert_reminded([$online_soon, $meet_soon]);

        // Seven hours on, the 30-hour booking is inside the window: reminded once.
        $this->travel(7)->hours();
        $this->artisan('consultations:remind-missing-meeting-links')->expectsOutputToContain('1 booking(s)');
        $this->artisan('consultations:remind-missing-meeting-links')->expectsOutputToContain('0 booking(s)');
        $this->assert_reminded([$online_soon, $meet_soon, $later]);
    }

    /**
     * Rescheduling to a new time re-arms the reminder for that time.
     *
     * @return void
     */
    public function test_rescheduled_booking_gets_a_new_reminder(): void
    {
        $booking = $this->booking(ConsultationType::GoogleMeet, now()->addHours(10));
        $this->artisan('consultations:remind-missing-meeting-links');
        $this->assert_reminded([$booking]);

        $new_slot = ConsultationSlot::factory()->ofType(ConsultationType::GoogleMeet)->startingAt(now()->addHours(12))->create();
        app(RescheduleConsultationBooking::class)->handle($booking, $new_slot->id, $this->staff_user);
        $this->assertNull($booking->refresh()->meeting_link_reminder_sent_at);

        $this->artisan('consultations:remind-missing-meeting-links')->expectsOutputToContain('1 booking(s)');
        Notification::assertSentToTimes($this->staff_user, ConsultationMeetingLinkMissing::class, 2);
    }

    /**
     * Each booking was reminded exactly once, to staff and admins.
     *
     * @param  array<int, ConsultationBooking>  $bookings
     * @return void
     */
    private function assert_reminded(array $bookings): void
    {
        foreach ([$this->staff_user, $this->admin_user] as $user) {
            Notification::assertSentToTimes($user, ConsultationMeetingLinkMissing::class, count($bookings));

            foreach ($bookings as $booking) {
                Notification::assertSentTo($user, ConsultationMeetingLinkMissing::class, fn (ConsultationMeetingLinkMissing $notification): bool => $notification->booking->is($booking));
            }
        }
    }

    /**
     * @param  ConsultationType  $type
     * @param  DateTimeInterface  $starts_at
     * @param  bool  $confirmed
     * @param  string|null  $meeting_link
     * @return ConsultationBooking
     */
    private function booking(ConsultationType $type, DateTimeInterface $starts_at, bool $confirmed = true, ?string $meeting_link = null): ConsultationBooking
    {
        $order = Order::factory()->guest()->for($this->consultation)->create([
            'status' => $confirmed ? OrderStatus::Paid : OrderStatus::PendingPayment,
            'total_amount' => $this->consultation->price,
        ]);
        $slot = ConsultationSlot::factory()->ofType($type)->startingAt($starts_at)->create();
        $factory = ConsultationBooking::factory()->for($order)->for($slot, 'slot');

        return ($confirmed ? $factory->confirmed() : $factory)->create(['meeting_link' => $meeting_link]);
    }
}

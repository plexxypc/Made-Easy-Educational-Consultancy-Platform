<?php

namespace Tests\Feature\Consultations;

use App\Actions\Consultations\BookConsultation;
use App\Actions\Consultations\CancelConsultationBooking;
use App\Actions\Consultations\CloseConsultationBooking;
use App\Enums\ConsultationBookingStatus;
use App\Enums\ConsultationType;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Exceptions\SlotUnavailable;
use App\Filament\Resources\ConsultationBookings\ConsultationBookingResource;
use App\Filament\Resources\ConsultationBookings\Pages\ListConsultationBookings;
use App\Filament\Resources\ConsultationBookings\Pages\ViewConsultationBooking;
use App\Filament\Resources\ConsultationSlots\ConsultationSlotResource;
use App\Filament\Resources\ConsultationSlots\Pages\ListConsultationSlots;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Mail\ConsultationBookingMail;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Back office: admins manage availability; staff and admins manage bookings
 * (complete, no-show, cancel, reschedule, meeting link). Customers are
 * emailed about every change to their booking except completion.
 */
class ConsultationManagementTest extends TestCase
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
        $this->consultation = Service::factory()->create([
            'name' => 'Admission Consultation',
            'fulfilment_type' => FulfilmentType::Consultation,
            'price' => 1_500_000,
        ]);
    }

    /**
     * Admins add single and recurring slots; duplicates are refused or skipped.
     *
     * @return void
     */
    public function test_admin_adds_single_and_recurring_slots(): void
    {
        $this->actingAs($this->admin_user);
        $next_monday = now(ConsultationSlot::timezone())->next('Monday')->toDateString();

        Livewire::test(ListConsultationSlots::class)
            ->callAction('addSlot', [
                'date' => $next_monday,
                'start_time' => '09:00',
                'duration_minutes' => 45,
                'consultation_type' => ConsultationType::WhatsApp->value,
                'capacity' => 1,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Slot added');

        $slot = ConsultationSlot::query()->sole();
        $this->assertSame($next_monday.' 09:00', $slot->startsAt()->format('Y-m-d H:i'));
        $this->assertSame(45, $slot->duration_minutes);
        $this->assertSame(ConsultationType::WhatsApp, $slot->consultation_type);
        $this->assertSame($this->admin_user->id, $slot->created_by);

        // Mon + Wed, 10:00–12:00 hourly, 2 weeks = 8 slots.
        Livewire::test(ListConsultationSlots::class)
            ->callAction('addRecurringSlots', [
                'first_date' => $next_monday,
                'weeks' => 2,
                'weekdays' => [1, 3],
                'from_time' => '10:00',
                'until_time' => '12:00',
                'duration_minutes' => 60,
                'consultation_type' => ConsultationType::GoogleMeet->value,
                'capacity' => 1,
            ])
            ->assertNotified('8 slots added');

        // Running it again creates nothing new.
        Livewire::test(ListConsultationSlots::class)
            ->callAction('addRecurringSlots', [
                'first_date' => $next_monday,
                'weeks' => 2,
                'weekdays' => [1, 3],
                'from_time' => '10:00',
                'until_time' => '12:00',
                'duration_minutes' => 60,
                'consultation_type' => ConsultationType::GoogleMeet->value,
                'capacity' => 1,
            ])
            ->assertNotified('0 slots added');

        $this->assertSame(9, ConsultationSlot::query()->count());
    }

    /**
     * Staff can see and manage bookings but not availability.
     *
     * @return void
     */
    public function test_staff_manage_bookings_but_not_slots(): void
    {
        $booking = $this->confirmed_booking(ConsultationSlot::factory()->create());

        $this->actingAs($this->staff_user)->get(ConsultationSlotResource::getUrl('index'))->assertForbidden();
        $this->actingAs($this->staff_user)->get(ConsultationBookingResource::getUrl('index'))->assertOk();
        $this->actingAs($this->staff_user)->get(ConsultationBookingResource::getUrl('view', ['record' => $booking]))->assertOk();
        $this->actingAs(User::factory()->create())->get(ConsultationBookingResource::getUrl('index'))->assertForbidden();

        $this->actingAs($this->staff_user);
        Livewire::test(ListConsultationBookings::class)->assertCanSeeTableRecords([$booking]);

        // The generic order actions can't complete or cancel a consultation order.
        Livewire::test(ViewOrder::class, ['record' => $booking->order->getRouteKey()])
            ->assertSee('Consultation booking')
            ->assertActionHidden('markCompleted')
            ->assertActionHidden('cancel');
    }

    /**
     * Cancelling a booking frees its place for someone else; completing one
     * (or recording a no-show) keeps it used.
     *
     * @return void
     */
    public function test_cancelled_booking_frees_its_slot_but_completed_does_not(): void
    {
        $slot = ConsultationSlot::factory()->create();
        $booking = $this->confirmed_booking($slot);

        $this->assert_slot_full($slot);

        $this->actingAs($this->staff_user);
        Livewire::test(ViewConsultationBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('cancelBooking', ['reason' => 'Customer asked to cancel'])
            ->assertNotified('Booking cancelled; the customer has been emailed');

        $booking->refresh();
        $this->assertSame(ConsultationBookingStatus::Cancelled, $booking->status);
        $this->assertSame(OrderStatus::Cancelled, $booking->order->status);
        $this->assertSame(1, $slot->remainingCapacity());
        Mail::assertQueued(ConsultationBookingMail::class, fn (ConsultationBookingMail $mail): bool => $mail->hasTo('ada@example.com') && $mail->kind === ConsultationBookingMail::CANCELLED);

        $rebooked = app(BookConsultation::class)->handle($slot->id, ['name' => 'Next Customer', 'email' => 'next@example.com', 'phone' => '08030000000']);
        $this->assertTrue($rebooked->consultationBooking->slot->is($slot));

        // Completed and no-show bookings keep their place.
        foreach ([ConsultationBookingStatus::Completed, ConsultationBookingStatus::NoShow] as $outcome) {
            $other_slot = ConsultationSlot::factory()->create(['start_time' => $outcome === ConsultationBookingStatus::Completed ? '15:00:00' : '17:00:00']);
            $other_booking = $this->confirmed_booking($other_slot);

            $this->travelTo($other_slot->startsAt()->addMinutes(70));
            app(CloseConsultationBooking::class)->handle($other_booking, $outcome, $this->staff_user);
            $this->travelBack();

            $other_booking->refresh();
            $this->assertSame($outcome, $other_booking->status);
            $this->assertSame(OrderStatus::Completed, $other_booking->order->status);
            $this->assert_slot_full($other_slot);
        }

        Mail::assertQueued(ConsultationBookingMail::class, 1); // only the cancellation
    }

    /**
     * An admin cancelling a slot cancels every booking on it and emails those
     * customers; other slots are untouched and nobody else is emailed.
     *
     * @return void
     */
    public function test_cancelling_a_booked_slot_notifies_the_customer(): void
    {
        $slot = ConsultationSlot::factory()->create(['capacity' => 2]);
        $booking = $this->confirmed_booking($slot);
        $held = app(BookConsultation::class)->handle($slot->id, ['name' => 'Held Customer', 'email' => 'held@example.com', 'phone' => '08030000000'])->consultationBooking;
        $other_booking = $this->confirmed_booking(ConsultationSlot::factory()->create(['start_time' => '16:00:00']), 'other@example.com');

        $this->actingAs($this->admin_user);
        Livewire::test(ListConsultationSlots::class)
            ->callAction(TestAction::make('cancelSlot')->table($slot), data: ['reason' => 'Our consultant is unwell'])
            ->assertNotified('Slot cancelled');

        $this->assertTrue($slot->refresh()->isCancelled());
        $this->assertSame('Our consultant is unwell', $slot->cancellation_reason);
        $this->assertSame(ConsultationBookingStatus::Cancelled, $booking->refresh()->status);
        $this->assertSame(ConsultationBookingStatus::Cancelled, $held->refresh()->status);
        $this->assertSame(ConsultationBookingStatus::Confirmed, $other_booking->refresh()->status);

        Mail::assertQueued(ConsultationBookingMail::class, function (ConsultationBookingMail $mail): bool {
            $mail->assertSeeInHtml('Our consultant is unwell');

            return $mail->hasTo('ada@example.com') && $mail->kind === ConsultationBookingMail::SLOT_CANCELLED;
        });
        Mail::assertQueued(ConsultationBookingMail::class, fn (ConsultationBookingMail $mail): bool => $mail->hasTo('held@example.com') && $mail->kind === ConsultationBookingMail::SLOT_CANCELLED);
        Mail::assertNotQueued(ConsultationBookingMail::class, fn (ConsultationBookingMail $mail): bool => $mail->hasTo('other@example.com'));

        $this->expectException(SlotUnavailable::class);
        app(BookConsultation::class)->handle($slot->id, ['name' => 'Late', 'email' => 'late@example.com', 'phone' => '08030000000']);
    }

    /**
     * Staff can't cancel slots; an admin can delete a never-booked slot but not a booked one.
     *
     * @return void
     */
    public function test_slot_cancel_and_delete_permissions(): void
    {
        $unbooked = ConsultationSlot::factory()->create();
        $booked = ConsultationSlot::factory()->create(['start_time' => '15:00:00']);
        $this->confirmed_booking($booked);

        $this->assertFalse($this->staff_user->can('cancel', $unbooked));
        $this->assertTrue($this->admin_user->can('delete', $unbooked));
        $this->assertFalse($this->admin_user->can('delete', $booked));

        $this->actingAs($this->admin_user);
        Livewire::test(ListConsultationSlots::class)
            ->assertActionHidden(TestAction::make('delete')->table($booked))
            ->callAction(TestAction::make('delete')->table($unbooked));

        $this->assertModelMissing($unbooked);
        $this->assertModelExists($booked);
    }

    /**
     * Rescheduling moves the booking, frees the old place, clears the meeting
     * link, and emails the customer the new time with the old one.
     *
     * @return void
     */
    public function test_reschedule_moves_booking_and_notifies_customer(): void
    {
        $old_slot = ConsultationSlot::factory()->create();
        $new_slot = ConsultationSlot::factory()->ofType(ConsultationType::WhatsApp)->startingAt(now()->addDays(6))->create();
        $booking = $this->confirmed_booking($old_slot);
        $booking->forceFill(['meeting_link' => 'https://meet.google.com/abc-defg-hij'])->save();

        $this->actingAs($this->staff_user);
        Livewire::test(ViewConsultationBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('reschedule', ['slot_id' => $new_slot->id, 'note' => 'As agreed on the phone.'])
            ->assertHasNoActionErrors();

        $booking->refresh();
        $this->assertTrue($booking->slot->is($new_slot));
        $this->assertSame($old_slot->id, $booking->previous_slot_id);
        $this->assertSame(1, $booking->reschedule_count);
        $this->assertNull($booking->meeting_link);
        $this->assertSame(ConsultationBookingStatus::Confirmed, $booking->status);
        $this->assertSame(1, $old_slot->remainingCapacity());
        $this->assertSame(0, $new_slot->remainingCapacity());

        Mail::assertQueued(ConsultationBookingMail::class, function (ConsultationBookingMail $mail) use ($old_slot, $new_slot): bool {
            $mail->assertSeeInHtml($new_slot->dateLabel());
            $mail->assertSeeInHtml($old_slot->label());
            $mail->assertSeeInHtml('WhatsApp call');

            return $mail->hasTo('ada@example.com') && $mail->kind === ConsultationBookingMail::RESCHEDULED;
        });
    }

    /**
     * A paid booking that lost its time is resolved by choosing a new one.
     *
     * @return void
     */
    public function test_needs_rebooking_is_resolved_by_rescheduling(): void
    {
        $taken_slot = ConsultationSlot::factory()->create();
        $new_slot = ConsultationSlot::factory()->create(['start_time' => '15:00:00']);
        $booking = $this->confirmed_booking($taken_slot);
        $booking->forceFill([
            'status' => ConsultationBookingStatus::PendingPayment,
            'confirmed_at' => null,
            'needs_rebooking_at' => now(),
            'needs_rebooking_reason' => 'This time has just been booked by someone else.',
        ])->save();

        $this->actingAs($this->staff_user);
        Livewire::test(ViewConsultationBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Paid – needs a new time')
            ->assertActionVisible('reschedule')
            ->assertActionHidden('markCompleted')
            ->callAction('reschedule', ['slot_id' => $new_slot->id])
            ->assertHasNoActionErrors();

        $booking->refresh();
        $this->assertSame(ConsultationBookingStatus::Confirmed, $booking->status);
        $this->assertFalse($booking->needsRebooking());
        $this->assertTrue($booking->slot->is($new_slot));
        Mail::assertQueued(ConsultationBookingMail::class, fn (ConsultationBookingMail $mail): bool => $mail->kind === ConsultationBookingMail::RESCHEDULED);
    }

    /**
     * Staff paste the Google Meet link; the customer is emailed it and sees it
     * on their booking page instead of the placeholder. Only https links.
     *
     * @return void
     */
    public function test_staff_add_meeting_link(): void
    {
        $booking = $this->confirmed_booking(ConsultationSlot::factory()->create());

        $this->actingAs($this->staff_user);
        Livewire::test(ViewConsultationBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Meeting link needed')
            ->callAction('meetingLink', ['meeting_link' => 'http://meet.google.com/abc-defg-hij'])
            ->assertHasActionErrors(['meeting_link']);

        $this->assertNull($booking->refresh()->meeting_link);

        Livewire::test(ViewConsultationBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('meetingLink', ['meeting_link' => 'https://meet.google.com/abc-defg-hij'])
            ->assertHasNoActionErrors()
            ->assertNotified('Meeting link saved; the customer has been emailed');

        $booking->refresh();
        $this->assertSame('https://meet.google.com/abc-defg-hij', $booking->meeting_link);
        $this->assertSame($this->staff_user->id, $booking->meeting_link_added_by);

        Mail::assertQueued(ConsultationBookingMail::class, function (ConsultationBookingMail $mail): bool {
            $mail->assertSeeInHtml('https://meet.google.com/abc-defg-hij');
            $mail->assertDontSeeInHtml('To be added by our team');

            return $mail->kind === ConsultationBookingMail::MEETING_LINK;
        });

        // Not offered for WhatsApp consultations.
        $whatsapp_booking = $this->confirmed_booking(ConsultationSlot::factory()->ofType(ConsultationType::WhatsApp)->create(['start_time' => '15:00:00']), 'other@example.com');
        Livewire::test(ViewConsultationBooking::class, ['record' => $whatsapp_booking->getRouteKey()])
            ->assertActionHidden('meetingLink');
    }

    /**
     * Final bookings can't be changed again.
     *
     * @return void
     */
    public function test_final_bookings_cannot_be_managed(): void
    {
        $booking = $this->confirmed_booking(ConsultationSlot::factory()->create());
        app(CancelConsultationBooking::class)->handle($booking, $this->staff_user);

        $this->assertFalse($this->staff_user->can('manage', $booking->refresh()));

        $this->actingAs($this->staff_user);
        Livewire::test(ViewConsultationBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('cancelBooking')
            ->assertActionHidden('reschedule')
            ->assertActionHidden('meetingLink');
    }

    /**
     * A paid order with a confirmed booking of the slot.
     *
     * @param  ConsultationSlot  $slot
     * @param  string  $email
     * @return ConsultationBooking
     */
    private function confirmed_booking(ConsultationSlot $slot, string $email = 'ada@example.com'): ConsultationBooking
    {
        $order = Order::factory()->guest()->for($this->consultation)->create([
            'status' => OrderStatus::Paid,
            'guest_email' => $email,
            'total_amount' => $this->consultation->price,
        ]);

        return ConsultationBooking::factory()->confirmed()->for($order)->for($slot, 'slot')->create();
    }

    /**
     * No place left in the slot.
     *
     * @param  ConsultationSlot  $slot
     * @return void
     */
    private function assert_slot_full(ConsultationSlot $slot): void
    {
        $this->assertSame(0, $slot->remainingCapacity());

        try {
            app(BookConsultation::class)->handle($slot->id, ['name' => 'Someone', 'email' => 'someone@example.com', 'phone' => '08030000000']);
            $this->fail('A full slot was booked.');
        } catch (SlotUnavailable) {
            $this->addToAssertionCount(1);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Consultations\BookConsultation;
use App\Actions\Payments\InitiatePayment;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\SlotUnavailable;
use App\Http\Requests\BookConsultationRequest;
use App\Models\ConsultationSlot;
use App\Models\Service;
use App\Support\TrackedOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "Book a consultation" (requirements PDF section 6).
 *
 * Pick an open slot, give contact details, pay. Like "Buy Result Checker" it
 * reuses the existing checkout: BookConsultation creates the order (through
 * CreateServiceRequest) with the slot held, InitiatePayment starts Paystack,
 * and the webhook, receipt and order page work as for any other order.
 */
class ConsultationBookingController extends Controller
{
    /**
     * Open slots grouped by date, and the booking form.
     *
     * @return View
     */
    public function index(): View
    {
        $service = Service::bookableConsultation();

        $slots = $service === null ? collect() : ConsultationSlot::query()
            ->upcomingForBooking()
            ->withOccupiedCount()
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->filter(fn (ConsultationSlot $slot): bool => $slot->isOpenForBooking() && $slot->remainingCapacity() > 0)
            ->groupBy(fn (ConsultationSlot $slot): string => $slot->date->toDateString());

        return view('consultations.book', [
            'service' => $service,
            'slots_by_date' => $slots,
            'hold_minutes' => (int) config('madeeasy.consultations.hold_minutes'),
        ]);
    }

    /**
     * Hold the slot, create the order and send the customer to Paystack.
     *
     * The visitor is granted tracking access to the new order in this session,
     * so the Paystack return page and "Pay now" work for guests. If Paystack
     * can't be reached they land on the order page, where the slot is still
     * held for them and they can try again.
     *
     * @param  BookConsultationRequest  $request
     * @param  BookConsultation  $book_consultation
     * @param  InitiatePayment  $initiate_payment
     * @return RedirectResponse
     */
    public function book(BookConsultationRequest $request, BookConsultation $book_consultation, InitiatePayment $initiate_payment): RedirectResponse
    {
        try {
            $order = $book_consultation->handle($request->slot_id(), $request->contact(), $request->user());
        } catch (SlotUnavailable $exception) {
            return back()->withInput($request->except('slot'))->withErrors(['slot' => $exception->getMessage()]);
        } catch (BusinessRuleException $exception) {
            return back()->withInput()->withErrors(['book' => $exception->getMessage()]);
        }

        TrackedOrders::grant($request, $order);

        try {
            return redirect()->away($initiate_payment->handle($order));
        } catch (BusinessRuleException $exception) {
            return redirect()
                ->route('orders.track.show', $order)
                ->withErrors(['payment' => $exception->getMessage()]);
        }
    }
}

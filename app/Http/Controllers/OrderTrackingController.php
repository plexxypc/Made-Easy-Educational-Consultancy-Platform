<?php

namespace App\Http\Controllers;

use App\Actions\Orders\FindTrackableOrder;
use App\Actions\Orders\RespondToNeedsAction;
use App\Actions\Pins\LogPinAccess;
use App\Enums\OrderStatus;
use App\Enums\PinStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\LookUpOrderRequest;
use App\Http\Requests\RespondToOrderRequest;
use App\Models\Order;
use App\Models\ResultCheckerPin;
use App\Support\TrackedOrders;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Customer-facing order tracking: guest lookup and the order page (with the
 * "needs action" response form). The signed-in account dashboard ("My
 * Requests") is CustomerDashboardController.
 *
 * Access to one order: TrackedOrders::allows() (owner, or reference + contact
 * proven in this session). Staff manage orders in the back office instead.
 */
class OrderTrackingController extends Controller
{
    /**
     * "Track your request" lookup form.
     *
     * @return View
     */
    public function lookup_form(): View
    {
        return view('orders.track');
    }

    /**
     * Check reference + contact; on a match remember it in the session and
     * open the order page. Rate limited (order-lookups).
     *
     * @param  LookUpOrderRequest  $request
     * @param  FindTrackableOrder  $find_trackable_order
     * @return RedirectResponse
     */
    public function lookup(LookUpOrderRequest $request, FindTrackableOrder $find_trackable_order): RedirectResponse
    {
        $order = $find_trackable_order->handle($request->validated('reference'), $request->validated('contact'));

        if ($order === null) {
            return back()
                ->withInput($request->only('reference'))
                ->withErrors(['reference' => "We couldn't find a request with those details. Check the reference number and use the same email or phone number you gave us."]);
        }

        $request->session()->regenerate();
        TrackedOrders::grant($request, $order);

        return redirect()->route('orders.track.show', $order);
    }

    /**
     * The order page: status, answers, documents, notes and (if needed) the response form.
     *
     * Guests see document filenames only; the signed-in owner also gets download
     * links through the existing /documents/{id} route (OrderDocumentPolicy).
     *
     * Delivered result checker PINs are shown in plaintext to whoever may view
     * the order. LogPinAccess records it when that viewer is signed in as
     * staff/admin (e.g. they looked the order up here); customers aren't logged.
     * The page is never cached.
     *
     * @param  Request  $request
     * @param  Order  $order
     * @param  LogPinAccess  $log_pin_access
     * @return Response|RedirectResponse
     */
    public function show(Request $request, Order $order, LogPinAccess $log_pin_access): Response|RedirectResponse
    {
        if (! TrackedOrders::allows($request, $order)) {
            return redirect()
                ->route('orders.track')
                ->withInput(['reference' => $order->reference])
                ->with('status', 'Enter the email or phone number you used to view this request. For your security, we ask again after '.config('madeeasy.order_lookups.guest_idle_minutes').' minutes without activity.');
        }

        $order->load(['service', 'documents' => fn ($query) => $query->orderBy('uploaded_at')->orderBy('id')]);

        $pins = $order->isResultChecker()
            ? $order->pins()->where('status', PinStatus::Revealed->value)->get()
            : new Collection;

        if ($request->user() !== null) {
            $pins->each(fn (ResultCheckerPin $pin) => $log_pin_access->handle($pin, $request->user(), $request->ip()));
        }

        return response()->view('orders.show', [
            'order' => $order,
            'booking' => $order->isConsultation() ? $order->consultationBooking()->with('slot')->first() : null,
            'pins' => $pins,
            'undelivered_pins' => $order->undeliveredPinCount(),
            'answers' => $order->labelledFieldAnswers(),
            'timeline' => $order->statusChanges()->whereNotNull('note')->get(),
            'needs_action_note' => $order->status === OrderStatus::NeedsAction ? $order->needsActionNote() : null,
            'can_download' => TrackedOrders::is_owner($request, $order),
            'receipt' => $order->receipt,
            'upload_rules' => config('madeeasy.uploads'),
            'max_documents' => RespondToOrderRequest::MAX_DOCUMENTS,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Customer response to "needs action". Rate limited (order-responses).
     *
     * @param  RespondToOrderRequest  $request
     * @param  Order  $order
     * @param  RespondToNeedsAction  $respond_to_needs_action
     * @return RedirectResponse
     */
    public function respond(RespondToOrderRequest $request, Order $order, RespondToNeedsAction $respond_to_needs_action): RedirectResponse
    {
        try {
            $respond_to_needs_action->handle(
                $order,
                $request->validated('note'),
                $request->uploadedDocuments(),
                $request->user(),
            );
        } catch (BusinessRuleException $exception) {
            return back()->withInput()->withErrors(['response' => $exception->getMessage()]);
        }

        return redirect()
            ->route('orders.track.show', $order)
            ->with('status', "Thank you. We've received your response and will continue with your request.");
    }
}

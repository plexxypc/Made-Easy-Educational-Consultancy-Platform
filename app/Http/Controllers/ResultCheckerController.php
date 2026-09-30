<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateServiceRequest;
use App\Actions\Payments\InitiatePayment;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\BuyResultCheckerRequest;
use App\Models\Service;
use App\Support\TrackedOrders;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "Buy Result Checker" (requirements PDF section 5).
 *
 * The short path to payment: choose a checker and quantity, give contact
 * details, and go straight to Paystack. It reuses the existing checkout
 * pieces rather than a second checkout: CreateServiceRequest makes the
 * order, InitiatePayment starts the payment, and the order tracking page,
 * webhook and receipt work exactly as for any other order.
 */
class ResultCheckerController extends Controller
{
    /**
     * The store page.
     *
     * @return View
     */
    public function index(): View
    {
        $services = Service::query()
            ->resultCheckersForSale()
            ->withCount(['pins as available_pins_count' => fn (Builder $query) => $query->available()])
            ->orderBy('name')
            ->get();

        return view('result-checker.index', [
            'services' => $services,
            'max_quantity' => (int) config('madeeasy.pins.max_quantity_per_order'),
        ]);
    }

    /**
     * Create the order and send the customer to Paystack.
     *
     * The visitor is granted tracking access to the new order in this session
     * (as if they had looked it up), so the Paystack return page and "Pay now"
     * work for guests. If Paystack can't be reached, they land on the order
     * page, where they can try again.
     *
     * @param  BuyResultCheckerRequest  $request
     * @param  CreateServiceRequest  $create_service_request
     * @param  InitiatePayment  $initiate_payment
     * @return RedirectResponse
     */
    public function buy(BuyResultCheckerRequest $request, CreateServiceRequest $create_service_request, InitiatePayment $initiate_payment): RedirectResponse
    {
        try {
            $order = $create_service_request->handle(
                $request->service(),
                $request->contact(),
                [],
                [],
                $request->user(),
                $request->quantity(),
            );
        } catch (BusinessRuleException $exception) {
            return back()->withInput()->withErrors(['buy' => $exception->getMessage()]);
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

<?php

namespace App\Http\Controllers;

use App\Actions\Customers\ClaimGuestOrders;
use App\Actions\Customers\DismissGuestOrderClaims;
use App\Actions\Customers\FindClaimableGuestOrders;
use App\Actions\Pins\LogPinAccess;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\ClaimGuestOrdersRequest;
use App\Models\OrderStatusChange;
use App\Models\ResultCheckerPin;
use App\Support\CustomerDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The signed-in customer's account dashboard ("My Requests", PDF section 8):
 * one tab per section, all read through CustomerDashboard (own orders only),
 * plus the prompt to add guest orders placed with their verified email.
 */
class CustomerDashboardController extends Controller
{
    /**
     * Show one tab (?tab=requests|payments|documents|consultations|pins|messages).
     *
     * PINs are rendered into the page (behind a "Show PIN" toggle), so as on the
     * tracking page LogPinAccess is called for each: it records staff/admin
     * viewers and ignores customers. Opening Messages marks updates as read.
     * The page is never cached.
     *
     * @param  Request  $request
     * @param  LogPinAccess  $log_pin_access
     * @param  FindClaimableGuestOrders  $find_claimable_guest_orders
     * @return Response
     */
    public function show(Request $request, LogPinAccess $log_pin_access, FindClaimableGuestOrders $find_claimable_guest_orders): Response
    {
        $user = $request->user();
        $dashboard = new CustomerDashboard($user);
        $tab = CustomerDashboard::tab($request->query('tab'));

        $data = match ($tab) {
            'requests' => ['pending_orders' => $dashboard->pending_orders(), 'completed_orders' => $dashboard->completed_orders()],
            'payments' => ['payments' => $dashboard->payments()],
            'documents' => ['documents' => $dashboard->documents()],
            'consultations' => $dashboard->bookings(),
            'pins' => ['pins' => $dashboard->pins()],
            'messages' => ['messages' => $dashboard->messages()],
        };

        if ($tab === 'pins') {
            $data['pins']->each(fn (ResultCheckerPin $pin) => $log_pin_access->handle($pin, $user, $request->ip()));
        }

        if ($tab === 'messages') {
            $data['unread_ids'] = $data['messages']->getCollection()
                ->filter(fn (OrderStatusChange $status_change): bool => $dashboard->is_unread($status_change))
                ->modelKeys();

            $dashboard->mark_messages_read();
        }

        return response()->view('account.dashboard', [
            'tab' => $tab,
            'tabs' => CustomerDashboard::TABS,
            'tab_counts' => $dashboard->tab_counts(),
            'claimable_orders' => $find_claimable_guest_orders->handle($user),
            'dismissed_claim_count' => $tab === 'requests' ? $find_claimable_guest_orders->dismissed_count($user) : 0,
            'email_verified' => $user->hasVerifiedEmail(),
            ...$data,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Accept the prompt: add the guest orders shown to this account.
     *
     * @param  ClaimGuestOrdersRequest  $request
     * @param  ClaimGuestOrders  $claim_guest_orders
     * @return RedirectResponse
     */
    public function claim(ClaimGuestOrdersRequest $request, ClaimGuestOrders $claim_guest_orders): RedirectResponse
    {
        try {
            $claimed = $claim_guest_orders->handle($request->user(), $request->order_ids());
        } catch (BusinessRuleException $exception) {
            return redirect()->route('my-requests')->withErrors(['claim' => $exception->getMessage()]);
        }

        return redirect()
            ->route('my-requests')
            ->with('status', $claimed > 0
                ? trans_choice(':count request has been added to your account.|:count requests have been added to your account.', $claimed)
                : 'There was nothing new to add to your account.');
    }

    /**
     * "Not now" on the prompt: stop showing it for these guest orders.
     *
     * @param  Request  $request
     * @param  DismissGuestOrderClaims  $dismiss_guest_order_claims
     * @return RedirectResponse
     */
    public function dismiss_claim(Request $request, DismissGuestOrderClaims $dismiss_guest_order_claims): RedirectResponse
    {
        $dismiss_guest_order_claims->handle($request->user());

        return redirect()
            ->route('my-requests')
            ->with('status', "OK, we won't ask about those requests again. You can still add them later from Services & Applications.");
    }

    /**
     * Bring back the prompt for guest orders dismissed earlier.
     *
     * @param  Request  $request
     * @param  DismissGuestOrderClaims  $dismiss_guest_order_claims
     * @return RedirectResponse
     */
    public function restore_claim(Request $request, DismissGuestOrderClaims $dismiss_guest_order_claims): RedirectResponse
    {
        $dismiss_guest_order_claims->restore($request->user());

        return redirect()->route('my-requests');
    }
}

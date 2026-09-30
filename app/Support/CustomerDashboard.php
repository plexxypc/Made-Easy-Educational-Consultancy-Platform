<?php

namespace App\Support;

use App\Enums\ConsultationBookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PinStatus;
use App\Models\ConsultationBooking;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderStatusChange;
use App\Models\Payment;
use App\Models\ResultCheckerPin;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everything on the customer's account dashboard ("My Requests", PDF section 8),
 * read from the existing orders, payments, receipts, documents, bookings,
 * PINs and order history. Nothing new is stored except messages_read_at.
 *
 * Access rule: every section is limited to orders_query(), i.e. the
 * policy-aligned Order::visibleTo() scope narrowed to orders the user placed
 * (or claimed). Staff/admin therefore see only their own purchases here, like
 * any customer; the back office is where they see everyone's. Soft-deleted
 * orders are excluded, as on the tracking page (OrderPolicy::track).
 *
 * Grouping (no new statuses):
 *  - Pending: pending_payment, paid, in_progress, needs_action.
 *  - Completed: completed and cancelled (closed; the badge says which).
 *    Assumption: the PDF has only the two groups, and a cancelled request is
 *    finished from the customer's point of view.
 */
class CustomerDashboard
{
    /**
     * Tab keys, in display order, with their PDF section 8 titles.
     *
     * @var array<string, string>
     */
    public const TABS = [
        'requests' => 'Services & Applications',
        'payments' => 'Payment History',
        'documents' => 'Uploaded Documents',
        'consultations' => 'Consultation Appointments',
        'pins' => 'Result Checker Tokens',
        'messages' => 'Messages',
    ];

    /**
     * @var int
     */
    public const PER_PAGE = 15;

    /**
     * @param  User  $user
     */
    public function __construct(
        public readonly User $user,
    ) {}

    /**
     * A valid tab key (unknown values fall back to the first tab).
     *
     * @param  string|null  $tab
     * @return string
     */
    public static function tab(?string $tab): string
    {
        return array_key_exists((string) $tab, self::TABS) ? (string) $tab : array_key_first(self::TABS);
    }

    /**
     * The user's own orders. Every other section is filtered through this.
     *
     * @return Builder<Order>
     */
    public function orders_query(): Builder
    {
        return Order::query()->visibleTo($this->user)->whereBelongsTo($this->user);
    }

    /**
     * Open requests, newest first.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function pending_orders(): LengthAwarePaginator
    {
        return $this->orders_with_card_data()
            ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
            ->paginate(self::PER_PAGE, pageName: 'pending_page')
            ->withQueryString();
    }

    /**
     * Completed and cancelled requests, newest first.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function completed_orders(): LengthAwarePaginator
    {
        return $this->orders_with_card_data()
            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
            ->paginate(self::PER_PAGE, pageName: 'completed_page')
            ->withQueryString();
    }

    /**
     * Successful payments with their receipts, newest first.
     *
     * @return LengthAwarePaginator<int, Payment>
     */
    public function payments(): LengthAwarePaginator
    {
        return Payment::query()
            ->whereIn('order_id', $this->order_ids())
            ->where('status', PaymentStatus::Successful->value)
            ->with(['order.service', 'receipt'])
            ->latest('paid_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Every document on the user's orders, newest first. Opened through the
     * existing /documents/{id} route (OrderDocumentPolicy; staff views audited).
     *
     * @return LengthAwarePaginator<int, OrderDocument>
     */
    public function documents(): LengthAwarePaginator
    {
        return OrderDocument::query()
            ->whereIn('order_id', $this->order_ids())
            ->with('order.service')
            ->latest('uploaded_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Consultation bookings split into upcoming (soonest first) and past
     * (latest first). Upcoming: not cancelled/closed and not yet ended.
     *
     * @return array{upcoming: Collection<int, ConsultationBooking>, past: Collection<int, ConsultationBooking>}
     */
    public function bookings(): array
    {
        [$upcoming, $past] = ConsultationBooking::query()
            ->whereIn('order_id', $this->order_ids())
            ->with(['slot', 'order.service'])
            ->get()
            ->partition(fn (ConsultationBooking $booking): bool => ! $booking->status->isFinal()
                && $booking->slot->endsAt()->isFuture());

        return [
            'upcoming' => $upcoming->sortBy(fn (ConsultationBooking $booking): int => $booking->slot->startsAt()->getTimestamp())->values(),
            'past' => $past->sortByDesc(fn (ConsultationBooking $booking): int => $booking->slot->startsAt()->getTimestamp())->values(),
        ];
    }

    /**
     * PINs delivered to the user's orders, newest first.
     *
     * @return LengthAwarePaginator<int, ResultCheckerPin>
     */
    public function pins(): LengthAwarePaginator
    {
        return ResultCheckerPin::query()
            ->whereIn('order_id', $this->order_ids())
            ->where('status', PinStatus::Revealed->value)
            ->with('order.service')
            ->latest('revealed_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * The order history of every order (status changes, staff messages,
     * needs-action notes and the customer's own responses), newest first.
     *
     * Assumption: every status change is included, not only those with a
     * note, because each one was also emailed to the customer as an update.
     *
     * @return LengthAwarePaginator<int, OrderStatusChange>
     */
    public function messages(): LengthAwarePaginator
    {
        return OrderStatusChange::query()
            ->whereIn('order_id', $this->order_ids())
            ->with('order.service')
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Whether an update is unread: from us (not the customer's own
     * response) and newer than when they last opened Messages.
     *
     * @param  OrderStatusChange  $status_change
     * @return bool
     */
    public function is_unread(OrderStatusChange $status_change): bool
    {
        return ! $status_change->by_customer
            && ($this->user->messages_read_at === null || $status_change->created_at->greaterThan($this->user->messages_read_at));
    }

    /**
     * Unread updates, for the tab badge.
     *
     * @return int
     */
    public function unread_message_count(): int
    {
        return OrderStatusChange::query()
            ->whereIn('order_id', $this->order_ids())
            ->where('by_customer', false)
            ->when($this->user->messages_read_at, fn (Builder $query, $read_at) => $query->where('created_at', '>', $read_at))
            ->count();
    }

    /**
     * Opening Messages marks everything up to now as read. The page being
     * shown still marks what was new before this visit.
     *
     * @return void
     */
    public function mark_messages_read(): void
    {
        $this->user->forceFill(['messages_read_at' => now()])->save();
    }

    /**
     * Item counts for the tab labels (messages: unread only).
     *
     * @return array<string, int>
     */
    public function tab_counts(): array
    {
        return [
            'requests' => $this->orders_query()->count(),
            'payments' => Payment::query()->whereIn('order_id', $this->order_ids())->where('status', PaymentStatus::Successful->value)->count(),
            'documents' => OrderDocument::query()->whereIn('order_id', $this->order_ids())->count(),
            'consultations' => ConsultationBooking::query()->whereIn('order_id', $this->order_ids())->whereNot('status', ConsultationBookingStatus::Cancelled->value)->count(),
            'pins' => ResultCheckerPin::query()->whereIn('order_id', $this->order_ids())->where('status', PinStatus::Revealed->value)->count(),
            'messages' => $this->unread_message_count(),
        ];
    }

    /**
     * Subquery of the user's order ids.
     *
     * @return Builder<Order>
     */
    private function order_ids(): Builder
    {
        return $this->orders_query()->select('orders.id');
    }

    /**
     * Orders with what the request cards show, newest first.
     *
     * @return Builder<Order>
     */
    private function orders_with_card_data(): Builder
    {
        return $this->orders_query()
            ->with(['service', 'consultationBooking.slot', 'receipt'])
            ->latest()
            ->latest('id');
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Support\TrackedOrders;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base for actions on the customer order page (respond, pay).
 *
 * Allowed for the order's owner, or a visitor who looked it up in this session
 * within the idle timeout (TrackedOrders::allows()).
 */
abstract class TrackedOrderRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return TrackedOrders::allows($this, $this->order());
    }

    /**
     * Not allowed (e.g. the guest's lookup expired): back to the lookup form
     * rather than a bare 403, without doing anything.
     *
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()
                ->route('orders.track')
                ->withInput(['reference' => $this->order()->reference])
                ->with('status', 'For your security, please look up your request again.'),
        );
    }

    /**
     * The order from the route.
     *
     * @return Order
     */
    public function order(): Order
    {
        return $this->route('order');
    }
}

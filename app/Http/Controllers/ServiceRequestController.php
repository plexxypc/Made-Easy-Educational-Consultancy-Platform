<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateServiceRequest;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\SubmitServiceRequest;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Public "Request this service" submission (guest checkout, no payment).
 */
class ServiceRequestController extends Controller
{
    /**
     * Create the order and redirect to the confirmation page.
     *
     * @param  SubmitServiceRequest  $request
     * @param  ServiceCategory  $category
     * @param  Service  $service
     * @param  CreateServiceRequest  $create_service_request
     * @return RedirectResponse
     */
    public function store(
        SubmitServiceRequest $request,
        ServiceCategory $category,
        Service $service,
        CreateServiceRequest $create_service_request,
    ): RedirectResponse {
        try {
            $order = $create_service_request->handle(
                $service,
                $request->contact(),
                $request->answers(),
                $request->uploadedFiles(),
                $request->user(),
            );
        } catch (BusinessRuleException $exception) {
            return back()->withInput()->withErrors(['request' => $exception->getMessage()]);
        }

        return redirect()->to(URL::signedRoute('service-requests.received', ['order' => $order]));
    }

    /**
     * Confirmation page. The signed URL stops people browsing other references;
     * it shows only the reference and service name (no personal details).
     *
     * @param  Order  $order
     * @return View
     */
    public function received(Order $order): View
    {
        return view('services.received', [
            'order' => $order->load('service'),
        ]);
    }
}

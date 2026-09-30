<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderDocumentController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\ResultCheckerController;
use App\Http\Controllers\ServiceCatalogueController;
use App\Http\Controllers\ServiceRequestController;
use App\Livewire\Hello;
use Illuminate\Support\Facades\Route;

/*
| Public site
*/
Route::get('/', HomeController::class)->name('home');

Route::get('services', [ServiceCatalogueController::class, 'index'])->name('services.index');
Route::get('services/{category:slug}', [ServiceCatalogueController::class, 'category'])->name('services.category');
Route::get('services/{category:slug}/{service:slug}', [ServiceCatalogueController::class, 'show'])
    ->scopeBindings()
    ->name('services.show');
Route::post('services/{category:slug}/{service:slug}/request', [ServiceRequestController::class, 'store'])
    ->scopeBindings()
    ->middleware('throttle:service-requests')
    ->name('services.request');
Route::get('requests/{order}/received', [ServiceRequestController::class, 'received'])
    ->middleware('signed')
    ->name('service-requests.received');

// "Track your request": reference + email/phone lookup, then the order page.
// Order URLs use the reference, not the id, so a URL never reveals more than the customer already has.
Route::get('track', [OrderTrackingController::class, 'lookup_form'])->name('orders.track');
Route::post('track', [OrderTrackingController::class, 'lookup'])
    ->middleware('throttle:order-lookups')
    ->name('orders.track.lookup');
Route::get('track/{order:reference}', [OrderTrackingController::class, 'show'])->name('orders.track.show');
Route::post('track/{order:reference}/respond', [OrderTrackingController::class, 'respond'])
    ->middleware('throttle:order-responses')
    ->name('orders.respond');

// Payments (Paystack). Only the verified webhook marks an order paid.
Route::post('track/{order:reference}/pay', [OrderPaymentController::class, 'pay'])
    ->middleware('throttle:order-payments')
    ->name('orders.pay');
Route::get('track/{order:reference}/payment/return', [OrderPaymentController::class, 'payment_return'])
    ->middleware('throttle:order-payments')
    ->name('orders.payment.return');
Route::get('track/{order:reference}/receipt', [OrderPaymentController::class, 'receipt'])->name('orders.receipt');

// Server-to-server from Paystack; CSRF-exempt (bootstrap/app.php), authenticated by signature.
Route::post('webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');

// "Buy Result Checker": choose, pay, receive PINs. Orders then use the normal tracking/payment routes.
Route::get('result-checker', [ResultCheckerController::class, 'index'])->name('result-checker');
Route::post('result-checker/buy', [ResultCheckerController::class, 'buy'])
    ->middleware(['throttle:service-requests', 'throttle:order-payments'])
    ->name('result-checker.buy');

// Placeholder: updates/blog is built in a later prompt.
Route::view('updates', 'pages.updates')->name('updates');

Route::view('about', 'pages.about')->name('about');
Route::view('contact', 'pages.contact')->name('contact');

Route::get('hello', Hello::class)->name('hello');

/*
| Signed-in area
*/
Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('my-requests', [OrderTrackingController::class, 'my_requests'])
    ->middleware(['auth'])
    ->name('my-requests');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Owners and staff/admin only (OrderDocumentPolicy); staff/admin views are audited.
Route::get('documents/{order_document}', [OrderDocumentController::class, 'show'])
    ->middleware(['auth'])
    ->name('order-documents.show');

require __DIR__.'/auth.php';

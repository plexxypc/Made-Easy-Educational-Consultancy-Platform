<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderDocumentController;
use App\Http\Controllers\OrderTrackingController;
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
Route::get('track', [OrderTrackingController::class, 'lookup_form'])->name('orders.track');
Route::post('track', [OrderTrackingController::class, 'lookup'])
    ->middleware('throttle:order-lookups')
    ->name('orders.track.lookup');
Route::get('track/{order}', [OrderTrackingController::class, 'show'])->name('orders.track.show');
Route::post('track/{order}/respond', [OrderTrackingController::class, 'respond'])
    ->middleware('throttle:order-responses')
    ->name('orders.respond');

// Placeholders: the result checker store and updates/blog are built in later prompts.
Route::view('result-checker', 'pages.result-checker')->name('result-checker');
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

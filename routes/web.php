<?php

use App\Http\Controllers\AdmissionSearchController;
use App\Http\Controllers\ConsultationBookingController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderDocumentController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\ResultCheckerController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ServiceCatalogueController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\UpdatesController;
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

// "Book a consultation": pick a slot, pay. Orders then use the normal tracking/payment routes.
Route::get('book-consultation', [ConsultationBookingController::class, 'index'])->name('book-consultation');
Route::post('book-consultation', [ConsultationBookingController::class, 'book'])
    ->middleware(['throttle:service-requests', 'throttle:order-payments'])
    ->name('book-consultation.book');

// Course & cut-off search: informational only (no application or payment).
Route::get('admission/course-search', AdmissionSearchController::class)
    ->middleware('throttle:course-search')
    ->name('admission.search');

// Updates (blog): published posts only; drafts and scheduled posts 404.
Route::get('updates', [UpdatesController::class, 'index'])->name('updates');
Route::get('updates/category/{post_category:slug}', [UpdatesController::class, 'category'])->name('updates.category');
// Draft/scheduled preview: expiring signed link (Post::previewUrl) AND a staff/admin sign-in.
Route::get('updates/preview/{post}', [UpdatesController::class, 'preview'])
    ->middleware(['auth', 'signed'])
    ->name('updates.preview');
Route::get('updates/{slug}', [UpdatesController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('updates.show');

Route::get('about', [PageController::class, 'about'])->name('about');
Route::view('contact', 'pages.contact')->name('contact');
Route::get('faq', [PageController::class, 'faq'])->name('faq');
// Promo only: tutoring is arranged over WhatsApp, not booked or paid here.
Route::get('maths-tutoring', [PageController::class, 'maths_tutoring'])->name('maths-tutoring');

// Testimonials: approved ones only. Submitting needs access to a completed order (TestimonialController).
Route::get('testimonials', [TestimonialController::class, 'index'])->name('testimonials');
Route::get('track/{order:reference}/testimonial', [TestimonialController::class, 'create'])->name('orders.testimonial');
Route::post('track/{order:reference}/testimonial', [TestimonialController::class, 'store'])
    ->middleware('throttle:testimonials')
    ->name('orders.testimonial.store');

// Generated so they carry the live host; public/robots.txt must not exist (Caddy would serve it instead).
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::get('hello', Hello::class)->name('hello');

/*
| Signed-in area
*/
// Where sign-in, registration and email verification land: the account dashboard.
// Not behind "verified": unverified accounts still see orders placed while signed in.
Route::redirect('dashboard', 'my-requests')
    ->middleware(['auth'])
    ->name('dashboard');

Route::get('my-requests', [CustomerDashboardController::class, 'show'])
    ->middleware(['auth'])
    ->name('my-requests');
Route::post('my-requests/claim', [CustomerDashboardController::class, 'claim'])
    ->middleware(['auth', 'verified', 'throttle:account-claims'])
    ->name('my-requests.claim');
Route::post('my-requests/claim/dismiss', [CustomerDashboardController::class, 'dismiss_claim'])
    ->middleware(['auth', 'throttle:account-claims'])
    ->name('my-requests.claim.dismiss');
Route::post('my-requests/claim/restore', [CustomerDashboardController::class, 'restore_claim'])
    ->middleware(['auth', 'throttle:account-claims'])
    ->name('my-requests.claim.restore');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Owners and staff/admin only (OrderDocumentPolicy); staff/admin views are audited.
Route::get('documents/{order_document}', [OrderDocumentController::class, 'show'])
    ->middleware(['auth'])
    ->name('order-documents.show');

require __DIR__.'/auth.php';

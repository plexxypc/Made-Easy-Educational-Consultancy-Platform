<?php

/*
|--------------------------------------------------------------------------
| Made Easy platform settings
|--------------------------------------------------------------------------
|
| Application-specific configuration. Read values through config('madeeasy.*')
| rather than env() so they survive `php artisan config:cache`.
|
*/

return [

    /*
     * Initial admin account created by Database\Seeders\AdminUserSeeder.
     * In production SEED_ADMIN_PASSWORD must be set; the seeder refuses to
     * create an admin with the local default password.
     */
    'seed_admin' => [
        'name' => env('SEED_ADMIN_NAME', 'Made Easy Admin'),
        'email' => env('SEED_ADMIN_EMAIL', 'admin@madeeasy.test'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

    /*
     * Public contact details shown on the Contact page, footer and emails.
     * Change them in .env (then `php artisan config:cache` in production);
     * no code change needed. Placeholders until the client confirms.
     */
    'contact' => [
        'business_name' => env('CONTACT_BUSINESS_NAME', 'Made Easy Educational Consultancy'),
        'phone' => env('CONTACT_PHONE', '+234 800 000 0000'),
        'whatsapp' => env('CONTACT_WHATSAPP', '+234 800 000 0000'),
        'email' => env('CONTACT_EMAIL', 'hello@madeeasy.example'),
        'address' => env('CONTACT_ADDRESS', 'Office address to be confirmed'),
        'hours' => env('CONTACT_HOURS', 'Monday – Saturday, 9am – 5pm'),
    ],

    /*
     * Staff notifications. Customer responses to "needs action" always appear in
     * the back-office notification bell; set this to also email every staff/admin.
     */
    'notifications' => [
        'email_staff_on_customer_response' => (bool) env('EMAIL_STAFF_ON_CUSTOMER_RESPONSE', false),
    ],

    /*
     * Order tracking lookups ("Track your request"): attempts per minute per IP,
     * and per hour per reference number (slows guessing from many IPs).
     */
    'order_lookups' => [
        'per_minute_per_ip' => 5,
        'per_hour_per_reference' => 10,
        // A guest who looked an order up must do so again after this long without using the page.
        'guest_idle_minutes' => (int) env('GUEST_TRACKING_IDLE_MINUTES', 30),
    ],

    /*
     * Paystack. Credentials live in config/services.php (PAYSTACK_*).
     * Staff are always notified in the back office when a payment needs a
     * human (wrong amount, order not payable); this also emails them.
     */
    'payments' => [
        'currency' => 'NGN',
        'email_staff_on_review' => (bool) env('EMAIL_STAFF_ON_PAYMENT_REVIEW', true),
        // The Paystack return page re-checks this many times (every 5 seconds) while waiting for the webhook.
        'return_page_max_checks' => 12,
    ],

    /*
     * Result checker PIN stock.
     *  - low_stock_threshold: DEFAULT low-stock level, used for any service
     *    whose own "low stock at" value (services.pin_low_stock_threshold) is
     *    empty. At or below it, the stock widget warns and staff are notified
     *    (bell + email) once per crossing; again at zero.
     *  - max_quantity_per_order: largest quantity on "Buy Result Checker".
     *  - email_staff_on_shortfall: a paid order that couldn't get all its PINs
     *    always notifies staff in the back office; this also emails them
     *    (on by default: a customer has paid and is waiting).
     */
    'pins' => [
        'low_stock_threshold' => (int) env('PIN_LOW_STOCK_THRESHOLD', 20),
        'max_quantity_per_order' => (int) env('PIN_MAX_QUANTITY_PER_ORDER', 10),
        'email_staff_on_shortfall' => (bool) env('EMAIL_STAFF_ON_PIN_SHORTFALL', true),
    ],

    /*
     * Consultation booking (/book-consultation).
     *  - timezone: slot dates and times are wall-clock times in this zone
     *    (the app itself runs in UTC).
     *  - hold_minutes: choosing a slot holds it for this long while the
     *    customer pays; after that it is free for others again.
     *  - min_notice_minutes: slots starting sooner than this can't be booked.
     *  - booking_window_days: how far ahead the booking page lists slots.
     *  - default_duration_minutes: length of a new slot unless changed.
     *  - max_slots_per_batch: cap on "Add recurring slots" in one go.
     */
    'consultations' => [
        'timezone' => env('CONSULTATION_TIMEZONE', 'Africa/Lagos'),
        'hold_minutes' => (int) env('CONSULTATION_HOLD_MINUTES', 30),
        'min_notice_minutes' => (int) env('CONSULTATION_MIN_NOTICE_MINUTES', 60),
        'booking_window_days' => (int) env('CONSULTATION_BOOKING_WINDOW_DAYS', 60),
        'default_duration_minutes' => 60,
        'max_slots_per_batch' => 300,
    ],

    /*
     * Customer uploads on service request forms ("file" fields). Files go to the
     * private disk as order_documents. Extensions are checked against the
     * detected (content) MIME type, and the MIME type is re-checked on store.
     * Keep max_kilobytes below PHP's upload_max_filesize (docker/php/php.ini).
     */
    'uploads' => [
        'max_kilobytes' => (int) env('UPLOAD_MAX_KILOBYTES', 5120),
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
        'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
    ],

];

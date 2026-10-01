# Made Easy Educational Consultancy Platform

Laravel 13 platform for Made Easy Educational Consultancy. This repository is
currently the **infrastructure skeleton only**: auth, roles, admin panel and a
deployable Docker stack. There are no business features (services, orders,
payments) yet.

| Layer        | Choice                                                     |
| ------------ | ---------------------------------------------------------- |
| Framework    | Laravel 13 on PHP 8.4 (PHP-FPM, Alpine)                    |
| Auth         | Laravel Breeze, Livewire (Volt) stack                      |
| Frontend     | Blade + Livewire 4 + Tailwind CSS (built with Vite)        |
| Admin panel  | Filament 5 at `/admin`                                     |
| Database     | PostgreSQL 16                                              |
| Queue/cache  | Database driver (no Redis)                                 |
| Web server   | Caddy (automatic HTTPS) → PHP-FPM                          |

## Docker services

`docker-compose.yml` defines four services, used unchanged locally and in production:

- **app**: PHP-FPM running Laravel (image built from `Dockerfile`, target `app`).
- **worker**: the same image running `php artisan queue:work database`.
- **caddy**: reverse proxy and HTTPS termination, and serves static files from
  `public/`. Configured by `docker/caddy/Caddyfile`; the domain comes from
  `SITE_ADDRESS` in `.env`.
- **db**: PostgreSQL 16 with data in the `db_data` named volume.

Named volumes:

- `db_data`: the database.
- `app_storage`: Laravel `storage/`, including private uploads in
  `storage/app/private`. Caddy never mounts it.
- `public_media`: the `public` disk (`storage/app/public`), which holds only
  Updates images. Caddy mounts it read-only and serves it at `/storage/*`.
- `caddy_data` and `caddy_config`: TLS certificates. They must persist so
  Let's Encrypt isn't re-hit.

Code and front-end assets are baked into the images, so **after changing code, rebuild**:
`docker compose up -d --build`.

## Running locally

Prerequisites: Docker Desktop (or Docker Engine + Compose v2). PHP, Composer and
Node are **not** needed on the host.

```bash
# 1. Configure
cp .env.example .env

# 2. Generate an app key and paste it into APP_KEY in .env
docker compose run --rm --no-deps app php artisan key:generate --show

# 3. Build and start the stack
docker compose up -d --build

# 4. Create the schema and the admin user
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

Then open:

- <https://localhost>: public site (Breeze login/register at `/login`, `/register`)
- <https://localhost/hello>: Livewire smoke-test page
- <https://localhost/admin>: Filament admin panel. There is one login for
  everyone at `/login`; `/admin` sends guests there.

Local accounts (password `password`):

| Email                      | Role     | Seeded by                              |
| -------------------------- | -------- | -------------------------------------- |
| `admin@madeeasy.test`      | admin    | `AdminUserSeeder` (from `SEED_ADMIN_*`) |
| `test-admin@madeeasy.test` | admin    | `TestUsersSeeder` (local only)          |
| `staff@madeeasy.test`      | staff    | `TestUsersSeeder` (local only)          |
| `customer@madeeasy.test`   | customer | `TestUsersSeeder` (local only)          |

**Local certificate warning:** for `localhost`, Caddy uses its own internal CA,
so the browser will warn. Either accept the warning, trust Caddy's root cert
(`docker compose cp caddy:/data/caddy/pki/authorities/local/root.crt .` and
import it), or run over plain HTTP by setting in `.env`:

```dotenv
SITE_ADDRESS=http://localhost
APP_URL=http://localhost
SESSION_SECURE_COOKIE=false
```

and running `docker compose up -d`. If ports 80/443 are taken, change
`HTTP_PORT` / `HTTPS_PORT`.

Useful commands:

```bash
docker compose ps                         # service status
docker compose logs -f app worker caddy   # logs (Laravel logs to stderr)
docker compose exec app php artisan tinker
docker compose down                       # stop (keeps volumes)
docker compose down -v                    # stop AND delete all data volumes
```

## Migrations and seeders

Migrations are **never run automatically** on container start, so a deploy
can't change the schema unexpectedly. Run them explicitly:

```bash
docker compose exec app php artisan migrate            # local
docker compose exec app php artisan migrate --force    # production (APP_ENV=production)
docker compose exec app php artisan migrate:rollback   # every migration implements down()
```

Seeders:

```bash
docker compose exec app php artisan db:seed            # runs DatabaseSeeder
docker compose exec app php artisan db:seed --force    # production
```

`DatabaseSeeder` runs, in order (all safe to re-run):

- `AdminUserSeeder` creates or updates one admin from `SEED_ADMIN_NAME`,
  `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD`. In production it refuses to
  run unless `SEED_ADMIN_PASSWORD` is set.
- `ServiceCatalogueSeeder` seeds the 4 categories and 40 services from the
  requirements document. It only creates missing rows (matched on slug), so it
  never overwrites prices an admin has changed. Services without a documented
  price get a ₦1,000 placeholder and are seeded **inactive**; quote services
  have price 0.
- `PostCategorySeeder` seeds the 12 Updates categories from PDF section 10
  (JAMB News … NIN/JAMB Information). It only creates missing ones.
- `TestUsersSeeder` seeds the staff/admin/customer test accounts above. It is
  skipped in production.
- `AdmissionSampleDataSeeder` seeds **SAMPLE** course search data: 11 real
  institutions, 11 courses and 49 cut-off marks. **The cut-off marks and credit
  counts are illustrative, not official.** Skipped in production. Before
  go-live, clear it (Course search → Cut-off marks → select all → Delete, as
  an admin; then delete the institutions and courses) and import the client's
  dataset. To show the sample on another environment on purpose, run
  `php artisan db:seed --class=AdmissionSampleDataSeeder`.

## Roles

`users.role` is one of `customer` (default), `staff` or `admin`
(`App\Enums\UserRole`). It is not mass-assignable. Checks available:

```php
$user->hasRole('admin', 'staff');   // any of; accepts strings or UserRole cases
$user->isAdmin(); $user->isStaff(); $user->isCustomer(); $user->isStaffOrAdmin();
```

```php
Route::middleware(['auth', 'role:admin,staff'])->group(...);   // 403 otherwise
```

```blade
@role('admin', 'staff') ... @else ... @endrole
```

Only `staff` and `admin` users can enter `/admin` (`User::canAccessPanel()`
plus the role middleware). A signed-in customer gets a 403 page saying
"You are not authorized to access this area."
Hiding UI with `@role` is cosmetic; always enforce roles server-side too.

**Where new back-office routes go:**

- `routes/staff.php` is served at `/staff/*` with `auth` + `role:staff,admin`.
- `routes/admin.php` is served at `/admin/*` with `auth` + `role:admin`. Pick
  paths that won't clash with Filament.

The middleware is applied in `bootstrap/app.php`, so individual routes don't
need to repeat it. `AdminAccessTest` fails if any `/admin*` or `/staff*` route
lacks a role gate.

**Policies:**

- `OrderPolicy` and `OrderDocumentPolicy`: customers can see only their own
  records, staff and admin can see all, and only admin can delete. Orders are
  soft-deleted.
- For lists, use `Order::query()->visibleTo($user)`.

**Documents:** files live on the private `local` disk and are served only by
`GET /documents/{order_document}` (`OrderDocumentController`). Each staff/admin
view writes a `document_access_logs` row via `App\Actions\Documents\LogDocumentAccess`.
Serve document contents only through `App\Actions\Documents\OpenOrderDocument`.
The controller and Filament's Download action both use it, and it calls
`LogDocumentAccess`.

## Public site

The public pages are Blade with Tailwind and need no JavaScript; the mobile menu is a native `<details>` element.
They use the `<x-public-layout>` component. The nav comes from
`App\Support\PublicNavigation` and follows PDF section 19.

| URL | Page |
| --- | --- |
| `/` | Homepage: PDF headline and buttons, plus live categories that have active services |
| `/services` | All categories with active services and prices |
| `/services/{category}` | One category (Admission = `admission-services`, Documents = `document-services`) |
| `/services/{category}/{service}` | Service detail and the **Request this service** form |
| `/result-checker` | **Buy Result Checker**: pick a checker and quantity, then **Buy now** straight to Paystack |
| `/book-consultation` | **Book a Consultation**: pick an open time, give contact details, pay through Paystack |
| `/updates` | **Updates** (blog): published posts, newest first, 9 per page, with category links |
| `/updates/category/{slug}` | Published posts in one category |
| `/updates/{slug}` | One published post, with its meta title and description as the page `<title>` and `<meta>` |
| `/updates/preview/{id}` | Staff/admin preview of a draft or scheduled post: expiring signed link (24 hours) **and** a staff/admin sign-in; `noindex` |
| `/admission/course-search` | **Course & cut-off search** (PDF section 11): JAMB score, O'Level credits, course, state, all optional; informational, links to Admission Guidance |
| `/about`, `/contact` | About (placeholder copy) and Contact |
| `/track` | **Track your request**: reference plus the email or phone used |
| `/track/{reference}` | Order page: status, answers, documents, messages, **Pay now**, receipt and the needs-action response form |
| `/track/{reference}/receipt` | Payment receipt PDF, opened in the browser; `?download=1` saves it as `receipt-MER-000042.pdf` |
| `/track/{reference}/payment/return` | Where Paystack sends the customer back; display only |
| `POST /webhooks/paystack` | Paystack webhook (signature-checked, CSRF-exempt) |
| `/my-requests?tab=` | Customer dashboard (login required); `/dashboard` redirects here |
| `POST /my-requests/claim` | Add guest orders placed with the account's verified email (verified accounts only) |

**Customer dashboard (`/my-requests`, PDF section 8).** Data comes from
`App\Support\CustomerDashboard`, and everything is scoped by
`Order::visibleTo($user)->whereBelongsTo($user)`. It has these tabs:

- `requests`: Pending and Completed. Cancelled orders are listed under Completed.
- `payments`: successful payments, each with a receipt and download link.
- `documents`: documents from all orders, served through the audited
  `/documents/{id}` route.
- `consultations`: upcoming and past bookings, with the meeting link once set.
- `pins`: allocated PINs behind **Show PIN**. Only staff and admin views are
  audit-logged.
- `messages`: status changes and staff notes from all orders, newest first.
  Opening this tab marks them read (`users.messages_read_at`).

**Email verification and claiming guest orders.** `User` implements
`MustVerifyEmail`. Once the email is verified, `App\Actions\Customers\FindClaimableGuestOrders`
looks for guest orders (no `user_id`) whose `guest_email` matches the account
email, ignoring case and surrounding spaces. The dashboard asks the customer
before linking them. `App\Actions\Customers\ClaimGuestOrders` then sets
`user_id` and `claimed_at`. It re-checks every id under a row lock, so repeat
claims do nothing and it never takes an order owned by someone else. There is
no phone-based or reference-based claiming.

**Not now** (`POST /my-requests/claim/dismiss`) sets
`users.guest_claims_dismissed_at`, so the choice holds across sessions and
devices. After that:

- The prompt no longer appears for guest orders placed before that time.
- A guest order placed later asks again, listing only the new one.
- The Services & Applications tab keeps a small "Review them" link
  (`POST /my-requests/claim/restore`) in case the customer changes their mind.
- Changing the account email clears the dismissal.

**Service requests.** The form is built from each service's `customer_fields`,
set in the admin under Services, then "Information required from the customer".

- Validation is in `App\Http\Requests\SubmitServiceRequest`.
- `App\Actions\Orders\CreateServiceRequest` creates a `pending_payment` order
  with the contact details in `guest_*`.
- Files from **file** fields go through `App\Actions\Documents\StoreOrderDocument`.
  They are stored on the private disk under `order-documents/{order_id}/`, and the
  MIME type is re-checked from the file contents. Allowed types are PDF, JPG and
  PNG, up to `UPLOAD_MAX_KILOBYTES` each.
- A queued `ServiceRequestReceivedMail` gives the customer their reference (not
  sent for result checkers or consultations, whose receipt and PIN or booking
  emails already confirm the order). The reference is a
  random code such as `ME-7K2QXD` (`Order::generateReference()`, Crockford
  base32, unique). Orders created before this change keep their old numeric
  reference (shown as `#12`). Submissions are rate-limited to 5 per minute per IP.
- Instant services (result checkers), consultation services and inactive
  services can't be requested through this form; result checkers are bought on
  `/result-checker` and consultations booked on `/book-consultation`.

**Order tracking.** `App\Actions\Orders\FindTrackableOrder` matches the
reference against the guest or account email (any case), or the phone number
after normalising (spaces, dashes and `+234` are ignored). A wrong reference and
a wrong contact give the same error.

- A match is remembered in the session (`App\Support\TrackedOrders`). It
  expires after `GUEST_TRACKING_IDLE_MINUTES` (default 30) without activity on
  that order. The signed-in owner skips the lookup (`OrderPolicy::track`) and
  never times out.
- References are matched case-insensitively, with or without the dash, `#` or
  spaces, and O/I/L are read as 0/1/1.
- Guests see document filenames only. The owner also gets download links
  through the existing `/documents/{id}` route.
- Lookups are rate-limited to 5 per minute per IP and 10 per hour per reference
  (`config/madeeasy.php`, `order_lookups`). Responses are limited to 5 per minute per IP.

**Responding to "Needs action".** The order page shows the staff note and a
form for a message and/or up to 5 documents. `App\Actions\Orders\RespondToNeedsAction`:

- stores the files through `StoreOrderDocument` (the same private path as the request form);
- records a customer status change (`order_status_changes.by_customer`);
- returns the order to the status it had before "needs action";
- notifies every staff and admin user through `CustomerRespondedToOrder`;
- emails the customer a confirmation (`NeedsActionResponseReceivedMail`).

Staff see that notification in the back-office bell, which polls every 30 seconds.
Set `EMAIL_STAFF_ON_CUSTOMER_RESPONSE=true` to also email them.

**Payments (Paystack).** An order is payable only while it is `pending_payment`
with a total above ₦0. A quote order becomes payable only after **Set Total**
(`Order::paymentBlocker()` explains why an order can't be paid).

- **Pay now** (order page and My Requests) runs `App\Actions\Payments\InitiatePayment`.
  It creates a `payments` row (`initiated`, amount in kobo, reference `MEPAY-…`)
  and redirects to Paystack checkout. Each click creates a new row.
- **Only the webhook marks an order paid.** `PaystackWebhookController` checks
  `x-paystack-signature` (HMAC-SHA512 of the raw body with `PAYSTACK_SECRET_KEY`)
  and rejects anything else with a 401. `charge.success` goes to
  `App\Actions\Payments\ConfirmPaystackCharge`, which locks the payment row, so a
  repeat delivery does nothing.
- The charge is applied only if the status, currency (NGN) and amount exactly
  match and the order is still `pending_payment`. Then the payment becomes
  `successful`, the order `paid`, and a `receipts` snapshot is created and
  emailed (`PaymentReceiptMail`) with the receipt PDF attached. The PDF is
  rendered by `App\Actions\Receipts\RenderReceiptPdf` (dompdf, template
  `resources/views/receipts/pdf.blade.php`). The same PDF is served from the
  order page and dashboard. dompdf runs with remote files, PHP and JavaScript
  off. Payment times on receipts (PDF, email, order page, Payment History)
  are shown in Lagos time and labelled "(Lagos time, WAT)"
  (`Receipt::paidAtLabel()`); they are stored in UTC.
- Anything else is **not applied**. The payment keeps a `review_reason`, the
  event is logged at error level, and staff get a `PaymentNeedsReview`
  notification (plus email when `EMAIL_STAFF_ON_PAYMENT_REVIEW=true`). The order
  screen shows a red "Payment needs review" callout. Refunds or manual fixes are
  done by a person in the Paystack dashboard.
- The return page calls Paystack's verify API only to decide what to *show*
  ("confirming…", with a 5-second refresh). It never changes an order.

Set the webhook URL in the Paystack dashboard to `https://<domain>/webhooks/paystack`.
To test locally, expose the site with a tunnel (e.g. ngrok) and use test keys.

**Result checker PINs.** A result checker is an active **instant** service
priced above ₦0 (`Service::resultCheckersForSale()`).

- **Buying.** `/result-checker` (`ResultCheckerController`, `BuyResultCheckerRequest`)
  takes the checker, a quantity (1 to `PIN_MAX_QUANTITY_PER_ORDER`) and contact
  details. It reuses the normal flow: `CreateServiceRequest` makes a
  `pending_payment` order with `quantity` and `total_amount = price × quantity`,
  then `InitiatePayment` sends the customer to Paystack. There is no form to fill
  in and no second checkout. Guests keep session access to the order, as with requests.
- **Stock.** `result_checker_pins` holds each PIN and optional serial number.
  Both use Laravel's `encrypted` cast; `pin_hash` (an HMAC keyed with `APP_KEY`)
  enforces uniqueness across all services. Status: `available` → `allocated` →
  `revealed` (shown as "Delivered": emailed and visible to the customer).
- **Allocation.** `ConfirmPaystackCharge` fires `App\Events\OrderPaid` after it
  applies a charge. The queued listener `AllocateResultCheckerPins` runs
  `App\Actions\Pins\AllocatePinsToOrder`. In one transaction it locks the order
  and claims up to `quantity` available PINs with `SELECT … FOR UPDATE SKIP LOCKED`,
  so two payments never get the same PIN. `DeliverAllocatedPins` then marks them
  delivered, emails them (`ResultCheckerPinsMail`), and completes the order
  automatically once every unit has a PIN.
- **Short stock.** If stock runs out, the order stays `paid` (the money is never
  refused), what is available is delivered, and the rest is flagged. Staff get
  `PinsNeedManualFulfilment` in the bell (plus email when
  `EMAIL_STAFF_ON_PIN_SHORTFALL=true`), the order shows a red "PINs still to
  deliver" callout, and the order can't be marked completed until it is fully
  delivered. The fix is **Deliver PIN manually** on the order: allocate from
  stock after a top-up, or type in a one-off PIN. The customer is emailed
  straight away that their PIN is delayed (`ResultCheckerDelayedMail`).
- **Top-ups fulfil waiting orders.** After an upload, `FulfilWaitingPinOrders`
  runs `AllocatePinsToOrder` (the same locking) for every paid order still owed
  PINs, oldest first, stopping at the first it can't complete.
- **Low-stock alerts.** Each result checker has an optional low-stock threshold
  (Services → edit), falling back to `PIN_LOW_STOCK_THRESHOLD`.
  `CheckPinStockLevel` runs after allocations, uploads and deletions. It sends
  staff and admins `PinStockRunningLow` (bell and email) once when stock falls
  to the threshold and once when it hits zero. A restock resets it silently.
- **Customers** see their PINs on the order tracking page and in My Requests
  (served with `Cache-Control: no-store`). They see "N PINs on the way" while
  any are owed.

**Consultations.** The bookable consultation is the oldest active service with
fulfilment type **Consultation** and a price above ₦0
(`Service::bookableConsultation()`). Admins add times under Consultations →
Availability (`consultation_slots`: date, start time, length, type, places).
Times are in `CONSULTATION_TIMEZONE`.

- **Booking.** `/book-consultation` lists open times with a free place, at
  least `CONSULTATION_MIN_NOTICE_MINUTES` ahead. `BookConsultation` claims the
  slot (`ReserveConsultationSlot`: `SELECT … FOR UPDATE SKIP LOCKED` on the slot
  row, then a capacity check). It then creates the order through
  `CreateServiceRequest` and a `pending_payment` booking that holds the place
  for `CONSULTATION_HOLD_MINUTES`, and hands over to the normal `InitiatePayment`.
  **Pay now** re-checks the slot and renews the hold.
- **Confirmation.** The queued `OrderPaid` listener
  `ConfirmConsultationBookingOnPayment` runs `ConfirmConsultationBooking`; the
  webhook has no consultation logic. If the slot still has room, the booking is
  `confirmed` and the customer is emailed the date, time and type. If the hold
  lapsed and someone else took the place, the money is kept, the booking shows
  "Paid – needs a new time", and staff get `ConsultationNeedsRebooking`.
- **Meeting links** are never generated. For Google Meet and online
  consultations, the email and order page show "To be added by our team" until
  staff use **Add meeting link** on the booking.
- **Cancellations.** A cancelled booking frees its place; completed and no-show
  bookings keep it. Cancelling a slot cancels every booking on it and emails
  those customers. Refunds are done by hand in Paystack.

**Contact details** (Contact page, footer, emails) come from the `CONTACT_*`
variables in `.env`. After changing them in production, run
`docker compose exec app php artisan config:cache`.

With `LOG_CHANNEL=stderr` and `MAIL_MAILER=log`, emails appear in
`docker compose logs worker`.

## Back office (`/admin`)

| Screen | Who | Notes |
| --- | --- | --- |
| Dashboard | staff, admin | Counts for needs action, pending, and orders in the last 7 days |
| Orders | staff, admin | List, filters, detail, status actions, documents |
| Catalogue: Services / Categories | admin | Prices typed in naira and stored as kobo |
| Administration: Users & roles | admin | Change role; admins cannot change their own role |
| Catalogue: PIN stock | admin | Upload PINs (paste one per line or CSV of `pin[,serial]`); PINs always masked; per-service stock widget |
| Orders → PINs tab | staff, admin | Result checker orders only; **Show PIN** is audited |
| Administration: Access log (documents & PINs) | admin | Read-only; search by staff member or order reference |
| Consultations: Bookings | staff, admin | Filter by date and status; add meeting link, reschedule, mark completed / no-show, cancel (customer emailed, except completed / no-show) |
| Consultations: Availability | admin | Add one slot or a weekly pattern; cancel a slot (booked customers emailed); delete never-booked slots |
| Updates: Posts | staff write, admin publishes | Rich text, featured image with alt text, category, auto-slug, SEO fields, publish date/time; list shows Published / Scheduled / Draft; **Preview** on unpublished posts |
| Course search: Cut-off marks | staff, admin | Import CSV (all or nothing), download template, add/edit single rows; bulk delete admin only |
| Course search: Institutions / Courses | staff, admin | Plain CRUD; delete only once nothing uses them |

**Updates posts** (`App\Models\Post`, `PostPolicy`):

- **Status** comes from `published_at`. Empty is a draft, a future time is
  scheduled, and a past time is published. A scheduled post goes live by
  itself because public pages compare against now(), so no job is needed.
  Publish times are entered in `BLOG_TIMEZONE` (default Africa/Lagos).
- **Staff** create posts and edit or delete drafts. They cannot set a publish
  date: the field is disabled and `App\Actions\Posts\SavePost` drops it on the
  server. They also cannot change a post once it is scheduled or live.
- **Admins** do everything, including **Publish now** and **Unpublish** from
  the list (`SetPostPublishDate`, logged).
- **Body HTML** from the editor is sanitised on output by Filament's renderer
  (`Post::bodyHtml()`), which strips scripts, event handlers and
  `javascript:` links.
- **Images** (featured image and images in the body) go to the `public` disk
  under `posts/`. They must be JPEG, PNG or WebP, up to
  `BLOG_IMAGE_MAX_KILOBYTES` (default 2 MB), with the type checked from the
  contents. SVG is refused. A replaced or deleted featured image is removed
  from disk. A featured image needs alt text, which is used on the post page.

**Course search data** (`Institution`, `Course`, `AdmissionRequirement`).
CSV import (`App\Actions\Admissions\ImportAdmissionRequirements`):

- **Header row required.** Required columns: `institution`, `course`,
  `cut_off_mark` (1–400) and `olevel_credits_required` (1–9). Optional columns:
  `type` (University / Polytechnic / College of Education), `ownership`
  (Federal / State / Private) and `state` (a state name, or FCT).
- Columns can be in any order, and common spellings such as "Cut-off mark"
  are accepted. Unknown columns are refused.
- Names containing a comma must be in double quotes. Download the template
  from the screen for an example.
- **All or nothing.** Any bad line rejects the file, and the problems are
  listed by line number (line 1 is the header).
- Institutions and courses are **matched by name**, ignoring case and extra
  spaces, and are created if new. Abbreviations are not matched: "UNILAG" is
  a different institution from "University of Lagos".
- An institution and course that are already listed get the new cut-off mark
  and credits. The same pair twice in one file is an error.
- A new institution with no type, ownership or state is still created, and the
  result lists it so someone can complete it. Until then it is left out of
  searches by state.
- Blank details on an existing institution are filled in from the file.
  Details that contradict what is stored are an error.

**Search matching** (`AdmissionRequirement::matching()`):

- The **cut-off mark** must be at or below the student's score. Meeting it
  exactly counts.
- The **credits required** must be at or below the student's credits.
- The **course name** must contain the typed text, ignoring case.
- The **institution** must be in the chosen state.
- A blank field is not checked. Results are sorted by cut-off mark, highest
  first.

**PIN uploads** (`App\Actions\Pins\ImportPinBatch`) are all-or-nothing. Any
malformed line, duplicate within the batch, or PIN already in stock (for any
service) rejects the whole batch, with the line numbers listed. The dashboard
widget shows available PINs per checker, amber at or below the checker's
threshold (default `PIN_LOW_STOCK_THRESHOLD`) and red at zero, plus how many
paid orders are waiting. A successful upload immediately fulfils waiting orders.

**Sensitive access audit.** Staff/admin opening a customer document
(`LogDocumentAccess`) or revealing an allocated PIN (`LogPinAccess`) writes a
row to `document_access_logs`. PIN rows use `result_checker_pin_id` instead of
`order_document_id`. Customers viewing their own PINs are not logged.
PIN values never appear in any list, only in the Show PIN modal on the order
they belong to.

Access is enforced by the policies in `app/Policies`, not only by hidden
navigation. Business rules live in `app/Actions`, and Filament only calls them.

**Order status workflow** (`OrderStatus::allowedNextStatuses()`):

- `pending_payment` → needs action or cancelled; → in progress only when the
  total is ₦0. Orders with a total must be paid first.
- `paid` → in progress, completed, needs action, or cancelled. Only the verified
  Paystack webhook sets `paid`.
- `in_progress` → completed, needs action, or cancelled.
- `needs_action` → in progress or cancelled. Needs action requires a note.
- `completed` and `cancelled` are final.

Every change is recorded in `order_status_changes` and emailed to the customer
(a queued `OrderStatusChangedMail`, sent by the worker).

Orders for **quote** services (priced ₦0) can only be cancelled until an admin
uses **Set Total**, which records `quoted_at`/`quoted_by`.

In the services list, **inactive placeholders** means inactive services still
on the ₦1,000 seed price. They need a real price from the client before they
are switched on. **Possible duplicates** shows the two service pairs the PDF
lists twice (`Service::POSSIBLE_DUPLICATES`), which are waiting on a client
decision.

## Queue and task scheduler

- `QUEUE_CONNECTION=database`; the **worker** service processes jobs
  continuously and is restarted by Docker if it exits (`--max-time=3600`
  recycles it hourly). Failed jobs land in `failed_jobs`
  (`php artisan queue:failed`, `queue:retry all`).
- Scheduled tasks are defined in `routes/console.php`. Add this **host** cron
  entry (`crontab -e` on the VPS) to trigger them every minute:

  ```cron
  * * * * * cd /opt/madeeasy && docker compose exec -T app php artisan schedule:run >> /dev/null 2>&1
  ```

  Check with `docker compose exec app php artisan schedule:list`.
- `consultations:remind-missing-meeting-links` runs every 15 minutes. It sends
  staff and admins one notification (database and mail) for each confirmed
  online or Google Meet booking that starts within 24 hours and has no meeting
  link. `consultation_bookings.meeting_link_reminder_sent_at` stops a second
  reminder, and rescheduling to a new time clears it.

## Tests

The runtime image has no dev dependencies, so tests run in a throwaway
container with `vendor/` kept in a Docker volume (fast even on Windows):

```bash
docker run --rm -v "$PWD:/var/www/html" -v madeeasy_test_vendor:/var/www/html/vendor \
  --user root --entrypoint sh madeeasy-app:latest \
  -c "composer install --no-interaction --quiet && php artisan test"
```

(PowerShell: use `"${PWD}:/var/www/html"`.) Tests use in-memory SQLite from `phpunit.xml`.

`PinAllocationConcurrencyTest` and `ConsultationBookingConcurrencyTest` need
real row locks, so they are skipped on SQLite. They start several PHP
processes that allocate from the same stock, or claim the same consultation
slot, at the same moment. To run it, and the whole suite, against PostgreSQL, create a separate
test database once (never point tests at `madeeasy`, because they wipe it), then
pass the connection in:

```bash
docker compose exec -T db createdb -U madeeasy madeeasy_test
docker run --rm --network madeeasy_internal \
  -e DB_CONNECTION=pgsql -e DB_HOST=db -e DB_DATABASE=madeeasy_test \
  -e DB_USERNAME=madeeasy -e DB_PASSWORD="<DB_PASSWORD from .env>" \
  -v "$PWD:/var/www/html" -v madeeasy_test_vendor:/var/www/html/vendor \
  --user root --entrypoint sh madeeasy-app:latest -c "php artisan test"
```

## First deploy: Hostinger VPS checklist

Assumes a fresh Ubuntu 24.04 KVM VPS and a domain you control.

1. **DNS**: create `A` (and `AAAA` if using IPv6) records for the domain (and
   `www` if wanted) pointing at the VPS IP. Wait until they resolve, or Caddy
   can't obtain certificates.
2. **Server prep** (as root or a sudo user):
   ```bash
   apt update && apt upgrade -y
   curl -fsSL https://get.docker.com | sh          # Docker Engine + Compose plugin
   ufw allow OpenSSH && ufw allow 80/tcp && ufw allow 443/tcp && ufw allow 443/udp && ufw enable
   ```
   Also check that the Hostinger panel firewall (if enabled) allows 80/443.
3. **Clone the repo**:
   ```bash
   git clone <repo-url> /opt/madeeasy && cd /opt/madeeasy
   ```
4. **Create `.env`**: `cp .env.example .env`, then edit:
   - `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`
   - `APP_URL=https://your-domain.com`
   - `SITE_ADDRESS=your-domain.com, www.your-domain.com` and `ACME_EMAIL=you@your-domain.com`
   - `DB_PASSWORD=` a long random value (`openssl rand -base64 32`)
   - `MAIL_MAILER=smtp` plus real `MAIL_*` SMTP credentials and `MAIL_FROM_ADDRESS`
   - `PAYSTACK_PUBLIC_KEY` / `PAYSTACK_SECRET_KEY` (live keys when ready)
   - `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` (strong password)
   - `chmod 600 .env`
5. **App key**:
   ```bash
   docker compose run --rm --no-deps app php artisan key:generate --show   # paste into APP_KEY
   ```
6. **Build and start the containers**:
   ```bash
   docker compose up -d --build
   docker compose ps          # app & db should become "healthy"
   ```
7. **Migrate and seed**:
   ```bash
   docker compose exec app php artisan migrate --force
   docker compose exec app php artisan db:seed --force
   ```
   Afterwards, remove `SEED_ADMIN_PASSWORD` from `.env`.
8. **Point Caddy at the real domain.** This is done by `SITE_ADDRESS` in step 4.
   Watch `docker compose logs -f caddy` for "certificate obtained successfully",
   then check `https://your-domain.com`, `/hello` and `/admin`.
9. **Scheduler**: add the cron entry from [Queue and task scheduler](#queue-and-task-scheduler).
10. **Backups** (recommended): nightly `pg_dump`, e.g.
    `docker compose exec -T db pg_dump -U madeeasy madeeasy | gzip > /root/backups/madeeasy-$(date +\%F).sql.gz`,
    plus the `app_storage` volume (private uploads) and the `public_media`
    volume (Updates images). Copy them all off the server.

**Subsequent deploys:**

```bash
cd /opt/madeeasy && git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

In production the containers run `php artisan optimize` and `filament:optimize`
on start. After editing `.env`, run `docker compose up -d` to recreate the
containers and rebuild the config cache.

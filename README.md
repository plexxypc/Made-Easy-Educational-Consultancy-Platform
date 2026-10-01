# Made Easy Educational Consultancy Platform

Laravel 13 platform for Made Easy Educational Consultancy: public site,
service requests, Paystack payments, result checker PINs, consultation
bookings, Updates (blog), course search, and the Filament back office.

**Launching?** Follow [GO-LIVE.md](GO-LIVE.md) step by step. The
[Production](#production-hostinger-kvm-2) section below explains each step.

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

`docker-compose.yml` defines four services. Production adds
`docker-compose.prod.yml` on top (restart policies, limits, log rotation, the
scheduler and backups; see [Production](#production-hostinger-kvm-2)), and
local development never uses it:

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
- `FaqSeeder` seeds the 7 FAQs for PDF section 13. **Pending:** until the
  official wording is pasted into `database/data/faqs_section_13.php`, it
  seeds drafted stand-ins. Once that file holds the seven questions, re-run
  `php artisan db:seed --class=FaqSeeder --force`: it adds the official
  questions, removes drafted ones nobody edited, and names any edited draft so
  it can be reviewed in Website → FAQ (`App\Actions\Faqs\InstallOfficialFaqs`).
  It never overwrites an answer an admin changed.
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
| `/` | Homepage: PDF headline and buttons, the "students assisted" figure (once set), live categories, latest updates, maths tutorials promo, approved testimonials and the first 4 FAQs |
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
| `/about`, `/contact` | About (text from Site settings) and Contact |
| `/faq` | All active FAQs, with schema.org `FAQPage` structured data (JSON-LD) |
| `/testimonials` | Approved testimonials only |
| `/maths-tutoring` | Online Mathematics tutorials (PDF section 12): arranged on WhatsApp, not booked or paid here. Linked from the homepage, the services page and the footer |
| `/sitemap.xml`, `/robots.txt` | Generated (current host). The sitemap lists public pages, categories with active services, active services, Updates categories and published posts |
| `/track` | **Track your request**: reference plus the email or phone used |
| `/track/{reference}` | Order page: status, answers, documents, messages, **Pay now**, receipt, the needs-action response form and, once completed, **Share your experience** |
| `/track/{reference}/testimonial` | Testimonial form: completed orders only, same access as the order page, once per order |
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
  reference (shown as `#12`). Submissions are rate-limited (see [Rate limits](#rate-limits)).
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
- Lookups are rate-limited per IP and per reference; responses per IP (see
  [Rate limits](#rate-limits)).

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
  enforces uniqueness across all services. Changing `APP_KEY` therefore needs
  `php artisan pins:rekey` (see [Rotating APP_KEY](#rotating-app_key)). Status: `available` → `allocated` →
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
| Course search: Institutions / Courses | staff, admin | Plain CRUD; delete only once nothing uses them. Institutions have **aliases** ("also known as") for the CSV import |
| Website: Testimonials | staff, admin | Opens on those waiting for review; Approve / Reject (also in bulk); add one received elsewhere; delete admin only. Menu badge = pending count |
| Website: FAQ | admin (staff read-only) | Question, answer, shown on/off, drag to reorder; live at once |
| Website: Site settings | admin | Homepage "students assisted" figure and wording, About Us text; live at once |

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
- **Featured image copies.** On save, `MakeFeaturedImageDisplayCopy` (GD)
  writes a WebP copy at most 1200px wide, at quality 80, under
  `posts/featured/display/`. The site serves that copy, and the original upload
  is kept. If no copy can be made (GD missing, an unreadable image, or more than
  25 megapixels), the original is served and a warning is logged. For posts
  saved before this existed, run
  `docker compose exec app php artisan posts:make-display-images` once.
  Images inside the post body are **not** resized.
- **Slugs of published posts** no longer follow the title. Editing one shows a
  red warning, and saving sends a notification naming the old address. There
  are no redirects, so old links break.

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
  spaces, and are created if new. An institution also matches any of its
  **aliases** (set on the institution, e.g. "UNILAG" for "University of
  Lagos"), so a file mixing both spellings updates one institution. The same
  course under both spellings is reported as a duplicate. Other abbreviations
  still create a new institution, so add them as aliases first. An alias can't
  be another institution's name or alias. Courses have no aliases.
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
- Scheduled tasks are defined in `routes/console.php`. In production the
  **scheduler** service (`docker-compose.prod.yml`) runs `php artisan
  schedule:work`, so no host cron entry is needed. Don't add one as well, or
  every task runs twice. Locally, run `docker compose exec app php artisan
  schedule:run` by hand when you need to. Check with `php artisan schedule:list`.
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

**One Postgres run at a time per database.** Two runs against the same test
database break each other: one run's `migrate:fresh` drops tables while the
other is creating or using them. `RefreshDatabase` then retries the migration
on every following test, so almost the whole suite fails. `tests/TestCase.php`
prevents this with a Postgres advisory lock. A second run prints "Another test
run is using the "madeeasy_test" database; waiting…" and starts when the first
finishes (it gives up after 20 minutes). To run in parallel, give each run its
own `DB_DATABASE`.

**Checking a running deployment.** `ProductionDeploymentTest` (group
`production`) makes real HTTPS requests through Caddy. It checks that `.env`,
`.git`, `vendor/`, `storage/` and source files return 404, that the security
headers are on pages and static files, and that `/up` answers. It is skipped
unless `PRODUCTION_CHECK_URL` is set. Run it from any machine with Docker:

```bash
docker run --rm -e PRODUCTION_CHECK_URL=https://your-domain.com -e PRODUCTION_CHECK_HSTS=31536000 \
  -v "$PWD:/var/www/html" -v madeeasy_test_vendor:/var/www/html/vendor \
  --user root --entrypoint sh madeeasy-app:latest -c "php artisan test --group=production"
```

Leave out `PRODUCTION_CHECK_HSTS` until HSTS is switched on. For a local run
of the production config, add `-e PRODUCTION_CHECK_INSECURE=1` (Caddy's
internal CA), `--network madeeasy_internal` and
`-e PRODUCTION_CHECK_RESOLVE=localhost:443:<caddy container IP>`.

## Rate limits

Every limit is in `config/madeeasy.php` → `rate_limits`, and nothing else
hardcodes one. Route forms use named `throttle:<name>` middleware
(`AppServiceProvider`). Livewire auth forms all post to `/livewire/update`, so
they limit themselves through `App\Support\FormThrottle`. Over a limit, route
forms answer 429; the tracking form and the Livewire forms show "Too many
attempts…" on the form instead. `RateLimitTest` has one test per form, plus a
test that fails if any new public POST route has no throttle.

| Form | Limit | Keyed by |
| --- | --- | --- |
| Service request form, Buy Result Checker, Book a consultation (one shared "create an order" budget) | 5 / minute | IP |
| Track your request (lookup) | 5 / minute, **and** 10 / hour per reference | IP; reference from any IP |
| "Needs action" reply | 5 / minute | IP |
| Testimonial submission | 5 / minute | IP |
| Pay now + Paystack return page (shared) | 10 / minute | IP |
| Course & cut-off search | 60 / minute | IP |
| My Requests claim / dismiss / restore | 5 / minute | user |
| Email verification link | 6 / minute | user |
| Sign in | 5 **failed** attempts / minute (cleared on success) | email + IP |
| Register | 5 / minute | IP |
| Forgot password (sends email) | 5 / minute, and 1 email per address per minute | IP; address |
| Reset password (with token) | 5 / minute | IP |
| Resend verification email | 5 / minute | user |
| Confirm password, change password | 5 / minute | user |

The scheme: forms that create something or send an email get 5 a minute;
guessable lookups also get a slower cap on the thing being guessed; payment
steps get 10 (the return page refreshes itself); read-only search gets 60. The
Paystack webhook is deliberately **not** limited: it is authenticated by its
signature, and Paystack retries anything that fails.

## Security headers

`App\Http\Middleware\SecurityHeaders` (global) adds `X-Content-Type-Options:
nosniff`, `X-Frame-Options: SAMEORIGIN` and `Referrer-Policy:
strict-origin-when-cross-origin` to every Laravel response. Caddy sends the
same headers for static files and strips the `Server` header; its values
replace Laravel's, so nothing is sent twice. HSTS is controlled by
`HSTS_MAX_AGE` (default 0, meaning off) in both places. Switch it on only once HTTPS works on the
real domain (GO-LIVE.md): after that, browsers refuse plain HTTP for that long.

The private `local` disk has Laravel's `serve` option **off**, so there is no
`/storage/{path}` route for private files. Uploads are only served by
`OrderDocumentController`, which checks ownership or role.

## Production (Hostinger KVM 2)

### What production adds

On the VPS, `.env` contains
`COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml`, so every plain
`docker compose …` command uses both files. Never put that line in a local `.env`.
`docker-compose.prod.yml` adds:

- `restart: always` on every long-running service, so Docker restarts a crashed container
  and starts everything after a reboot. This was checked by killing the main
  process (SIGKILL from the host) of the worker, app and scheduler, and with
  `queue:restart`. Each came back within a second, and the restarted worker
  processed a new job.
- CPU and memory caps for 2 cores / 8 GB: app 1.5 CPU / 1.5 GB, worker 1 / 512 MB,
  scheduler 0.5 / 256 MB, db 1.5 / 2 GB (Postgres tuned to match), caddy
  0.5 / 256 MB, backup 0.5 / 512 MB. That is about 5 GB, which leaves room for the OS, image builds
  and the page cache.
- Log rotation: every container uses json-file logs, 5 files × 10 MB.
- `docker/caddy/Caddyfile.prod`: the real domain (no localhost fallback;
  Compose refuses to start without `SITE_ADDRESS` and `ACME_EMAIL`), the HSTS
  setting, JSON access logs, and explicit 404s for dotfiles, `vendor/`,
  `storage/` internals and source paths. The Caddy image only contains
  `public/` anyway.
- app's healthcheck requests `/up` through php-fpm, so it fails if Laravel or
  Postgres is down.
- **scheduler**: `php artisan schedule:work` (no host cron).
- **backup**: the nightly encrypted backup; **backup-restore**: a one-off restore container.

### Production environment variables

All of these go in `/opt/madeeasy/.env` on the VPS (`chmod 600 .env`). Keep a copy
of the finished `.env` in a password manager. **It isn't in the backups**,
and without the same `APP_KEY` a restored database can't decrypt PINs.

| Variable | Production value | Notes |
| --- | --- | --- |
| `COMPOSE_FILE` | `docker-compose.yml:docker-compose.prod.yml` | VPS only |
| `APP_ENV` | `production` | containers then cache config/routes/views on start |
| `APP_DEBUG` | `false` | `true` shows stack traces and secrets to visitors |
| `APP_KEY` | generated once (`key:generate --show`) | never change it casually; see [Rotating APP_KEY](#rotating-app_key) |
| `APP_PREVIOUS_KEYS` | empty | only during a key rotation |
| `APP_URL` | `https://your-domain.com` | used in emails, sitemap, canonical links |
| `LOG_LEVEL` | `warning` | keep `LOG_CHANNEL=stderr` (rotated container logs) |
| `SITE_ADDRESS` | `your-domain.com, www.your-domain.com` | what Caddy gets certificates for |
| `ACME_EMAIL` | a monitored address | Let's Encrypt expiry notices |
| `HSTS_MAX_AGE` | `0`, then `31536000` once HTTPS is confirmed | GO-LIVE.md |
| `DB_PASSWORD` | long random (`openssl rand -base64 32`) | only read when the database volume is first created; changing it later needs `ALTER USER` too |
| `SESSION_SECURE_COOKIE` | `true` | |
| `MAIL_MAILER` | `smtp` | any transactional provider that offers SMTP |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME` | from the provider | `587` + `MAIL_SCHEME=null` (STARTTLS) or `465` + `smtps` |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | from the provider | SMTP credentials, not your mailbox password |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | e.g. `no-reply@your-domain.com` | the domain must pass the provider's SPF/DKIM checks, or mail lands in spam |
| `PAYSTACK_PUBLIC_KEY` | `pk_live_…` | Paystack → Settings → API Keys & Webhooks |
| `PAYSTACK_SECRET_KEY` | `sk_live_…` | also the **webhook secret**: Paystack signs webhooks with the secret key, and there is no separate value |
| `CONTACT_BUSINESS_NAME`, `CONTACT_PHONE`, `CONTACT_WHATSAPP`, `CONTACT_EMAIL`, `CONTACT_ADDRESS`, `CONTACT_HOURS` | the client's real details | `CONTACT_WHATSAPP` drives the floating WhatsApp button |
| `SEED_ADMIN_NAME`, `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD` | the first admin | remove the password after seeding |
| `EMAIL_STAFF_ON_*`, `PIN_*`, `CONSULTATION_*`, `UPLOAD_MAX_KILOBYTES` | defaults are fine | business tuning |
| `BACKUP_AGE_RECIPIENTS` | the age public key(s) | required; see [Backups](#backups) |
| `BACKUP_TARGET` + `BACKUP_SCP_*` or `BACKUP_S3_*` | the off-server destination | `local` is not a real backup |

### Server hardening (on the VPS, by hand)

These are done on the server itself, not from this repo. Do them before the
site goes public, and keep a second SSH session open while changing SSH.

1. **A sudo user with an SSH key** (Hostinger gives you root):
   ```bash
   adduser deploy && usermod -aG sudo deploy
   mkdir -p /home/deploy/.ssh && cp /root/.ssh/authorized_keys /home/deploy/.ssh/
   chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys
   ```
   Check that `ssh deploy@<vps-ip>` works with the key before going on.
2. **SSH: keys only, no root login.** Create `/etc/ssh/sshd_config.d/99-hardening.conf`:
   ```text
   PasswordAuthentication no
   KbdInteractiveAuthentication no
   PermitRootLogin no
   PubkeyAuthentication yes
   ```
   then run `sshd -t && systemctl reload ssh`. Open a **new** session to confirm you can still get in.
3. **Firewall: only 22, 80 and 443.**
   ```bash
   ufw default deny incoming && ufw default allow outgoing
   ufw allow OpenSSH && ufw allow 80/tcp && ufw allow 443/tcp && ufw allow 443/udp
   ufw enable && ufw status verbose
   ```
   Set the same rules in the Hostinger panel firewall if it's enabled. Docker's
   published ports bypass ufw, so **never publish the db port**. Only Caddy
   publishes ports (80/443) in these Compose files.
4. **Automatic security updates:**
   ```bash
   apt install -y unattended-upgrades && dpkg-reconfigure -plow unattended-upgrades
   ```
   To let it reboot for kernel updates, set `Unattended-Upgrade::Automatic-Reboot "true";` and
   `Unattended-Upgrade::Automatic-Reboot-Time "04:00";` in
   `/etc/apt/apt.conf.d/50unattended-upgrades`. The containers come back on their own (`restart: always`).

### First deploy

1. **DNS:** `A` (and `AAAA` for IPv6) records for the domain and `www`, pointing at the VPS. Wait until they resolve.
2. **Harden the server** (section above), then install Docker:
   `curl -fsSL https://get.docker.com | sh && usermod -aG docker deploy` (sign in again for the group to apply).
3. **Clone** (as `deploy`): `sudo mkdir /opt/madeeasy && sudo chown deploy: /opt/madeeasy && git clone <repo-url> /opt/madeeasy && cd /opt/madeeasy`.
4. **`.env`:** `cp .env.example .env`, set everything in
   [the table](#production-environment-variables), then `chmod 600 .env`.
5. **App key:** `docker compose run --rm --no-deps app php artisan key:generate --show`, paste it into `APP_KEY`.
   Save the finished `.env` in the password manager.
6. **Backups:** set up the age key and destination ([Backups](#backups)).
7. **Start:** `docker compose up -d --build`, then `docker compose ps`. Everything should be Up, with app and db healthy.
8. **Schema and first admin:**
   ```bash
   docker compose exec app php artisan migrate --force
   docker compose exec app php artisan db:seed --force
   ```
   Then remove `SEED_ADMIN_PASSWORD` from `.env`.
9. **HTTPS:** `docker compose logs -f caddy` until "certificate obtained successfully", then open the site.
10. Continue with [GO-LIVE.md](GO-LIVE.md): data clean-up, Paystack, the
    deployment check, HSTS, and the first backup and restore drill.

**Subsequent deploys:**

```bash
cd /opt/madeeasy && git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker image prune -f          # old images otherwise fill the disk
```

After editing `.env`, run `docker compose up -d` to recreate the containers and rebuild the config cache.

### Rotating APP_KEY

`APP_KEY` encrypts sessions and cookies, signs links (password reset, email
verification, Updates previews), and encrypts result checker PINs. It is also the key of
`pin_hash`, the HMAC that stops the same PIN being uploaded twice
(`ResultCheckerPin::hash()`). Laravel can still **decrypt** with old keys
listed in `APP_PREVIOUS_KEYS`. Hashes can't be "decrypted", though: after a key change every new upload would hash
differently from the existing stock, and duplicates would slip through.
`php artisan pins:rekey` fixes that by re-encrypting every PIN and recomputing
every hash with the new key. It runs in one transaction, so the PINs move to the new key all together or not at all.

Rotate only for a reason (the key leaked, or a staff member with server access left). Procedure:

1. **Back up first:** `docker compose exec backup backup-now`. Note the current
   key; the backup can only be read with it.
2. **Stop PIN uploads for a few minutes.** Tell staff, or run `docker compose exec app php artisan down`
   for a short maintenance window.
3. **New key:** `docker compose run --rm --no-deps app php artisan key:generate --show`.
4. **Edit `.env`:** move the current value to `APP_PREVIOUS_KEYS=` (comma-separate
   if there are several) and put the new one in `APP_KEY=`.
5. **Apply:** `docker compose up -d`, which recreates app, worker and scheduler with the new config.
6. **Re-key the PINs:** `docker compose exec app php artisan pins:rekey --force`.
   It prints the number re-keyed. If it says a PIN can't be decrypted, the old key isn't in
   `APP_PREVIOUS_KEYS`. Nothing was changed; fix `.env` and run it again.
7. **Check:** `php artisan up` if you used maintenance mode. As an admin, open
   an order with a PIN and use "Show PIN". Then upload a CSV containing one PIN
   that's already in stock: it must be reported as a duplicate.
8. **Remove the old key.** If the key **leaked**, do it straight after step 7, and
   also sign everyone out: `docker compose exec app php artisan tinker --execute "DB::table('sessions')->truncate();"`.
   For a routine rotation, wait a week, then empty `APP_PREVIOUS_KEYS` and run `docker compose up -d`.
   Until then, existing sign-ins, "remember me" cookies and emailed links keep
   working. Afterwards, anyone still holding an old one just signs in again or
   asks for a new link.
9. **Update the password-manager copy of `.env`.** Backups made before the rotation
   need the old key to be useful (PINs), so keep the old key with them until they expire.

### Backups

Hostinger's daily server backup and these backups protect against different failure modes:

| | Hostinger snapshot | This backup job |
| --- | --- | --- |
| Where | Hostinger, same account | another provider or host (`BACKUP_TARGET`) |
| Restores | the whole server, all-or-nothing | the database and files only, or a drill into a scratch database |
| Survives | a broken upgrade or a lost disk | losing the Hostinger account or server, ransomware, or "someone deleted orders yesterday" (restore to scratch and copy rows) |
| Readable by | anyone with panel access | only the holder of the age private key |

The **backup** service runs `backup-now` daily at `BACKUP_SCHEDULE` (default
02:30 Lagos time). Each run creates three files:

- `madeeasy-<stamp>-db.dump.age`: `pg_dump` (custom format).
- `madeeasy-<stamp>-files.tar.gz.age`: `storage/app/private` (customer documents) plus the public disk (Updates images).
- `madeeasy-<stamp>.sha256`: checksums of the two encrypted files.

Both data files are encrypted with [age](https://age-encryption.org) to the
**public** key(s) in `BACKUP_AGE_RECIPIENTS`. They are uploaded to the destination
and kept on the server for `BACKUP_KEEP_LOCAL_DAYS`. The private key is never on the server.

**Setting up:**

1. **Key pair, on your own computer:** `docker run --rm alpine sh -c "apk add -q age && age-keygen"`.
   Save the whole output as `madeeasy-backup-key.txt` in the password manager, plus one offline copy.
   Put the `age1…` public key in `BACKUP_AGE_RECIPIENTS`. To let a second
   person restore independently, add their public key too, separated by a space.
2. **Destination**, one of:
   - **SCP to another host**: `BACKUP_TARGET=scp`, with `BACKUP_SCP_DESTINATION=backup@host:/srv/backups/madeeasy`
     and `BACKUP_SCP_PORT`. On the VPS:
     ```bash
     mkdir -p /opt/madeeasy/backup-ssh && cd /opt/madeeasy/backup-ssh
     ssh-keygen -t ed25519 -N '' -f id_ed25519      # add id_ed25519.pub to the backup user's authorized_keys
     ssh-keyscan -p 22 host > known_hosts            # pins the host key; scp refuses unknown hosts
     ```
     Prune old copies on the backup host (e.g. a cron job running `find /srv/backups/madeeasy -mtime +30 -delete`).
   - **S3-compatible storage** (AWS S3, Backblaze B2, Cloudflare R2, Wasabi, …): `BACKUP_TARGET=s3`,
     `BACKUP_S3_ENDPOINT` (empty for AWS), `BACKUP_S3_REGION`, `BACKUP_S3_BUCKET`, `BACKUP_S3_PREFIX`,
     `BACKUP_S3_ACCESS_KEY_ID` and `BACKUP_S3_SECRET_ACCESS_KEY`. Use a key that can only **write** to that
     bucket, if the provider allows it, so someone who takes over the server can't delete the backups.
     Set a lifecycle rule on the bucket to expire old copies (e.g. 30 days).
3. **Apply and test:** `docker compose up -d backup`, then `docker compose exec backup backup-now`.
   Confirm the three files arrived at the destination. Then do a restore drill (below).

**Checking it runs:** `docker compose exec backup cat /backups/last-success` shows the
time and name of the last good run. `docker compose logs --tail 50 backup` shows every run's
output and any "FAILED: …" line.

### Restoring from a backup

All commands are run in `/opt/madeeasy`. `<stamp>` looks like `20261002T013000Z`.

1. **Fetch** the set from the destination into the backups volume:
   `docker compose exec backup backup-fetch` lists what's there, and
   `docker compose exec backup backup-fetch <stamp>` downloads one set and checks its checksums.
2. **Bring the private key in**, only for the restore:
   `docker compose cp madeeasy-backup-key.txt backup:/backups/restore/key.txt`.
3. **Drill** (safe at any time; changes nothing live):
   `docker compose exec backup backup-restore <stamp> /backups/restore/key.txt`.
   It restores the database into `madeeasy_restore_check` and the files into
   `/backups/restore/files`, then prints row and file counts. Compare them with
   the live site. Clean up with `docker compose exec db dropdb -U madeeasy madeeasy_restore_check`.
4. **Disaster recovery** (replaces the live data):
   - On a new server, do [First deploy](#first-deploy) steps 1–7 with the **same
     `.env`** (above all, the same `APP_KEY`). Skip step 8: the backup has the schema and the admin.
   - Run:
     ```bash
     docker compose stop app worker scheduler
     docker compose run --rm backup-restore backup-restore <stamp> /backups/restore/key.txt --live
     docker compose up -d
     ```
     If you're on a new server, run `docker compose exec app php artisan migrate --force` too, in
     case the code is newer than the backup.
5. **Remove the key** whatever the outcome: `docker compose exec backup rm -rf /backups/restore`.

Both modes were tested end to end on the local stack: a backup, a drill whose
counts matched live in every table, and a `--live` restore that brought back a
deleted private document byte for byte, with PINs still decrypting.
**Do a drill on the real server after the first night's backup, and then every
few months** (GO-LIVE.md).

### Monitoring and logs

There's no paid monitoring service; these checks cover the basics.

- **Is everything up?** `docker compose ps`: every service should be Up, with app and db
  `(healthy)`. `docker inspect -f '{{.RestartCount}}' madeeasy-worker-1`
  shows how often Docker has had to restart the worker. A climbing number means it keeps crashing; check its logs.
- **Health check:** `curl -fsS https://your-domain.com/up` returns 200 "Application up"
  only if Laravel **and** the database answer. A free external uptime checker
  can poll that URL every few minutes and email you when it fails.
- **Is the queue worker alive and keeping up?**
  - `docker compose logs --tail 50 worker` shows a `RUNNING` / `DONE` line per job.
  - `docker compose exec app php artisan queue:monitor database:default --max=50` reports
    the number of waiting jobs. It should be near 0; a growing number means the worker is stuck or down.
  - `docker compose exec app php artisan queue:failed` lists failed jobs; retry them with `queue:retry all`.
  - `docker compose restart worker` if in doubt (jobs are not lost).
- **Scheduler:** `docker compose logs --tail 20 scheduler`. It logs every task it runs.
- **Tail logs:** `docker compose logs -f --tail 100 app worker` (Laravel logs to
  stderr). Caddy's access log is JSON: `docker compose logs caddy | grep '"status":5'` finds server errors.
- **Log rotation:** every container is capped at 5 × 10 MB of logs
  (`docker-compose.prod.yml`), and Laravel writes nothing to `storage/logs`, so
  logs can't fill the disk.
- **Disk:** `df -h /` and `docker system df`. Prune old images after deploys.


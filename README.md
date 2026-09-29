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

Named volumes: `db_data` (database), `app_storage` (Laravel `storage/`,
including private uploads in `storage/app/private`), `caddy_data` and
`caddy_config` (TLS certificates, which must persist so Let's Encrypt isn't re-hit).

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
- `TestUsersSeeder` seeds the staff/admin/customer test accounts above. It is
  skipped in production.

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
| `/result-checker`, `/updates` | Placeholders until their later prompts |
| `/about`, `/contact` | About (placeholder copy) and Contact |
| `/track` | **Track your request**: reference plus the email or phone used |
| `/track/{reference}` | Order page: status, answers, documents, messages, **Pay now**, receipt and the needs-action response form |
| `/track/{reference}/receipt` | Payment receipt (printable HTML; `?download=1` saves it as a file) |
| `/track/{reference}/payment/return` | Where Paystack sends the customer back; display only |
| `POST /webhooks/paystack` | Paystack webhook (signature-checked, CSRF-exempt) |
| `/my-requests` | Signed-in customer's own orders (login required) |

**Service requests.** The form is built from each service's `customer_fields`,
set in the admin under Services, then "Information required from the customer".

- Validation is in `App\Http\Requests\SubmitServiceRequest`.
- `App\Actions\Orders\CreateServiceRequest` creates a `pending_payment` order
  with the contact details in `guest_*`.
- Files from **file** fields go through `App\Actions\Documents\StoreOrderDocument`.
  They are stored on the private disk under `order-documents/{order_id}/`, and the
  MIME type is re-checked from the file contents. Allowed types are PDF, JPG and
  PNG, up to `UPLOAD_MAX_KILOBYTES` each.
- A queued `ServiceRequestReceivedMail` gives the customer their reference, a
  random code such as `ME-7K2QXD` (`Order::generateReference()`, Crockford
  base32, unique). Orders created before this change keep their old numeric
  reference (shown as `#12`). Submissions are rate-limited to 5 per minute per IP.
- Instant services (result checkers) and inactive services can't be requested.

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
  emailed (`PaymentReceiptMail`).
- Anything else is **not applied**. The payment keeps a `review_reason`, the
  event is logged at error level, and staff get a `PaymentNeedsReview`
  notification (plus email when `EMAIL_STAFF_ON_PAYMENT_REVIEW=true`). The order
  screen shows a red "Payment needs review" callout. Refunds or manual fixes are
  done by a person in the Paystack dashboard.
- The return page calls Paystack's verify API only to decide what to *show*
  ("confirming…", with a 5-second refresh). It never changes an order.

Set the webhook URL in the Paystack dashboard to `https://<domain>/webhooks/paystack`.
To test locally, expose the site with a tunnel (e.g. ngrok) and use test keys.

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
| Administration: Document access log | admin | Read-only; search by staff member or order # |

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

## Tests

The runtime image has no dev dependencies, so tests run in a throwaway
container with `vendor/` kept in a Docker volume (fast even on Windows):

```bash
docker run --rm -v "$PWD:/var/www/html" -v madeeasy_test_vendor:/var/www/html/vendor \
  --user root --entrypoint sh madeeasy-app:latest \
  -c "composer install --no-interaction --quiet && php artisan test"
```

(PowerShell: use `"${PWD}:/var/www/html"`.) Tests use in-memory SQLite from `phpunit.xml`.

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
    plus the `app_storage` volume (private uploads). Copy both off the server.

**Subsequent deploys:**

```bash
cd /opt/madeeasy && git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

In production the containers run `php artisan optimize` and `filament:optimize`
on start. After editing `.env`, run `docker compose up -d` to recreate the
containers and rebuild the config cache.

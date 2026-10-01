# Frontend guide: where everything lives

A map of the public site and customer pages: which file draws each part, where
its text and data come from, and how to see a change. Use it to make edits
yourself and to narrow down a problem before asking for help.

All paths are relative to the repository root. Templates are Blade files
(HTML with `{{ }}` and `@if` / `@foreach`) under `resources/views/`.

---

## 1. Before you edit: three rules that save hours

1. **Rebuild to see a change.** The code is baked into the Docker images; it is
   not live-mounted. After editing any file, run:
   ```bash
   docker compose up -d --build
   ```
   Then hard-refresh the browser (Ctrl+F5). If a change "doesn't show", this
   is almost always why.
2. **Styling is Tailwind CSS.** Classes like `text-slate-900` or `rounded-xl`
   in the templates *are* the styling. New classes only work after a rebuild
   (step 1), because the CSS is generated from the classes the templates use.
   Docs: <https://tailwindcss.com/docs>.
3. **Check where the text comes from before editing a template.** Much of it
   isn't in the templates at all; see [section 3](#3-where-text-and-data-come-from).
   Editing the wrong place does nothing.

**Finding any piece of text quickly:** search for a distinctive phrase across
`resources/views` (Cursor/VS Code: Ctrl+Shift+F, or
`rg "Making Your Educational Journey" resources/views`). If there is no match,
the text comes from the back office, `.env` or the database (section 3).

---

## 2. The page frame (shared by every public page)

```
┌──────────────────────────────────────────────────────────┐
│ HEADER: logo · main menu · Track/Log in · mobile "Menu"  │  partials/public/header.blade.php
├──────────────────────────────────────────────────────────┤
│                                                          │
│ PAGE CONTENT (the {{ $slot }})                           │  e.g. pages/home.blade.php
│                                                          │
├──────────────────────────────────────────────────────────┤
│ FOOTER: business name · contact · Explore links · ©      │  partials/public/footer.blade.php
└──────────────────────────────────────────────────────────┘
                                        [ WhatsApp button ]  components/public-layout.blade.php
```

| Part | File | Notes |
| --- | --- | --- |
| Whole-page wrapper: `<head>`, page title, SEO / Open Graph tags, skip link, body background | `resources/views/components/public-layout.blade.php` | Every public page starts with `<x-public-layout title="…" description="…">`. The default meta description is here. |
| **Header**: logo, desktop menu, dropdown, account buttons, mobile menu | `resources/views/partials/public/header.blade.php` | The "ME" logo badge and the "Made Easy / Educational Consultancy" wording are at the top of this file. |
| **Main menu items** (labels, order, links, Admission dropdown) | `app/Support/PublicNavigation.php` → `items()` | **Edit the menu here, not in the header.** The header, the mobile menu and the footer's "Explore" list all read this one list. |
| Account buttons (Back office / My Requests / My account / Track request / Log in) | `header.blade.php`, the `@auth … @else … @endauth` block | Mobile copies are near the bottom of the same file. |
| Mobile menu ("Menu" button) | `header.blade.php`, the `<details>` block | Works without JavaScript. Shows below the `xl` screen width (1280px). |
| **Footer** | `resources/views/partials/public/footer.blade.php` | The tagline "Making your educational journey easier." and the extra links (Track, Maths, FAQ, Testimonials) are here. |
| **Floating WhatsApp button** | `public-layout.blade.php` (bottom) | The number is `CONTACT_WHATSAPP` in `.env`; the link is built in `app/Support/WhatsApp.php`. Pages can pass a pre-filled message via `whatsapp-message="…"`. |
| Colours and fonts (the design tokens) | `tailwind.config.js` | See [Design system](#design-system) below. |
| Buttons on public pages | `resources/views/components/public/button.blade.php` | Variants: `primary` (orange), `dark` (ink), `secondary` (white, outlined). Used as `<x-public.button :href="…" variant="dark">`. |
| Hand-written CSS: heading font, focus outline, reduced motion, the carousel, blog post body (`.post-body`) | `resources/css/app.css` | Everything else is Tailwind classes in the templates. |

### Design system

A **working design direction** to show the client, not the final brand: Made
Easy's own logo and colours are still pending. To change it, edit
`tailwind.config.js`, then rebuild.

| Token | Value | Use it for |
| --- | --- | --- |
| `ink` | `#151313` | Text, borders, dark buttons. Use opacity for softer text: `text-ink/70`. |
| `accent` (orange scale, `accent` = `accent-500`) | `#ff5734` | **The one dominant colour: primary actions.** Orange backgrounds always take `text-ink` (white on orange fails contrast). For orange *text* on white, use `text-accent-700` or darker. |
| `secondary` (purple) | `#be94f5` | Sparingly. Used once on the homepage (the Maths tutorials panel). |
| `highlight` (yellow) | `#fccc42` | Sparingly. The hero illustration's backdrop and the "Not sure where to start?" box. |
| `surface` | `#f7f7f5` | The page background. |
| `brand-*` | same orange scale as `accent` | Older templates (order page, result checker, Updates, …) use `brand-*`. It's kept as an alias so the whole site matches. When Made Easy's real colour arrives, changing `orange_scale` changes both. |
| `shadow-pop` / `shadow-pop-sm` | hard 4px / 2px ink shadow | Clickable cards and primary buttons only. |
| `font-display` | Kodchasan, self-hosted in `resources/fonts/kodchasan/` | Headings. `h1`–`h3` get it automatically from `app.css`. **Never use it for prices**: its ₦ looks like ₳. |
| `font-sans` | Figtree, then the system font | Body text. Public pages don't load the Figtree file, so they show the system font (Segoe UI, Roboto, …). |
| `animate-rise-in` | 500ms fade and slide up | The homepage hero only. Always write it as `motion-safe:animate-rise-in`. |

**Illustrations** are free [unDraw](https://undraw.co) SVGs, recoloured to the
palette (purple → orange, dark → ink, and a darker skin tone), in
`resources/images/illustrations/`. They're shown with
`<x-public.illustration name="hero-requests" />`. To add one:

1. Download the SVG from unDraw.
2. Replace `#6c63ff` with `#ff5734`.
3. Save it in that folder.
4. Add its width and height to the list in `components/public/illustration.blade.php`.

Use illustrations only for the hero and genuinely empty lists. Cards get icons
(`components/public/category-icon.blade.php`) instead.

**Accessibility built in:** every focused link or button gets a 3px ink outline
(white in the dark footer), set in `app.css`. Users who ask their device for
reduced motion get no animation.

---

## 3. Where text and data come from

| What you see | Change it in | Not in a template? |
| --- | --- | --- |
| Business name, phone, email, address, opening hours, WhatsApp number | `.env` → `CONTACT_*`, then `docker compose up -d` | Yes: `config/madeeasy.php` → `contact` |
| About Us page text | Back office → Website → **Site settings** | Yes (the default text is in `app/Enums/SiteSettingKey.php`) |
| "Students assisted" figure on the homepage | Back office → Website → **Site settings** | Yes (hidden while empty) |
| FAQ questions and answers (/faq and the homepage) | Back office → Website → **FAQ** | Yes |
| Testimonials (homepage and /testimonials) | Back office → Website → **Testimonials** (approve) | Yes (only approved ones show) |
| Services, prices, descriptions, request form fields | Back office → Catalogue → **Services** / **Categories** | Yes |
| Updates (blog) posts and categories | Back office → Updates → **Posts** | Yes |
| Course & cut-off data | Back office → Course search → **Institutions** / **Courses** / **Cut-off marks** | Yes |
| Consultation times | Back office → Consultations → **Availability** | Yes |
| Hero headline, "How it works" steps, page intros, button labels | the page's Blade file (section 4) | No, they are written in the templates |
| Menu labels and order | `app/Support/PublicNavigation.php` | No (PHP) |
| Order status names ("Pending payment", "Needs action", …) | `app/Enums/OrderStatus.php` → `getLabel()` | No (PHP) |
| Emails to customers | `resources/views/mail/orders/*.blade.php` (section 6) | No |
| Form error messages (e.g. "Too many attempts…") | the controller / FormRequest (`app/Http/Requests`) or `app/Support/FormThrottle.php` | No (PHP) |

---

## 4. Public pages

Each row: the URL, the template that draws it, and the PHP that supplies its
data (useful when something is missing or wrong rather than mis-styled).

| Page | URL | Template | Data from |
| --- | --- | --- | --- |
| **Home** | `/` | `resources/views/pages/home.blade.php` | `app/Http/Controllers/HomeController.php` |
| Services: all categories | `/services` | `resources/views/services/index.blade.php` | `ServiceCatalogueController@index` |
| Services: one category (also the **Admission** and **Documents** menu items) | `/services/{category}` | `resources/views/services/category.blade.php` | `ServiceCatalogueController@category` |
| Service detail and **request form** | `/services/{category}/{service}` | `resources/views/services/show.blade.php` | `ServiceCatalogueController@show` |
| "Request received" | after submitting a request | `resources/views/services/received.blade.php` | `ServiceRequestController@received` |
| **Result Checker** store | `/result-checker` | `resources/views/result-checker/index.blade.php` | `ResultCheckerController` |
| **Book a consultation** | `/book-consultation` | `resources/views/consultations/book.blade.php` | `ConsultationBookingController` |
| **Course & cut-off search** (Admission dropdown) | `/admission/course-search` | `resources/views/admission/search.blade.php` | `AdmissionSearchController` |
| **Updates** list (and by category) | `/updates`, `/updates/category/{slug}` | `resources/views/updates/index.blade.php` | `UpdatesController@index` / `@category` |
| One update (post) | `/updates/{slug}` | `resources/views/updates/show.blade.php` | `UpdatesController@show` |
| **About Us** | `/about` | `resources/views/pages/about.blade.php` | `PageController@about` (text from Site settings) |
| **Contact** | `/contact` | `resources/views/pages/contact.blade.php` | none; reads `config('madeeasy.contact')` |
| **FAQ** | `/faq` | `resources/views/pages/faq.blade.php` + `partials/public/faq-list.blade.php` | `PageController@faq` |
| Maths tutorials | `/maths-tutoring` | `resources/views/pages/maths-tutoring.blade.php` | `PageController@maths_tutoring` |
| Testimonials | `/testimonials` | `resources/views/testimonials/index.blade.php` | `TestimonialController@index` |
| Share a testimonial | `/track/{reference}/testimonial` | `resources/views/testimonials/create.blade.php` | `TestimonialController@create` |
| **Track your request** (lookup form) | `/track` | `resources/views/orders/track.blade.php` | `OrderTrackingController@lookup_form` |
| Order page (status, payment, PINs, booking, documents, "needs action" reply) | `/track/{reference}` | `resources/views/orders/show.blade.php` | `OrderTrackingController@show` |
| "Confirming your payment" (after Paystack) | `/track/{reference}/payment/return` | `resources/views/orders/payment-return.blade.php` | `OrderPaymentController@payment_return` |
| Receipt PDF | `/track/{reference}/receipt` | `resources/views/receipts/pdf.blade.php` | `OrderPaymentController@receipt` |
| Sitemap / robots | `/sitemap.xml`, `/robots.txt` | `resources/views/seo/sitemap.blade.php` / generated | `SeoController` |

Controllers are in `app/Http/Controllers/`. All URLs are listed in
`routes/web.php` (public), `routes/auth.php` (login etc.).

### The homepage, section by section

`resources/views/pages/home.blade.php`, top to bottom. Each block starts with a `{{-- comment --}}` naming it.

| Section | What to edit | Shows when |
| --- | --- | --- |
| **Hero**: topic tags ("Exam registration", …), headline "Making Your Educational Journey Easier", intro paragraph, 4 buttons, illustration on a yellow panel | the first `<section>` in the file. Button targets are the `:href` values. The headline, paragraph and button labels are the client's wording from the requirements PDF, and `PublicPagesTest` checks them. | always |
| **Stat badges** in the hero | "Students assisted" number and label: Site settings. "Services available online" and "service categories" are counted from the live categories (`$categories`). | each stat only when it has a real number |
| **How it works** (3 numbered steps) | the `@foreach ([...])` list of `title` / `body` | always |
| **Our services**: swipeable category cards (icon, name, description, service count) | cards come from the database (categories with an active service). The row is CSS scroll snap (`.carousel` in `app.css`), with no JavaScript. Icons: `components/public/category-icon.blade.php`. | always (a "coming soon" line if there are none) |
| **Latest Updates** (3 newest posts) | posts in the back office; card layout in `components/public/post-card.blade.php` | once a post is published |
| **Maths tutorials** promo box | text in this file; link goes to `/maths-tutoring` | always |
| **What our customers say** (3 testimonials) | approved testimonials; card in `components/public/testimonial-card.blade.php` | once one is approved |
| **FAQ** preview | FAQs in the back office; how many: `HomeController::HOME_FAQ_COUNT` | once an FAQ is active |

### Reusable pieces used across pages

`resources/views/components/public/`:

| Component | Used for |
| --- | --- |
| `button.blade.php` | the big rounded buttons |
| `service-card.blade.php` | a service in category lists (name, price or "Price on request", link) |
| `request-field.blade.php` | one field of a service request form (text, select, date, file upload …), driven by the fields set on the service in the back office |
| `status-badge.blade.php` | the coloured order-status pill (colours per status are here) |
| `post-card.blade.php` | an Updates post preview |
| `testimonial-card.blade.php` | one testimonial |
| `stat.blade.php` | a stat badge (bold number + label) inside a `<dl>`: homepage hero and dashboard summary. Only real figures. |
| `empty-state.blade.php` | "nothing here yet" panel: optional illustration, title, short text and one next step (`<x-slot:action>`) |
| `illustration.blade.php` | one unDraw SVG from `resources/images/illustrations/` |
| `category-icon.blade.php` | the icon on a category card, picked by slug (new categories get a generic icon until one is added here) |

`resources/views/partials/public/faq-list.blade.php` draws the FAQ list (homepage and /faq).

---

## 5. Signed-in and account pages

These use a different frame from the public pages.

| Page | URL | Template |
| --- | --- | --- |
| Log in, Register, Forgot / Reset password, Verify email, Confirm password | `/login`, `/register`, `/forgot-password`, … | `resources/views/livewire/pages/auth/*.blade.php`, in the frame `resources/views/layouts/guest.blade.php` (logo: `components/application-logo.blade.php`) |
| **My Requests** dashboard | `/my-requests` | `resources/views/account/dashboard.blade.php` + its parts in `account/partials/` (`requests`, `order-card`, `payments`, `pins`, `consultations`, `documents`, `messages`). The four summary stat badges at the top use `$tab_counts`, the same numbers as the tab badges. The Requests, Consultations and Documents tabs show an illustration when they're empty. |
| Profile (name, email, password, delete account) | `/profile` | `resources/views/profile.blade.php` + `livewire/profile/*.blade.php`, in the frame `layouts/app.blade.php` with the menu `livewire/layout/navigation.blade.php` |
| "Forbidden" error page | any 403 | `resources/views/errors/403.blade.php` |

The login/register pages and the profile page still use the original Laravel
Breeze styling (grey, `indigo` focus colours). Their small building blocks are in
`resources/views/components/` (`primary-button`, `text-input`, `input-label`,
`input-error`, `modal`, `dropdown`, …).

---

## 6. Emails and the PDF receipt

| Email | Template | Sent by (`app/Mail/`) |
| --- | --- | --- |
| "We've received your request" | `resources/views/mail/orders/request-received.blade.php` | `ServiceRequestReceivedMail` |
| Payment receipt (with the PDF attached) | `mail/orders/payment-receipt.blade.php` | `PaymentReceiptMail` |
| Status changed | `mail/orders/status-changed.blade.php` | `OrderStatusChangedMail` |
| Result checker PINs | `mail/orders/result-checker-pins.blade.php` | `ResultCheckerPinsMail` |
| PINs delayed | `mail/orders/result-checker-delayed.blade.php` | `ResultCheckerDelayedMail` |
| Consultation booking details | `mail/orders/consultation-booking.blade.php` | `ConsultationBookingMail` |
| Customer replied (to staff) | `mail/orders/response-received.blade.php` | `NeedsActionResponseReceivedMail` |
| The receipt PDF itself | `resources/views/receipts/pdf.blade.php` | `app/Actions/Receipts/RenderReceiptPdf.php` |

Staff bell notifications (low PIN stock, payment needs review, …) are in
`app/Notifications/`.

Locally, emails aren't sent: they're written to the log (`MAIL_MAILER=log`).
To read one, run `docker compose logs app | more`.

---

## 7. The back office (`/admin`)

The admin panel is **Filament**, not Blade templates you edit directly.

| What | Where |
| --- | --- |
| Panel name ("Made Easy Admin"), colours, URL | `app/Providers/Filament/AdminPanelProvider.php` |
| Each screen (Orders, Services, Posts, FAQ, Site settings, Testimonials, Users & roles, …). The sidebar group and label are the `$navigationGroup` / `$navigationLabel` lines at the top of each resource | `app/Filament/Resources/<Name>/`: the form is in `*Resource.php` or `Schemas/*Form.php`, the list in `Tables/*Table.php` or the resource's `table()` |
| Dashboard widgets (PIN stock etc.) | `app/Filament/Widgets/` |
| The "Show PIN" pop-up | `resources/views/filament/orders/pin-reveal.blade.php` |

---

## 8. Debugging checklist (before asking for help)

1. **Did you rebuild?** `docker compose up -d --build`, then hard refresh (section 1).
2. **Is everything running?** `docker compose ps`: app and db should be `healthy`.
   `https://localhost/up` should say "Application up".
3. **Read the error.** Locally (`APP_DEBUG=true`) the browser shows the error and the file and line.
   Otherwise: `docker compose logs --tail 100 app`.
4. **What does the status code mean?**
   - 404: wrong URL, or the item is inactive or unpublished (draft posts, inactive services and categories 404 on purpose).
   - 403: the signed-in user's role isn't allowed.
   - 419: the form sat open too long (expired session). Reload and resubmit.
   - 429: a rate limit was hit; wait a minute (limits: README → Rate limits).
   - 500: a code error. Read the log (step 3).
5. **Text didn't change?** Check whether it comes from Site settings, `.env` or the database (section 3).
   After editing `.env`, run `docker compose up -d` (and `--build` if you changed code too).
6. **Something missing on the homepage?** Several sections hide when empty: the trust figure, Latest Updates,
   testimonials and FAQ (section 4).
7. **Menu link wrong after renaming a category?** The Admission and Documents menu items find their
   category by slug: `ADMISSION_CATEGORY_SLUG` / `DOCUMENTS_CATEGORY_SLUG` in `app/Support/PublicNavigation.php`.
8. **Styling looks broken or a new class does nothing?** Rebuild (Tailwind only includes classes it finds at build time).
   Don't build class names from pieces (`'text-'.$colour`): Tailwind can't see those. Write the full class.
   An illustration that doesn't show usually means it isn't in the build: it must sit in
   `resources/images/` (picked up by `resources/js/app.js`), and you need to rebuild.
   A test that compares exact HTML can fail after a restyle even though nothing changed for users:
   `UpdatesPublicTest` checks the Updates headings' class lists. Read the failure before changing code.
9. **Clear cached views and config** if behaviour seems stale:
   `docker compose exec app php artisan optimize:clear`.
10. **Run the tests** after a change (README → Tests). The test name usually tells you what broke.
    Tests that touch pages are in `tests/Feature/PublicSite/` and `tests/Feature/Website/`.

When you do ask for help, include: the URL, what you expected, what happened,
the status code or error message, and the last lines of `docker compose logs app`.

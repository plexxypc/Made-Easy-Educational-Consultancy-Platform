# Go-live checklist

The step-by-step for launch day on the Hostinger KVM 2 VPS. Work through it
in order and tick each box. Anything that can't be ticked yet goes in the
**Still open** list at the end, with who it's waiting on. The README explains
each step in more detail; the links point to the relevant section.

All commands are run on the VPS in `/opt/madeeasy`, with
`COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml` in `.env`. Plain
`docker compose …` then always means the production setup.

Launch date: ____________  Done by: ____________

---

## 1. Server (before launch day)

- [ ] DNS `A` / `AAAA` records for the domain and `www` point at the VPS, and resolve.
- [ ] A sudo user with an SSH key exists, and you can sign in as it.
- [ ] SSH accepts keys only, and root can't sign in (`PasswordAuthentication no`, `PermitRootLogin no`).
      Checked from a **new** session. ([Server hardening](README.md#server-hardening-on-the-vps-by-hand))
- [ ] Firewall allows only 22, 80 and 443: `ufw status verbose` (and the Hostinger panel firewall, if enabled).
- [ ] Automatic security updates are on: `systemctl status unattended-upgrades`.
- [ ] Docker installed; repo cloned to `/opt/madeeasy` ([First deploy](README.md#first-deploy)).
- [ ] Hostinger's own daily server backup is switched on in the panel. It is in
      addition to step 6, not instead of it.

## 2. Environment (`.env`)

Every value in [Production environment variables](README.md#production-environment-variables) is real:

- [ ] `COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml`
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`
- [ ] `APP_KEY` generated on this server (`key:generate --show`); `APP_PREVIOUS_KEYS` empty
- [ ] `APP_URL=https://<domain>`; `SITE_ADDRESS=<domain>, www.<domain>`; `ACME_EMAIL` is a monitored address
- [ ] `HSTS_MAX_AGE=0` for now (switched on in step 5)
- [ ] `DB_PASSWORD` is long and random
- [ ] Mail: `MAIL_MAILER=smtp` with the transactional provider's `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`,
      `MAIL_USERNAME`, `MAIL_PASSWORD`; `MAIL_FROM_ADDRESS` on our domain, with the provider's SPF/DKIM DNS records added
- [ ] `CONTACT_BUSINESS_NAME`, `CONTACT_PHONE`, `CONTACT_EMAIL`, `CONTACT_ADDRESS`, `CONTACT_HOURS` are the client's real details
- [ ] `CONTACT_WHATSAPP` is the real WhatsApp number (not `+234 800 000 0000`). Tap the floating button on a phone: it opens the right chat.
- [ ] `BACKUP_AGE_RECIPIENTS` and the backup destination are set (step 6)
- [ ] `SEED_ADMIN_PASSWORD` was removed after the first `db:seed`
- [ ] `chmod 600 .env`, and a copy of the finished `.env` is in the password manager.
      **Without this `APP_KEY`, the backups can't restore PINs.**
- [ ] After any `.env` edit: `docker compose up -d`

## 3. Start and verify the stack

- [ ] `docker compose up -d --build`, then `docker compose ps`: app, worker, scheduler, caddy, db and backup are all Up; app and db `(healthy)`
- [ ] `docker compose exec app php artisan migrate --force` reports nothing pending
- [ ] `docker compose logs caddy` shows "certificate obtained successfully"; `https://<domain>` loads with a valid padlock
- [ ] `curl -fsS https://<domain>/up` returns 200 "Application up"
- [ ] The queue worker is processing: `docker compose logs --tail 20 worker`, and
      `docker compose exec app php artisan queue:failed` is empty
- [ ] Mail really sends: use "Forgot password" for the admin account; the email arrives (check spam too)

## 4. Data clean-up

Do these in the back office (`/admin`) as an admin unless a command is given.

**Test data**

- [ ] Back up first: `docker compose exec backup backup-now`
- [ ] Dry run: `docker compose exec app php artisan launch:remove-test-data`. Read the counts and the list of staff/admin accounts it will keep.
- [ ] Remove every smoke-test order, payment, document, testimonial and customer account:
      `docker compose exec app php artisan launch:remove-test-data --force`.
      Add `--include-pin-stock` if the PINs in stock are test PINs, not real ones bought from the exam bodies.
      **Never run this after real customers have ordered.**
- [ ] Users: delete any test **staff/admin** accounts in Users. Only real people remain, each with their own login.
- [ ] Consultation slots: delete test slots; real availability is entered.

**Course search (sample data from Prompt 11)**

- [ ] **Either** the sample dataset is cleared and the client's real dataset is imported:
      Course search → Cut-off marks → select all → Delete, then delete the sample
      institutions and courses, then import the client's CSV. Spot-check three
      courses on `/admission/course-search` against the client's source.
- [ ] **Or** the course search is clearly marked as pending (decided by: ________), and the sample data was never loaded on this server
      (`AdmissionSampleDataSeeder` is skipped in production, so it is only there if someone ran it on purpose).

**Services and prices**

- [ ] Every service that should be on sale at launch has its real price and is active. In Services, the
      **inactive placeholders** filter (services still on the ₦1,000 seed price; 26 at handover) is empty,
      or the remaining ones are deliberately left off.
- [ ] The two known **possible duplicate** pairs are resolved; the Services list flags them until then:
  - [ ] "WAEC/NECO Certificate Processing" and "WAEC/NECO/GCE/NABTEB Certificate Processing"
  - [ ] "O'Level Result Upload" and "O'Level Upload Assistance"
- [ ] **Admission Consultation** (seeded inactive, ₦20,000): switched **on**, with slots available, **or** deliberately left
      **off** for now. This is the client's decision on launch day. Decision: ________
- [ ] Result checker PIN stock is uploaded for every result checker on sale, above its low-stock threshold.

**Website content**

- [ ] Website → Site settings → About Us text is the client's real text, not the default.
- [ ] Website → Site settings → "Students assisted" is a real, defensible figure (or left empty, which hides it).
- [ ] FAQ: the official PDF section 13 wording is installed (see **Still open**). Once the seven questions are in
      `database/data/faqs_section_13.php` and deployed:
      `docker compose exec app php artisan db:seed --class=FaqSeeder --force`. Review any drafted
      question it says it kept, in Website → FAQ.
- [ ] Testimonials: only real, approved ones are public (`/testimonials`).
- [ ] Updates: at least one real post is published; nothing test-looking is visible on `/updates`.

## 5. Payments and security

**Paystack live**

- [ ] The Paystack business account is verified for live payments.
- [ ] `.env`: `PAYSTACK_PUBLIC_KEY=pk_live_…`, `PAYSTACK_SECRET_KEY=sk_live_…`, then `docker compose up -d`.
      The secret key is also the webhook secret: Paystack signs webhooks with it.
- [ ] Paystack dashboard → Settings → API Keys & Webhooks → **Live** webhook URL: `https://<domain>/webhooks/paystack`
- [ ] One real low-value payment end to end (e.g. a result checker): Paystack checkout, then the order shows
      **Paid** (set by the webhook), the receipt email arrives and the PIN is delivered. Refund it in Paystack afterwards if wanted.

**Security checks against the live site**

- [ ] Run the deployment check from your own computer (README, "Checking a running deployment"):
      `PRODUCTION_CHECK_URL=https://<domain>` … `php artisan test --group=production`. All pass:
      `.env`, `.git`, `vendor/`, `storage/` and source files return 404, the headers are present, and `/up` answers.
- [ ] `curl -sI https://<domain>/.env` shows 404 (a quick manual double-check).
- [ ] HTTPS has worked for a day without certificate problems on both `<domain>` and `www.<domain>`.
      Then set `HSTS_MAX_AGE=31536000` and run `docker compose up -d`, then re-run the deployment check with
      `PRODUCTION_CHECK_HSTS=31536000`. From then on, browsers refuse plain HTTP for a year.

## 6. Backups

- [ ] age key pair made **off** the server; the private key is in the password manager and one offline copy;
      the public key is in `BACKUP_AGE_RECIPIENTS` ([Backups](README.md#backups)).
- [ ] `BACKUP_TARGET` is `scp` or `s3` (**not** `local`), with the destination settings filled in.
- [ ] Remote retention is set up (an S3 lifecycle rule, or a prune cron on the backup host).
- [ ] `docker compose exec backup backup-now` succeeds, and the three files are visible at the destination.
- [ ] **Restore drill:** `backup-fetch <stamp>`, copy the key in, run `backup-restore <stamp> /backups/restore/key.txt`.
      The printed counts match the live site. Then drop `madeeasy_restore_check` and run
      `rm -rf /backups/restore` ([Restoring from a backup](README.md#restoring-from-a-backup)).
- [ ] The next morning: `docker compose exec backup cat /backups/last-success` shows last night's run.

## 7. Monitoring

- [ ] Optional, free: an external uptime checker polls `https://<domain>/up` every 5 minutes and emails the maintainer.
- [ ] Whoever maintains the site knows the basic checks ([Monitoring and logs](README.md#monitoring-and-logs)):
      `docker compose ps`, the worker's restart count, `queue:monitor`, `queue:failed`, `docker compose logs -f app worker`.
- [ ] `df -h /` shows plenty of free disk.

## 8. After launch

- [ ] Day 1: check `queue:failed`, `docker compose logs --tail 100 app`, and that last night's backup ran.
- [ ] Week 1: review new orders in the back office and confirm each customer was emailed.
- [ ] Every month: run `apt list --upgradable` (should be near-empty) and `docker image prune -f`, and check `df -h /`.
- [ ] Every 3 months: a restore drill (step 6). Also check that the age key and `.env` copies in the password manager still match the server.
- [ ] If `APP_KEY` ever has to change: follow [Rotating APP_KEY](README.md#rotating-app_key) exactly (`pins:rekey`).

---

## Still open at handover

Items waiting on someone other than the developer. Clear each one before launch, or record the decision.

| Item | Waiting on | Status |
| --- | --- | --- |
| Official FAQ wording (PDF section 13, seven questions). Drafted stand-ins are live until it is pasted into `database/data/faqs_section_13.php` | client | pending |
| Real course & cut-off dataset (the seeded one is illustrative) | client | pending |
| Real prices for the 26 inactive placeholder services | client | pending |
| Duplicate service pairs: merge or keep both | client | pending |
| Admission Consultation on or off at launch | client | decide on launch day |
| Real `CONTACT_*` details, WhatsApp number, About Us text, "students assisted" figure | client | pending |
| Transactional mail (SMTP) provider and account | client / maintainer | pending |
| Backup destination (SCP host or S3-compatible bucket) | client / maintainer | pending |
| Paystack live activation | client | pending |

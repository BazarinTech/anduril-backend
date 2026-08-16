# anduril-backend

PHP API and admin panel for **Sanderson Farm**, a Kenyan M-Pesa investment
platform. The browser-facing app is a separate Next.js repository,
`anduril-market`.

Deployed to `sanderson.xgramm.com` on cPanel/LiteSpeed, PHP 8.1, MySQL 8.

---

## Layout

| Path | What it is |
|---|---|
| `backend/mains/` | The JSON API the frontend calls |
| `backend/mains/callbacks/` | Endpoints the payment provider posts to |
| `admin/` | Server-rendered admin dashboard |
| `bootstrap/` | Config loading and shared services — start here |
| `lib/` | Shared helpers (mail, SMS, email templates) |
| `config/` | Environment configuration; `env.php` is gitignored |
| `db/` | Schema, seed data, migrations |
| `bin/` | Cron jobs |
| `docs/` | Design notes and the remediation tracker |
| `phpmailer/` | Vendored PHPMailer |

## Getting it running locally

```bash
# 1. Configuration
cp config/env.example.php config/env.php     # then fill it in

# 2. Database
mysql -u root -p < db/schema.sql
mysql -u root -p < db/seed.sql

# 3. Serve  -- the router is not optional, see below
php -S 127.0.0.1:8090 -t . bin/router.php
```

Then open <http://127.0.0.1:8090/admin/login>.

**Always pass `bin/router.php`.** Production runs on Apache/LiteSpeed where
`admin/.htaccess` rewrites extensionless URLs onto `.php` files, and the panel
navigates entirely by extensionless paths — `header('Location: dashboard')`
after login, every sidebar link, every form action. PHP's built-in server does
not read `.htaccess`, so without the router every one of those 404s and the
panel is unusable. The router reproduces that single rewrite, and also denies
the directories that carry a deny-all `.htaccess` in production (`config/`,
`bootstrap/`, `lib/`, `bin/`, `db/`) so local is not laxer than the server.

The seed creates two admins — `admin@sanderson.local` / `admin123` and
`bazarin@gmail.com` / `445566gh` — plus five test users (`test1234`), five
products and a three-level referral tree.

**Testing without touching live services.** Real environment variables override
`config/env.php`, so point the outbound integrations at a closed port before
exercising anything that pays out or sends mail:

```bash
SMTP_HOST=127.0.0.1 SMTP_PORT=1 \
PALPLUSS_STK_URL=http://127.0.0.1:1/ PALPLUSS_B2C_URL=http://127.0.0.1:1/ \
SMS_URL=http://127.0.0.1:1/ \
PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8090 -t . bin/router.php
```

`PHP_CLI_SERVER_WORKERS` matters if you are testing anything concurrent — the
built-in server is single-threaded otherwise, which makes race conditions
impossible to reproduce.

The Palpluss key and the SMS gateway are **live**. Without those overrides, a
withdrawal test attempts a real M-Pesa payout and a verification test sends a
real SMS.

## How it fits together

Every request enters through one of two bootstraps, which load configuration,
connect to the database and publish the shared services:

- `bootstrap/api.php` — the JSON API. Uses `backend/vendor`.
- `bootstrap/admin.php` — the admin panel. Uses `admin/vendor`, starts the
  database-backed session, and provides the authorization helpers.

Both pull in `bootstrap/ledger.php` (row locking, money handling, idempotent
claims) and `bootstrap/referrals.php` (the referral tree and commission walk).

The `initiate.php` files scattered through the tree are thin shims that require
the right bootstrap; they exist so the ~35 endpoints that include them by
relative path keep working.

### Authentication

Tokens are minted by the **frontend**, in a Next.js route handler, and only
verified here. `JWT_SECRET` must be byte-identical in `config/env.php` and the
frontend's `.env`. See [docs/AUTH.md](docs/AUTH.md) for why, and what is still
imperfect about it.

The admin panel authenticates separately, against `users.passwrd`, and then
requires a matching row in `admins`.

### Money

Anything that changes a balance takes a row lock inside a transaction — see
`bootstrap/ledger.php`. Anything driven by an external event (payment
callbacks, admin approvals) claims its work with a conditional status change
via `claim_transaction()`, so a duplicate delivery cannot apply twice.

Balances are stored in `VARCHAR` columns. That is wrong and it is what
production does; `money()` and `money_str()` exist to make the coercion
explicit. Converting to `DECIMAL` is a schema migration nobody has done yet.

### The product

Users deposit via M-Pesa, buy a **product** (an investment plan with a daily
flat return and a duration), and claim that return once a day.

Claimability is **derived, not stored**. An investment can be claimed when the
clock is inside the daily window and that order has claims left for the current
period — see `bootstrap/claims.php`. The window (opening time, optional closing
time, claims per day) is set on the admin **Platform Control** page. Nothing has
to run overnight; there is no flag to go stale.

This replaced `orders.rolls`, a flag that had to be reset every night. Miss a
night and nobody could claim; run the reset twice and everybody claimed twice.

Referrers earn commission three levels up on their downlines' deposits, plus
milestone bonuses, coupons and salary "incentives".

## Operations

**No cron is required.** `bin/daily-reset.php` used to re-arm claims nightly
and was load-bearing; it is now a no-op that reports the current window and
exits 0. If the crontab still calls it, that is harmless — but the entry can go:

```bash
crontab -e   # delete the daily-reset.php line
```

**Deploying.** `config/env.php` is gitignored, so it must exist on the server
before the code does. `CALLBACK_TOKEN` fails closed — an unset one rejects
every payment callback.

**Product images** go to S3-compatible object storage (a Railway Bucket) when
one is configured, and to `admin/uploads/` on disk otherwise — see
`lib/storage.php`. Disk is fine for development and wrong on a container host,
where the filesystem is discarded on every deploy.

Set `S3_ENDPOINT`, `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY` and `S3_BUCKET`
from the bucket's Credentials tab; all four are required together, and a
partly-filled set is treated as unconfigured rather than half-working. Then
copy the images that already exist across:

```bash
php bin/migrate-images-to-bucket.php           # report only
php bin/migrate-images-to-bucket.php --apply   # upload
```

It copies rather than moves, and skips anything already in the bucket, so it is
safe to re-run and safe to reverse. The admin Products page shows which driver
is live and warns when uploads would be lost.

`STORAGE_URL_MODE` decides how the app is given a URL: `public` (bucket serves
reads directly), `presign` (signed, expiring URLs), or `proxy` (through
`backend/mains/image.php`, for a private bucket with stable URLs).

**Migrations** in `db/migrations/` are idempotent and safe to re-run:

```bash
php db/migrations/001_phase2_hashed_credentials.php   # required before Phase 2 code
php db/migrations/002_phase4_column_fixes.php
php db/migrations/003_deposit_provider_reference.php  # required for the Palpluss top-up API
php db/migrations/004_claim_window.php                # required for the claim window
```

## Things that will bite you

**`vendor/` is committed on purpose, and `composer install` is unsafe.**
`bazarin/bazarin-php-library` is tagged v1.1.0 but the copy here has been
edited in place — `selectOR()` was added, and Phase 5 added identifier
validation. Reinstalling from the registry silently reverts both and fatals
`auth.php` and `transfer.php`. Fixing this properly means publishing a
versioned fork, or adopting the library as first-party code under `lib/`.
Until then, treat `vendor/` as source.

**Two `sendSMS()` functions exist** with the same name and different
signatures: `backend/mains/sms-code.php` sends a verification code,
`lib/transaction-sms.php` sends an arbitrary message. No file includes both
today. One that did would fatal.

**PHP and MySQL must agree on the timezone.** They did not, and every expiry
check in the codebase silently did nothing for it. `APP_TIMEZONE` pins both;
don't remove it.

## Status

This codebase went through a five-phase remediation. What was fixed, what was
deliberately left, and what still needs a decision is tracked in
[docs/remediation-tracker.html](docs/remediation-tracker.html).

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

# 3. Serve
php -S 127.0.0.1:8000 -t .
```

The seed creates an admin (`admin@sanderson.local` / `admin123`), five test
users (`test1234`), five products and a three-level referral tree.

**Testing without touching live services.** Real environment variables override
`config/env.php`, so point the outbound integrations at a closed port before
exercising anything that pays out or sends mail:

```bash
SMTP_HOST=127.0.0.1 SMTP_PORT=1 \
PALPLUSS_STK_URL=http://127.0.0.1:1/ PALPLUSS_B2C_URL=http://127.0.0.1:1/ \
SMS_URL=http://127.0.0.1:1/ \
PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8000 -t .
```

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
flat return and a duration), and claim that return once a day. Claims are
gated by `orders.rolls`, which `bin/daily-reset.php` re-arms nightly.
Referrers earn commission three levels up on their downlines' deposits, plus
milestone bonuses, coupons and salary "incentives".

## Operations

**Nightly cron** — without this, nobody can claim:

```cron
0 0 * * * /usr/local/bin/php /home/USER/site/bin/daily-reset.php >> /home/USER/logs/daily-reset.log 2>&1
```

**Deploying.** `config/env.php` is gitignored, so it must exist on the server
before the code does. `CALLBACK_TOKEN` fails closed — an unset one rejects
every payment callback.

**Migrations** in `db/migrations/` are idempotent and safe to re-run:

```bash
php db/migrations/001_phase2_hashed_credentials.php   # required before Phase 2 code
php db/migrations/002_phase4_column_fixes.php
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

# Authentication model

Decision record for Phase 0.1. Answers "who mints the JWT?" — the question that
blocked the rest of the remediation plan.

## How it actually works

Two repositories are involved:

- `anduril-backend` (this one) — PHP API, **verifies** tokens
- `anduril-market` — Next.js 16 frontend, **mints** tokens

The flow:

1. The browser posts credentials to the frontend's own route handler,
   `app/(auth)/api/auth/route.ts`. Credentials never go to PHP directly.
2. That route forwards them to `backend/mains/auth.php`, which validates them
   against the `users` table and returns a bare `userID` — **no token**.
3. On success the route signs the token itself:
   `jwt.sign({ userID }, process.env.JWT_SECRET)`.
4. The token is set as a cookie and thereafter sent in the JSON body of every
   API call as the field `userID` (see `lib/backend/actions.ts`).
5. Every protected PHP endpoint calls `JWT::decode($data['userID'], ...)` with
   the shared `JWT_SECRET`.

So `JWT::encode` legitimately appears nowhere in this repo. This is a
Backend-For-Frontend pattern, and the signing secret lives in the Next.js
server runtime, not in the browser bundle.

## Decision

**Keep minting in the frontend BFF route.** It is a defensible split: the PHP
API stays a pure verifier, and the secret stays server-side. Moving issuance
into `auth.php` would be a larger change with no security gain.

`JWT_SECRET` is therefore a **shared secret between two repositories** and must
be byte-identical in:

- `config/env.php` → `JWT_SECRET` (this repo)
- `.env` → `JWT_SECRET` (anduril-market)

## What is broken about it

Four defects, all deferred to Phase 2 because fixing them requires a
coordinated change across both repos:

### 1. The cookie name *is* the signing secret — critical

`app/(auth)/api/auth/route.ts` sets the session cookie with:

```ts
res.cookies.set({ name: "susyr7q3ycugfWDFF", ... })
```

`susyr7q3ycugfWDFF` is the exact value of `JWT_SECRET` as it was hardcoded in
the old `backend/includes/initiate.php`. The HS256 signing secret is published
to every visitor as a cookie name, readable in DevTools with no exploitation
required. Anyone can forge a token for any `userID` and act as that user
against every endpoint.

Fix: rotate the secret, and name the cookie something that is not a secret
(`session`, `auth_token`). Both repos, one change. This is why the secret in
`config/env.php` is marked ROTATE ME.

### 2. Tokens never expire

`jwt.sign()` is called with no `expiresIn`, so there is no `exp` claim. The
7-day cookie `maxAge` only governs the browser; the token itself is valid
forever, and logout merely deletes the cookie client-side. The
`ExpiredException` handler present in all eleven PHP endpoints is unreachable
code.

Fix: `expiresIn`, plus a refresh path.

### 3. The cookie is readable by JavaScript

`httpOnly: false`, so any XSS yields a permanent, non-expiring session token.

### 4. Middleware does not verify signatures

`middleware.ts` uses `jwtDecode()`, which base64-decodes without checking the
signature. Client-side route guards can be bypassed with any well-formed
token. This is not itself a hole — the PHP side does verify — but it is not
the protection it looks like.

## A related trap: the two vendor trees

`admin/vendor/` and `backend/vendor/` both contain
`bazarin/bazarin-php-library` tagged **v1.1.0**, but they are not the same
code:

| | `admin/vendor` | `backend/vendor` |
|---|---|---|
| `selectOR()` | absent | present |
| `auth()` looks up | `username` | `phone` |
| `auth()` compares | `password_verify()` | `==` on plaintext |
| `firebase/php-jwt` | absent | present |

The backend copy has been hand-edited in place, and the version number was not
changed to reflect it. Consequences:

- `composer install` in `backend/` would silently revert the patches and fatal
  `auth.php` and `transfer.php` (both call `selectOR()`).
- The two contexts must therefore keep loading their **own** vendor tree.
  `bootstrap/api.php` and `bootstrap/admin.php` enforce this.
- `vendor/` cannot be gitignored until the library is forked and versioned.

Interestingly the `admin` copy is the *better* one — it already uses
`password_verify`. It is dead code there, because `admin/login.php` does its
own plaintext comparison instead of calling `auth()`. Phase 2.1 should adopt
the admin copy's approach across both.

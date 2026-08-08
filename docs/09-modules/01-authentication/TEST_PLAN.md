# Authentication — Test Plan

> **Status:** Approved matrix — tests **not** implemented in this specification task
> **Last updated:** 2026-08-08

Tooling: **Pest** (backend), **Vitest** (frontend). Security-relevant failures block merge.

Shared helpers: reuse Tenant Foundation fixtures (`Tenant::factory`, `tenantUser`, `platformUser`, `withTenant`) from `tests/Pest.php`.

---

## A. Backend Pest matrix

### A1. Login — success

| ID | Case | Expect |
|---|---|---|
| L01 | Valid active tenant user | 200; session cookie; `data.tenant` present; `LOGIN_SUCCESS` audited |
| L02 | Valid active platform user | 200; `is_platform_user` true; `tenant` null |
| L03 | `remember: true` | Persistent remember behavior set; subsequent request authenticated |
| L04 | Session regenerated on login | Session id changes vs pre-login |
| L05 | Email case normalization | `User@Example.com` matches stored lowercase email |
| L06 | Successful login clears rate limiter | After prior failures under limit, success resets counter |

### A2. Login — failures (generic / gates)

| ID | Case | Expect |
|---|---|---|
| L10 | Wrong password | 401 `AUTH_INVALID_CREDENTIALS` |
| L11 | Nonexistent email | 401 `AUTH_INVALID_CREDENTIALS` — **body indistinguishable** from L10 |
| L12 | Invalid email format | 422 |
| L13 | Missing password | 422 |
| L14 | Disabled user (correct password) | 403 `AUTH_ACCOUNT_DISABLED`; no session |
| L15 | Pending tenant | 403 `TENANT_PENDING`; no session |
| L16 | Suspended tenant | 403 `TENANT_SUSPENDED`; no session |
| L17 | Archived tenant | 403 `TENANT_ARCHIVED`; no session |
| L18 | Missing/invalid tenant relationship | 403 `TENANT_CONTEXT_INVALID`; no session |
| L19 | Rate limit exceeded | 429 `AUTH_TOO_MANY_ATTEMPTS` after 5 failures/minute |
| L20 | No session created when gates fail | Assert unauthenticated after failed login |

### A3. Current user & logout

| ID | Case | Expect |
|---|---|---|
| M01 | `/auth/me` tenant user | 200; safe fields only |
| M02 | `/auth/me` platform user | 200; tenant null |
| M03 | `/auth/me` unauthenticated | 401 `AUTH_UNAUTHENTICATED` |
| M04 | `/auth/me` disabled after login | 403 `AUTH_ACCOUNT_DISABLED` |
| O01 | Logout | 200; subsequent `/auth/me` → 401 |
| O02 | Protected request after tenant suspension | 403 `TENANT_SUSPENDED` on next request |

### A4. Password reset

| ID | Case | Expect |
|---|---|---|
| R01 | Forgot — existing email | 200; notification/mail sent (faked); token stored |
| R02 | Forgot — unknown email | 200; **outward response identical** to R01; no mail |
| R03 | Forgot rate limit | 429 |
| R04 | Reset — valid token | 200; password updated; token invalidated |
| R05 | Reset — invalid token | `AUTH_PASSWORD_RESET_INVALID` |
| R06 | Reset — expired token | `AUTH_PASSWORD_RESET_EXPIRED` |
| R07 | Reset — password policy fail | 422 field errors |
| R08 | Reset — confirmation mismatch | 422 |
| R09 | Other sessions revoked after reset | Where session driver allows; prior session rejected |

### A5. Audit & secrets

| ID | Case | Expect |
|---|---|---|
| U01 | Login success audit | Event present; IP/UA/correlation; no password |
| U02 | Login failed audit | No existence oracle; no secrets |
| U03 | Reset completed audit | No token/password in payload |
| U04 | Logs/audit never contain | plaintext password, hash dump, reset token, session id, CSRF |

### A6. CSRF / SPA session (feature)

| ID | Case | Expect |
|---|---|---|
| C01 | Mutating without CSRF | 419 (or framework equivalent) when applicable in test setup |
| C02 | With CSRF + credentials | Login succeeds |

---

## B. Frontend Vitest matrix

| ID | Case | Expect |
|---|---|---|
| F01 | Login form validation | Empty/invalid email/password blocked client-side |
| F02 | Login success flow | Mutation calls API; current-user query populated; navigate home |
| F03 | Invalid credentials | Shows generic message; no existence hint |
| F04 | Rate-limited login | Shows 429 messaging |
| F05 | Disabled account | Shows disabled messaging from `code` |
| F06 | Suspended tenant | Shows blocked messaging; no retry loop |
| F07 | CSRF bootstrap | Client fetches `/sanctum/csrf-cookie` before login POST |
| F08 | Logout | Clears current-user query; lands on login |
| F09 | `/auth/me` loading | Guard waits; no premature redirect |
| F10 | Guard — unauthenticated | Protected → `/login` |
| F11 | Guard — authenticated on `/login` | → home |
| F12 | Redirect-back | Safe intended path restored after login |
| F13 | Forgot password success | Generic success copy always |
| F14 | Reset validation | Policy + confirmation errors |
| F15 | Reset success | Redirect to login |
| F16 | API error normalization | `ApiError` carries `status`/`code`/`errors` |

### Router test setup (document for implementers)

- Mount with `vue-router` memory history, Pinia (if any), Vue Query client with `retry: false` for auth queries.
- Mock `fetch` / auth `api/*` module.
- Prefer testing guards via small `createAuthGuard` unit tests plus one integration test per redirect rule.
- No Playwright required to close Sprint 005; E2E login flow remains recommended follow-up per [TESTING_STRATEGY.md](../../07-testing/TESTING_STRATEGY.md).

---

## C. Performance checks (manual / light assertions)

- Login query count documented in PR (user by email, tenant by id, no N+1).
- Frontend: `staleTime` on `/auth/me` avoids refetch storm on every navigation; still invalidates on 401/403/logout.
- No uncontrolled retry on blocked tenant codes.

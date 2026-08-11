# Authentication — Business Rules

> **Status:** Approved — implementation-ready (Sprint 005)
> **Last updated:** 2026-08-08

## 1. Authentication technology (final)

| Decision | Value |
|---|---|
| Mechanism | **Laravel Sanctum SPA cookie authentication** (session-based) |
| Cookie | HttpOnly session cookie; SameSite and Secure appropriate to the deployed domain setup |
| CSRF | Required — client must call `GET /sanctum/csrf-cookie` before state-changing auth requests |
| Web app tokens | **No Bearer token login** for the SPA |
| Personal Access Tokens | **Future** (mobile / non-browser API clients) |
| Login identifier | **Email + password only** (no username, no phone in MVP) |

## 2. User account status (final)

Users have a minimal authentication status independent of employee lifecycle:

| Status | Meaning |
|---|---|
| `active` | May authenticate if tenant/platform checks pass |
| `disabled` | Cannot log in; existing sessions denied on the next protected request |

- Do **not** fold employee lifecycle into `users.status`.
- Default for new users: `active` (unless provisioning explicitly creates `disabled`).
- Disabled responses after successful credential verification use `AUTH_ACCOUNT_DISABLED` (generic human message; machine code for frontend). Wrong password / unknown email always use `AUTH_INVALID_CREDENTIALS` — no existence disclosure before password verification succeeds.
- Schema impact: add `users.status` — see [DATA_MODEL.md](DATA_MODEL.md). **Do not implement the migration in the specification phase.**

## 3. Who may authenticate

### Tenant user (`users.tenant_id` NOT NULL)

Must satisfy **all** of:

1. Credentials valid (email + password).
2. `users.status === active`.
3. `tenant_id` references an existing tenant (else `TENANT_CONTEXT_INVALID`).
4. Tenant `status === active` (else `TENANT_PENDING` / `TENANT_SUSPENDED` / `TENANT_ARCHIVED`).

### Platform user (`users.tenant_id` NULL)

Must satisfy:

1. Credentials valid.
2. `users.status === active`.
3. No tenant is required. Platform users **must never** automatically receive tenant business-data access (Tenant Foundation: no TenantContext → scoped queries fail closed).

## 4. Login pipeline (exact order)

No session is created until every gate passes.

1. **Validate** request (Form Request): email required + valid format; password required; `remember` optional boolean.
2. **Normalize** email (trim + lowercase) before lookup and before rate-limiter keying.
3. **Rate limit** login: **5 attempts per minute**, key = `normalized_email + '|' + IP`. On exceed → `429 AUTH_TOO_MANY_ATTEMPTS` (no enumeration).
4. **Find user** by normalized email.
5. **Verify password** (`Hash::check`). If user missing **or** password wrong → return **identical** `401 AUTH_INVALID_CREDENTIALS`; audit `LOGIN_FAILED` without revealing existence (see §10).
6. **Check user status.** If `disabled` → do **not** create session → `403 AUTH_ACCOUNT_DISABLED`; audit `ACCOUNT_DISABLED_ACCESS_ATTEMPT`.
7. **If tenant user:** load tenant **fresh** by `user.tenant_id`. Missing → `403 TENANT_CONTEXT_INVALID`. Non-active status → `403` with `TENANT_PENDING` / `TENANT_SUSPENDED` / `TENANT_ARCHIVED`; audit `TENANT_BLOCKED_ACCESS_ATTEMPT`. **Do not establish an operational session.**
8. **If pending tenant:** credentials may be verified internally, but **must not** create an operational authenticated session — same as other lifecycle blocks (`TENANT_PENDING`).
9. **Regenerate session** (session fixation defense).
10. **Authenticate** via Sanctum/session (`Auth::login($user, $remember)`).
11. **Clear** the login rate-limiter state for this email+IP on success.
12. **Return** `AuthUserResource` (same shape as `/auth/me`).
13. **Audit** `LOGIN_SUCCESS` (correlation ID, IP, user agent, tenant_id if any, context type).

### Where each step lives (request lifecycle)

| Step | Location |
|---|---|
| Credential verification | Login Action (before `Auth::login`) |
| User account status | Login Action (before session) |
| Tenant relationship + status | Login Action (before session); subsequent protected requests also via `ResolveTenantContext` + `EnsureTenantIsActive` |
| Session creation | Only after all gates pass |
| `TenantContext` initialization | `ResolveTenantContext` middleware on later API requests — **not** a substitute for login-time tenant checks |

## 5. Remember me

- Supported via Laravel session remember (`remember` boolean on login).
- Extends persistent login per framework-safe configuration (`SESSION_*` / remember token).
- **Must not** bypass account status or tenant status — every protected request still runs tenant/account gates.
- Default inactivity lifetime for normal sessions: **120 minutes** (`SESSION_LIFETIME=120`).

## 6. Session policy

| Rule | Behavior |
|---|---|
| Inactivity lifetime | 120 minutes (configurable via env; document default) |
| Remember-me | Framework persistent cookie; still subject to status gates |
| Login | Session ID regenerated on successful login |
| Logout | Invalidate current session; regenerate CSRF token where appropriate; clear request TenantContext in middleware `finally` as usual |
| Tenant becomes suspended/archived | Existing session denied on **next** protected request (`403` + tenant code) — already enforced by `EnsureTenantIsActive` |
| Account becomes disabled | Existing session denied on **next** protected request — see §7 |
| Password reset success | Invalidate **other** active sessions for that user where practical |

## 7. Ongoing request enforcement (after login)

On every protected tenant route (existing Tenant Foundation + auth addition):

1. Sanctum authenticates the session → user.
2. If `user.status === disabled` → reject `403 AUTH_ACCOUNT_DISABLED`. Add thin middleware (e.g. `EnsureUserIsActive`) on protected routes — must not rely on login alone.
3. `ResolveTenantContext` → set/clear TenantContext.
4. `EnsureTenantIsActive` → lifecycle codes.

Platform users on platform routes: no TenantContext; tenant-owned routes remain blocked with `TENANT_CONTEXT_MISSING`.

## 8. Password policy (MVP final)

| Rule | Value |
|---|---|
| Minimum length | **8** characters |
| Complexity | Must contain **letters** and **numbers**; symbols allowed |
| Maximum | Do not impose an arbitrary low max; respect Laravel-safe string limits |
| Confirmation | Required on **password reset** (and on user-create when Users module implements it) |
| Compromised-password check | **Future** unless an approved package/service is already in the stack (none today) |

**Laravel approach (conceptual):** `Password::defaults()` (or explicit `Password::min(8)->letters()->numbers()`) so Form Requests share one policy. Hash with Laravel's default hasher. Never log plaintext or hashes.

## 9. Password reset (included in Sprint 005)

### Forgot password — `POST /api/v1/auth/forgot-password`

1. Validate email; normalize.
2. Rate limit (recommended: **5 / minute** per normalized email + IP).
3. If a matching user exists, create a **secure random, expiring, one-time** reset token (Laravel `password_reset_tokens`) and dispatch mail with a **frontend** reset URL (`{FRONTEND_URL}/reset-password?token=...&email=...`).
4. **Always** return the same success envelope whether or not the email exists.
5. Do **not** reveal tenant membership.
6. Audit `PASSWORD_RESET_REQUESTED` without creating an existence side channel; never store the raw token.

### Reset password — `POST /api/v1/auth/reset-password`

1. Validate token, email, password, password_confirmation against policy.
2. Invalid/missing token → `AUTH_PASSWORD_RESET_INVALID`.
3. Expired token → `AUTH_PASSWORD_RESET_EXPIRED`.
4. On success: update hashed password; **invalidate token**; revoke other sessions where practical; audit `PASSWORD_RESET_COMPLETED`; client redirects to login.
5. Never reveal tenant membership in errors.

**Token lifetime:** Laravel default **60 minutes** (`auth.passwords.users.expire`) unless env override is documented.

**Email dependency:** configured mailer (`MAIL_*`). Local/dev may use `log`/`array`. Production transport TBD at deployment.

## 10. Audit events

| Event | When | Notes |
|---|---|---|
| `LOGIN_SUCCESS` | Session established | `user_id`, `tenant_id` (nullable), IP, UA, correlation ID, context type |
| `LOGIN_FAILED` | Bad credentials | Avoid enumeration: do not assert user existence for unknown email / wrong password |
| `LOGOUT` | Successful logout | Current user |
| `PASSWORD_RESET_REQUESTED` | Forgot-password after validation | No raw token |
| `PASSWORD_RESET_COMPLETED` | Password changed via token | No password values |
| `ACCOUNT_DISABLED_ACCESS_ATTEMPT` | Login or protected request blocked for disabled account | |
| `TENANT_BLOCKED_ACCESS_ATTEMPT` | Login or protected request blocked by tenant lifecycle | Include status code |

**Never store:** plaintext passwords, password hashes, reset tokens, session IDs, CSRF tokens, remember tokens.

## 11. Rate limiting summary

| Endpoint | Limit | Key |
|---|---|---|
| `POST /api/v1/auth/login` | 5 / minute | normalized email + IP |
| `POST /api/v1/auth/forgot-password` | 5 / minute | normalized email + IP |
| `POST /api/v1/auth/reset-password` | 5 / minute | IP (and/or email) |

Successful login clears the **login** limiter for that email+IP.

## 12. Out of scope reminders

- Email verification: **out** of Sprint 005.
- MFA: **future** only.
- RBAC / permissions on `/auth/me`: **out of Sprint 005** — additive extension specified for Sprint 006 ([02-users-and-authorization](../02-users-and-authorization/)).
- `logout-all`: **future**.

## Resolved former TBDs

| Former TBD | Resolution |
|---|---|
| Sanctum mode | SPA cookie session (scaffolded) |
| Login identifier | Email |
| Password policy | §8 |
| Lockout / rate limit | 5/min rate limit — not a permanent lockout table |
| Password reset | Included — §9 |
| Session lifetime | 120 minutes inactivity |
| Remember-me | Supported — §5 |
| Suspended tenant login | Rejected with `TENANT_SUSPENDED` before session |

## Remaining genuine TBDs (not blocking Sprint 005)

- Production mail provider and from-address branding.
- Exact SameSite/Secure cookie flags per final production domain topology.
- Whether disabled-user login should ever collapse into `AUTH_INVALID_CREDENTIALS` for stronger anti-enumeration (current: after successful password verify, return `AUTH_ACCOUNT_DISABLED`). Change Request only.
- Full Audit module UI/retention — auth requires append recording only.

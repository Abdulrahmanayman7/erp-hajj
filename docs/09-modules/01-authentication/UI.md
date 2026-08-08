# Authentication — UI

> **Status:** Approved UI specification — **no pages implemented yet**
> **Last updated:** 2026-08-08

Follow [DESIGN_GUIDELINES.md](../../05-ui-ux/DESIGN_GUIDELINES.md): Arabic RTL first, light theme, deep green primary, gold accent restrained, desktop-first responsive, no dark mode.

## Module layout (planned — do not create files in this task)

```text
src/modules/auth/
├── api/                 # login, logout, me, forgot, reset, csrf helpers
├── components/          # AuthFormField, PasswordInput (show/hide), etc.
├── pages/
│   ├── LoginPage.vue
│   ├── ForgotPasswordPage.vue
│   └── ResetPasswordPage.vue
├── queries/
│   └── useCurrentUserQuery.ts
├── mutations/
│   ├── useLoginMutation.ts
│   ├── useLogoutMutation.ts
│   ├── useForgotPasswordMutation.ts
│   └── useResetPasswordMutation.ts
├── stores/              # empty or omit — see state decision below
├── types/
├── validation/
└── routes.ts
```

Shared: `src/app/guards/` (auth guest/authenticated), `src/app/layouts/` (guest layout + authenticated shell), `src/shared/api/http.ts` (credentials + CSRF + error normalization).

---

## Auth state responsibilities (final)

| Concern | Owner |
|---|---|
| Current user (`/auth/me`) | **TanStack Query** server state (`useCurrentUserQuery`) |
| Login / logout / forgot / reset | **TanStack Query mutations** |
| Form UI state (show password, field errors) | **Local / component state** |
| Global Pinia auth store | **Avoid in MVP** unless a thin coordination flag is proven necessary for guards |

**Do not** duplicate the current-user object in both Pinia and TanStack Query.

**Recommended guard pattern:** router `beforeEach` awaits/resolves `useCurrentUserQuery` (or a shared `ensureSession()` helper that fetches `/auth/me` once with staleTime). While loading, show a neutral full-page loader — do not redirect yet. On `401`, treat as guest. On tenant/account block codes, clear query cache and route to a blocked/login state (no retry storm).

---

## Routes

### Guest (unauthenticated layout — no app sidebar)

| Path | Page |
|---|---|
| `/login` | Login |
| `/forgot-password` | Forgot password |
| `/reset-password` | Reset password (reads `token` + `email` from query) |

### Authenticated (app shell)

| Path | Page |
|---|---|
| `/` or `/app` (choose one home path and stick to it) | Temporary home: welcome message **or** existing system status — **no fake KPIs** |
| Future business routes | Added by later modules |

**Post-login default:** authenticated home showing e.g. «مرحباً بك في نظام إدارة حملات الحج» (and/or system status). No dashboard widgets in Sprint 005.

---

## Login page (`/login`)

### Composition (one clear composition — brand-first guest page)

- Product / campaign identity area (logo placeholder + product name **ERP Hajj** / نظام إدارة حملات الحج) as a strong visual signal.
- Email input (type email, autocomplete username).
- Password input with show/hide toggle.
- Remember-me checkbox.
- Primary login button with loading state.
- Link to `/forgot-password`.
- **No** registration / signup link.
- Validation messages (client) + server `422` field errors.
- Generic invalid-credentials message for `AUTH_INVALID_CREDENTIALS`.
- Clear messages for `AUTH_ACCOUNT_DISABLED`, `TENANT_PENDING`, `TENANT_SUSPENDED`, `TENANT_ARCHIVED`, `AUTH_TOO_MANY_ATTEMPTS` (these codes appear only after credential path allows — safe for the user who typed a password).
- Keyboard accessible; visible focus; labels; mobile-responsive.

### Visual

- Light theme; deep green primary CTAs; restrained gold accent if used.
- Guest layout: no right sidebar.
- Avoid dark mode, purple gradients, and decorative clutter (see project frontend design rules).

---

## Forgot password (`/forgot-password`)

- Email field + submit.
- Loading state.
- **Always** show the same generic success message after a successful HTTP response (existing or unknown email).
- Link back to `/login`.
- Rate-limit friendly copy when `429` (`AUTH_TOO_MANY_ATTEMPTS`).
- No user-existence disclosure.

---

## Reset password (`/reset-password`)

- Read `token` and `email` from the query string safely (do not persist to `localStorage`).
- Fields: new password, confirm password; show/hide; policy guidance (min 8, letters + numbers).
- Submit → on success redirect to `/login` with a success flash/toast.
- Handle `AUTH_PASSWORD_RESET_INVALID` / `AUTH_PASSWORD_RESET_EXPIRED` with clear Arabic messages and link to request a new reset.

---

## Authenticated app shell (Sprint 005)

Reuse approved shell direction:

- RTL, sidebar on the **right**, header, light theme, deep green direction.
- Header: user display name + logout action.
- Sidebar: minimal nav (home / system status only) — no fake module links that 404 unless stubbed.
- **Do not** implement real dashboard widgets or business KPIs.

---

## Route guards

| Rule | Behavior |
|---|---|
| Guest visiting protected route | Redirect to `/login`; optionally preserve safe `redirect` query (same-origin path only — open-redirect safe) |
| Authenticated visiting `/login` (etc.) | Redirect to app home |
| `/auth/me` loading | Wait — no redirect loop |
| `401` from `/auth/me` | Guest |
| `403` `TENANT_*` / `AUTH_ACCOUNT_DISABLED` | Clear current-user query; show access-blocked message; send to login or a dedicated `/access-blocked` guest page; **do not** endlessly retry |
| Frontend guards | UX only — **backend remains authoritative** |

Recommended optional page: `/access-blocked` for lifecycle/disabled messages after a session was rejected — still guest layout, link to login.

---

## CSRF flow (SPA)

1. App boot or first mutating call: `GET {API}/sanctum/csrf-cookie` with `credentials: 'include'`.
2. Then `POST /api/v1/auth/login` (and other mutating auth routes) with credentials + `X-XSRF-TOKEN` from cookie (Laravel convention; shared HTTP client should set this automatically).
3. Never store auth secrets in `localStorage`.
4. Normalize failures into existing `ApiError` (`status`, `code`, `errors`, `message`).

### HTTP handling

| Status | Frontend behavior |
|---|---|
| `401` | Clear current-user query; redirect to login (if not already on guest page) |
| `419` | Re-fetch CSRF cookie once; retry original request once; then fail visibly |
| `403` + tenant/account codes | Blocked-access UX (above) |
| `429` | Show rate-limit message; disable submit briefly |
| `422` | Map `errors` to fields |

---

## Frontend security

Never store: passwords, session cookies (manual), CSRF tokens (manual beyond cookie), reset tokens beyond page lifecycle, sensitive dumps in `localStorage`.

Do not use localStorage auth tokens. Do not fabricate permissions. Do not trust frontend-only account state for security.

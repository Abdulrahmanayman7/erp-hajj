# ADR-0016 — Typed Tenant Settings and Configuration Resolution

> **Status:** Accepted — **Implemented** (Sprint 020)
> **Date:** 2026-08-12
> **Implemented:** 2026-08-13
> **Deciders:** ERP Hajj engineering (documentation-first)

## Context

MVP module 21 requires **system-level settings** for each tenant. Sprint 019 identified that `tenant_settings.view|update` permissions, the `tenant_settings` table, and Tenant columns (`name`, `locale`, `timezone`, contacts) already exist, but **no tenant-facing Settings API/UI** ships yet.

Consumers already treat **`tenants.timezone`** as the operational source of truth for Dashboard, Notifications scanners, Contracts, Meetings, Tasks, Custodies, and Audit display. Operational thresholds (`contracts.expiring_soon_days`, `notifications.*`) live in **application config/env**, not per tenant.

## Decision

### 1. Hybrid storage (no duplication)

| Concern | Storage | Mutable via tenant Settings API? |
|---|---|---|
| Display name | `tenants.name` | Yes |
| Timezone (IANA) | `tenants.timezone` | Yes |
| Locale | `tenants.locale` | **No** in MVP (read-only; fixed `ar`) |
| Operational contacts | `tenants.contact_*` | Yes (nullable) |
| `tenant_code`, `status`, `notes`, lifecycle timestamps | `tenants.*` | **Never** via tenant Settings |
| Future typed overrides | `tenant_settings` (`key` + JSON `value`) | Only catalog-registered keys |

**Do not** duplicate Tenant columns into `tenant_settings` rows.

### 2. Strict typed catalog (code-owned)

- Settings exposed by the API are defined only in a central catalog (e.g. `Core/Tenancy/Settings/TenantSettingsCatalog`).
- Clients cannot invent keys. Unknown keys → `422` with stable code.
- MVP catalog keys map to **Tenant columns** only (see module docs). The KV table remains available for **future** approved keys without schema churn; MVP may ship with **zero** KV product keys.

### 3. Precedence

```text
Effective identity/regional values = tenants row
  (columns always present with DB defaults)

Future catalog KV overrides (when approved) =
  tenant_settings.value for registered key
  → else application config/env default
```

Missing `tenant_settings` rows are normal; APIs return **effective** values (never a blank singleton empty-state).

### 4. Operational thresholds stay global in MVP

These remain **config/env**, not tenant-editable:

- `config('contracts.expiring_soon_days')`
- `config('notifications.task_due_soon_days')`
- `config('notifications.meeting_starting_soon_minutes')`
- `config('notifications.custody_expected_return_soon_days')`
- `config('contracts.default_currency')`
- Document max upload size (`config/documents.php` / PHP limits)

Making them per-tenant would silently change scanners, Dashboard KPIs, Notifications, and tests without an approved Change Request.

### 5. API surface

```text
GET   /api/v1/tenant-settings              # tenant_settings.view
PATCH /api/v1/tenant-settings              # tenant_settings.update
POST  /api/v1/tenant-settings/test-email   # tenant_settings.update
```

- Singleton for the **current authenticated tenant** only (no `{tenant}` / no `tenant_id` input).
- Typed JSON body/resource (grouped `general` + `regional` + `technical`), **not** an unrestricted `key→value` bag.
- PATCH is partial: only supplied mutable fields change.
- `technical` includes optional SMTP delivery fields + sender identity; see Decision §6 amendment.

### 6. Secrets & env

Tenant Settings is **not** a general secret manager and **not** an env editor. Never store `APP_KEY`, DB passwords, API tokens, or private keys unrelated to mail.

**Amended exception (2026-09-16):** tenant SMTP credentials may be stored on `tenants` columns (`mail_password` encrypted at rest via Laravel `encrypted` cast) so owners can configure delivery without editing server `.env`. Requirements:

- Password never returned by API (only `mail_password_configured`).
- Password never audited as plaintext or ciphertext (`mail_auth_updated` boolean flag only).
- Per-send isolated mailer via `MailManager::build()` — **never** `Config::set` of global SMTP for tenant credentials.
- Server `.env` mail remains fallback when tenant SMTP is incomplete; `log`/`array` are not deliverable.

### 7. Audit

Successful PATCH → one semantic audit event **`TENANT_SETTINGS_UPDATED`** (already planned in Audit inventory) with safe before/after of **changed fields only**. GET is not audited. Fail-closed with domain transaction per ADR-0015.

### 8. Caching

**No** dedicated settings cache in MVP. Read Tenant (and optional KV) per request / Action. After update, frontend invalidates `/auth/me` and settings queries so timezone/name refresh immediately. Avoid stale override caches.

### 9. Notifications

Settings changes do **not** emit in-app Notifications in MVP. Audit is sufficient.

### 10. Platform boundary

`/api/v1/platform/tenants*` remains **out of this slice**. Platform registry edits (when implemented) use `platform_tenants.*` and planned `TENANT_UPDATED` / lifecycle events — distinct from tenant self-service Settings.

## Consequences

- Implementation can complete MVP module 21 with **no new migration** if only Tenant columns are updated.
- Timezone SoT stays `tenants.timezone`; Settings is the admin UX to edit it.
- Locale stays Arabic-first; no fake language picker.
- Currency stays per-contract + global default SAR — no tenant currency setting.
- Logo/branding deferred (no public asset URLs).
- GM template keeps **view** only; Owner retains **update** (high-risk) — matches current `PermissionCatalog`.

## Rejected alternatives

| Alternative | Why rejected |
|---|---|
| Unrestricted KV settings editor | Validation/security risk; not typed |
| Duplicate timezone into `tenant_settings` | Two sources of truth |
| Tenant-editable thresholds in MVP | Cross-module behavior change without CR |
| Feature flags / plugin manager / theme builder | Out of MVP scope |
| Settings as secret/env UI | Deployment concern, not tenant SoR |

## Implementation note (Sprint 020)

Shipped as `Modules/Settings` + `frontend/src/modules/settings/`:

- `GET|PATCH /api/v1/tenant-settings` over **`tenants` columns** only (no new migration; no KV product keys).
- `TenantSettingsResolver` for effective typed DTO; timezone SoT remains `tenants.timezone`.
- Audit `TENANT_SETTINGS_UPDATED` on real changes; GM view-only / Owner update per catalog.
- Thresholds/currency/logo remain out of scope as decided above.

## References

- [15-system-settings/](../09-modules/15-system-settings/)
- [00-tenancy/](../09-modules/00-tenancy/)
- [ADR-0003](ADR-0003-MULTI-TENANCY.md) · [ADR-0015](ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md)

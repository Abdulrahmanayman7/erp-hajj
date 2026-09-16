# System Settings — Business Rules

> **Status:** Implemented (Sprint 020 + mail sender extension)
> **Last updated:** 2026-09-16
> ADR: [ADR-0016](../../10-decisions/ADR-0016-TYPED-TENANT-SETTINGS-AND-RESOLUTION.md)

## 1. Singleton per tenant

- One logical Settings document per tenant (the authenticated tenant).
- No list/create/delete Settings resources.
- No second settings “profile”.

## 2. Final settings catalog

| Key (API field) | Arabic label | Type | Default | Nullable | Validation | Owner | Affects business logic? | Restart? | Audit |
|---|---|---|---|---|---|---|---|---|---|
| `name` | اسم المنشأة | string | seeded tenant name | no | required, string, max 255, **globally unique** among tenants (existing `tenants.name` UNIQUE) | Tenant admin | Display (`/me`, shell, Dashboard meta) | no | yes |
| `timezone` | المنطقة الزمنية | string (IANA) | `Asia/Riyadh` | no | required; must be a valid IANA id (`timezone_identifiers_list()` / equivalent allow-check) | Tenant admin | **Yes** — all calendar “today”, scanners, overdue, Audit display | no | yes |
| `locale` | اللغة | string | `ar` | no | **Read-only** via this API in MVP | System | UI is Arabic-first; value stored for future | no | n/a (immutable here) |
| `contact_name` | اسم جهة الاتصال | string | null | yes | nullable, string, max 255, no HTML | Tenant admin | Operational display only | no | yes |
| `contact_email` | بريد جهة الاتصال | string | null | yes | nullable, email, max 255 | Tenant admin | Operational display only | no | yes |
| `contact_phone` | هاتف جهة الاتصال | string | null | yes | nullable, string, max 50 | Tenant admin | Operational display only | no | yes |
| `mail_from_address` | بريد المرسل | string | null | yes | nullable, email, max 255; blank/null → `config('mail.from.address')` at send time | Tenant admin | Invite / password-reset From address | no | yes |
| `mail_from_name` | اسم المرسل | string | null | yes | nullable, string, max 255, no HTML; blank/null → `config('mail.from.name')` | Tenant admin | Invite / password-reset From name | no | yes |
| `mail_mailer` | طريقة الإرسال | string | null | yes | `smtp` only when enabling tenant transport | Tenant admin | Delivery | no | yes |
| `mail_host` / `mail_port` / `mail_encryption` / `mail_username` | SMTP | mixed | null | yes | required together when `mail_mailer=smtp`; encryption `tls\|ssl\|none` | Tenant admin | Delivery | no | yes |
| `mail_password` | كلمة مرور SMTP | encrypted text | null | yes | never returned; blank omit preserves; `mail_password_clear` clears | Tenant admin | Delivery | no | flag `mail_auth_updated` only |

### Mail architecture (non-negotiable)

| Concern | Where |
|---|---|
| Tenant SMTP (complete) | **Primary** — Settings → technical columns on `tenants` |
| Server `.env` / `config/mail.php` | **Fallback** when tenant SMTP incomplete |
| `MAIL_MAILER=log\|array` | **Not deliverable** — invitations fall back to manual password |
| Sender identity | tenant From → else `config('mail.from.*')` |
| Isolation | Per-send `MailManager::build()` / `tenant_mail` channel — never global `Config::set` of tenant credentials |

### Explicitly **not** in catalog (unchanged)

| Candidate | Decision | Reason |
|---|---|---|
| `tenant_code` | Immutable; never via Settings | Operational identity; not authorization |
| `status` / lifecycle | Platform only | `platform_tenants.*` |
| `notes` | Platform only | Operator notes |
| Logo / branding | **Deferred** | No public file URLs; Documents stack not for branding |
| Currency | **Not tenant setting** | Per-contract `currency` + `config('contracts.default_currency')` = SAR |
| Date format / week start | **Deferred** | Not required by current modules |
| Contract expiring-soon days | **Global config** | `config('contracts.expiring_soon_days')` |
| Meeting starting-soon minutes | **Global config** | `config('notifications.meeting_starting_soon_minutes')` |
| Task due-soon days | **Global config** | `config('notifications.task_due_soon_days')` |
| Custody due-soon days | **Global config** | `config('notifications.custody_expected_return_soon_days')` |
| Document max upload | **Global / PHP** | Documents module + server limits |
| Inventory low-stock | **Per-item minimum** + scanner; no tenant toggle | Inventory module |
| APP_KEY / DB passwords / API tokens | **Forbidden** | Deployment configuration |

## 3. Timezone source of truth

**Canonical:** `tenants.timezone` (IANA).

Already consumed by (must remain consistent after Settings updates):

- Dashboard (`DashboardClock`, today/starting-soon windows)
- Notifications scanners (`notifications:scan-*`)
- Contracts list/expiry / `is_expiring_soon`
- Meetings list filters / “today”
- Tasks overdue / due windows (with global day counts)
- Custodies overdue / due-soon scanners
- Audit UI display convention
- `/auth/me` → `tenant.timezone`

Fallback when blank (should not happen if NOT NULL): `config('app.timezone')` historically used in code paths as `Asia/Riyadh` / UTC app default — Settings must always persist a non-empty IANA value.

**Forbidden:** browser offsets (`+03:00`) as stored timezone.

## 4. Locale

- Production UI remains **Arabic RTL first**.
- `tenants.locale` stays `ar` for MVP.
- Settings API **returns** `locale` but **rejects** PATCH attempts to change it (`422` / immutable field).
- No locale selector in UI.

## 5. Currency

- No tenant-level currency setting.
- Contracts keep row-level `currency` with default from `config('contracts.default_currency', 'SAR')`.
- No FX.

## 6. Tenant identity

- Editable display name = `tenants.name` (Arabic company name).
- `tenant_code` never editable here.
- Contacts optional; not a CRM/company-profile module.

## 7. Precedence / defaults

```text
Effective Settings response =
  current tenants row columns
  (+ future: registered tenant_settings keys overlaying config defaults)
```

- DB defaults already seed `locale=ar`, `timezone=Asia/Riyadh`.
- No requirement to pre-insert `tenant_settings` KV rows for MVP.

## 8. Resolver

Implementation introduces a single resolver (name suggestion: `TenantSettingsResolver` / `TenantSettingsReader`) used by the Settings API and available to modules that need a typed DTO.

- MVP: reads **Tenant** only.
- Future KV keys: resolver applies catalog + config fallback; modules **must not** scatter raw `TenantSetting::query()` for business rules.

Timezone consumers may keep reading `$tenant->timezone` directly (column SoT) — Settings update mutates that column.

## 9. Caching

- **No** short-lived settings cache in MVP.
- After PATCH: invalidate frontend settings query + current-user query so shell/Dashboard pick up new name/timezone.

## 10. Concurrency

- Low volume. Update inside a DB transaction; optional `lockForUpdate()` on the Tenant row.
- Last-write-wins acceptable; no optimistic version column required for MVP.

## 11. Audit

- Event code: **`TENANT_SETTINGS_UPDATED`** (planned inventory in [14-audit-trail/BUSINESS_RULES.md](../14-audit-trail/BUSINESS_RULES.md)).
- Payload: changed fields only, before/after, actor, tenant_id, correlation ID.
- Never audit passwords/tokens; contacts are not secrets but still treat as administrative PII in access control (permission-gated).
- GET not audited.
- Same-transaction fail-closed per ADR-0015 for domain writes.

## 12. Notifications

- **No** notification on settings change.

## 13. Unknown / immutable keys

- Unknown PATCH fields → reject entire request (`422 SETTINGS_UNKNOWN_FIELD` or validation errors).
- Immutable fields (`locale`, and any non-catalog keys) → reject if present in PATCH body attempting change.

## 14. Disabled / non-active tenants

- Existing tenant lifecycle middleware already blocks suspended/pending/archived tenants from protected APIs (`403` + stable codes). Settings does not add a bypass.

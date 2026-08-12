# Module: System Settings (إعدادات المنشأة)

> **Status:** Specified (Sprint 020) — **implementation pending**
> **Last updated:** 2026-08-12
> ADR: [ADR-0016](../../10-decisions/ADR-0016-TYPED-TENANT-SETTINGS-AND-RESOLUTION.md)

## Purpose

Give authorized tenant administrators a **single, bounded place** to view and update the small set of **tenant-level settings** already required by the implemented ERP (identity + regional defaults), without opening platform administration, secrets, or arbitrary configuration.

## Scope (MVP / Sprint 020 specification)

- Tenant-facing singleton API: `GET|PATCH /api/v1/tenant-settings`.
- Typed catalog mapping to existing **`tenants` columns** (`name`, `timezone`, contacts; `locale` read-only).
- Permissions: existing `tenant_settings.view` / `tenant_settings.update` (no rename).
- Arabic RTL UI: `/app/settings` (الإعدادات), sidebar under System.
- Audit: `TENANT_SETTINGS_UPDATED` on successful update.
- Pest + Vitest matrices documented for the implementation sprint.

## Out of scope (MVP)

- Platform Super Admin tenant registry UI/API (`platform_tenants.*`) — remains separate / TBD.
- Feature flags, plugins, theme builder, billing, notification preference center.
- Env/secret management (`APP_KEY`, DB/SMTP passwords, tokens).
- Arbitrary JSON/key dump editor.
- Locale/language productization beyond fixed Arabic.
- Tenant logo / branding uploads.
- Making operational thresholds (contract expiring-soon, meeting starting-soon, task/custody due-soon) per-tenant.
- Currency FX / multi-currency engine.
- Per-user settings.

## Personas (capability-driven)

| Template (typical) | Access |
|---|---|
| Tenant Owner | `tenant_settings.view` + `tenant_settings.update` (via full catalog) |
| General Manager | `tenant_settings.view` only (current seed) |
| Auditor / Dept Manager / Supervisor / Employee / Read-only | **No** default settings access |
| Custom roles | Any subset grantable under GrantAuthority rules |

Policies check **permissions**, never role names.

## Ownership split

| Concern | Owner |
|---|---|
| Tenant registry columns + `tenant_settings` table | `Core/Tenancy` |
| Settings catalog, resolver, Actions, Policy, API | Proposed `Modules/Settings` (or thin Controllers over Core Tenancy Actions) |
| Vue settings module | `frontend/src/modules/settings/` |
| Platform tenant APIs | Still 00-tenancy / future platform slice — **not** this UI |

## Documents in this folder

| File | Contents |
|---|---|
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Catalog, mutability, precedence, consumers |
| [PERMISSIONS.md](PERMISSIONS.md) | Exact grants |
| [API.md](API.md) | Endpoints, payloads, errors |
| [DATA_MODEL.md](DATA_MODEL.md) | Storage mapping; no duplicate columns |
| [UI.md](UI.md) | Route, sidebar, sections, save UX |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | Definition of done for implementation |
| [TEST_PLAN.md](TEST_PLAN.md) | Pest + Vitest matrices |

## References

- [00-tenancy/](../00-tenancy/) · [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md)
- [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md) · [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md)
- [MVP_SCOPE.md](../../00-project/MVP_SCOPE.md) item 21 · [MVP_KNOWN_LIMITATIONS.md](../../00-project/MVP_KNOWN_LIMITATIONS.md)

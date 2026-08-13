# System Settings — Acceptance Criteria

> **Status:** Implemented (Sprint 020)
> **Last updated:** 2026-08-13

Implementation sprint is done when all boxes can be checked.

## Functional

- [x] `GET /api/v1/tenant-settings` returns effective typed `general` + `regional` for current tenant.
- [x] `PATCH /api/v1/tenant-settings` updates only catalog-mutable fields; persists to `tenants` columns.
- [x] `locale` is returned and cannot be changed via PATCH.
- [x] Timezone accepts valid IANA only; invalid rejected.
- [x] Unknown keys rejected; no arbitrary KV writes for MVP product fields.
- [x] `/app/settings` works RTL; sidebar visible with `tenant_settings.view`.
- [x] View-only users cannot mutate (UI + API).
- [x] Owner can update; GM view-only per seed templates.

## Security / tenancy / audit

- [x] No `tenant_id` client control.
- [x] Cross-tenant isolation proven in Pest.
- [x] One `TENANT_SETTINGS_UPDATED` audit row per successful update; GET not audited.
- [x] No secrets in responses or audit payloads.
- [x] `tenant_settings.update` remains high-risk in catalog.

## Non-goals verified

- [x] No platform tenants UI in this slice.
- [x] No logo upload.
- [x] No tenant-editable operational thresholds.
- [x] No currency FX / locale picker.
- [x] No new migration (MVP uses Tenant columns).

## Quality

- [x] Pest matrix in [TEST_PLAN.md](TEST_PLAN.md) green (`TenantSettingsTest` 17/17).
- [x] Vitest matrix green; `tsc` / build green.
- [x] Docs consistent with ADR-0016.
- [x] Meets [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md).

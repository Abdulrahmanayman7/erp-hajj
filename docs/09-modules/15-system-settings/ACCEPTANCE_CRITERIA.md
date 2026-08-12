# System Settings — Acceptance Criteria

> **Status:** Specified (Sprint 020) — implementation pending  
> **Last updated:** 2026-08-12

Implementation sprint is done when all boxes can be checked.

## Functional

- [ ] `GET /api/v1/tenant-settings` returns effective typed `general` + `regional` for current tenant.
- [ ] `PATCH /api/v1/tenant-settings` updates only catalog-mutable fields; persists to `tenants` columns.
- [ ] `locale` is returned and cannot be changed via PATCH.
- [ ] Timezone accepts valid IANA only; invalid rejected.
- [ ] Unknown keys rejected; no arbitrary KV writes for MVP product fields.
- [ ] `/app/settings` works RTL; sidebar visible with `tenant_settings.view`.
- [ ] View-only users cannot mutate (UI + API).
- [ ] Owner can update; GM view-only per seed templates.

## Security / tenancy / audit

- [ ] No `tenant_id` client control.
- [ ] Cross-tenant isolation proven in Pest.
- [ ] One `TENANT_SETTINGS_UPDATED` audit row per successful update; GET not audited.
- [ ] No secrets in responses or audit payloads.
- [ ] `tenant_settings.update` remains high-risk in catalog.

## Non-goals verified

- [ ] No platform tenants UI in this slice.
- [ ] No logo upload.
- [ ] No tenant-editable operational thresholds.
- [ ] No currency FX / locale picker.
- [ ] No new migration unless a justified CR adds schema (MVP expects **none**).

## Quality

- [ ] Pest matrix in [TEST_PLAN.md](TEST_PLAN.md) green.
- [ ] Vitest matrix green; `tsc` / build green.
- [ ] Docs remain consistent with ADR-0016.
- [ ] Meets [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md).

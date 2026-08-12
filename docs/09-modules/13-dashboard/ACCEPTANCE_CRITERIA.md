# Dashboard — Acceptance Criteria

> **Status:** Implemented (Sprint 017)
> **Last updated:** 2026-08-12

## Functional

- [x] `/app` shows operational Dashboard for Users with `dashboard.view`
- [x] `GET /api/v1/dashboard` returns aggregated snapshot per [API.md](API.md)
- [x] KPI / Attention / Today / Work / Resources match [BUSINESS_RULES.md](BUSINESS_RULES.md)
- [x] Deep-links navigate to owning modules (no in-dashboard mutations)
- [x] Attention is computed from business SoRs (not notification rows)
- [x] Unread notification count is recipient-scoped
- [x] Empty/loading/error states behave per [UI.md](UI.md)
- [x] No charts; no cross-UOM quantity totals
- [x] No Documents / Employees / Finance vanity widgets
- [x] No Audit recent-activity widget (deferred)

## Security / tenancy

- [x] Cross-tenant data never influences aggregates
- [x] Unauthorized module sections omitted (not zeroed)
- [x] Self-service personal blocks do not expand to tenant-wide leakage
- [x] No `tenant_id` client input
- [x] No Dashboard audit spam on GET

## Quality

- [x] Pest matrix green (esp. tenancy + permission omit)
- [x] Vitest matrix green; `tsc` + build pass
- [x] Docs + ADR-0014 match implementation

## Explicitly not required for MVP acceptance

- [ ] Dashboard Redis cache
- [ ] Chart library
- [ ] Role-based layout presets
- [ ] Global Dashboard filters
- [ ] WebSockets
- [ ] Email/SMS digests of Dashboard

# Dashboard — Acceptance Criteria

> **Status:** Specification complete — implementation pending (Sprint 017)
> **Last updated:** 2026-08-12

## Functional

- [ ] `/app` shows operational Dashboard for Users with `dashboard.view`
- [ ] `GET /api/v1/dashboard` returns aggregated snapshot per [API.md](API.md)
- [ ] KPI / Attention / Today / Work / Resources match [BUSINESS_RULES.md](BUSINESS_RULES.md)
- [ ] Deep-links navigate to owning modules (no in-dashboard mutations)
- [ ] Attention is computed from business SoRs (not notification rows)
- [ ] Unread notification count is recipient-scoped
- [ ] Empty/loading/error states behave per [UI.md](UI.md)
- [ ] No charts; no cross-UOM quantity totals
- [ ] No Documents / Employees / Finance vanity widgets
- [ ] No Audit recent-activity widget (deferred)

## Security / tenancy

- [ ] Cross-tenant data never influences aggregates
- [ ] Unauthorized module sections omitted (not zeroed)
- [ ] Self-service personal blocks do not expand to tenant-wide leakage
- [ ] No `tenant_id` client input
- [ ] No Dashboard audit spam on GET

## Quality

- [ ] Pest matrix green (esp. tenancy + permission omit)
- [ ] Vitest matrix green; `tsc` + build pass
- [ ] Docs + ADR-0014 match implementation

## Explicitly not required for MVP acceptance

- [ ] Dashboard Redis cache
- [ ] Chart library
- [ ] Role-based layout presets
- [ ] Global Dashboard filters
- [ ] WebSockets
- [ ] Email/SMS digests of Dashboard

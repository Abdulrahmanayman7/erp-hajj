# ADR-0014: Permission-Aware Dashboard Aggregation Model

- **Status:** Accepted — **Implemented** (Sprint 017)
- **Date:** 2026-08-12
- **Sprint:** 017 (Dashboard)
- **Deciders:** ERP Hajj product / engineering (documentation lock)

## Implementation note (2026-08-12)

Shipped: `GET /api/v1/dashboard` via `Modules/Dashboard` aggregators; Gate `viewDashboard` (`dashboard.view`); sparse permission-aware payload; Attention from business SoRs; Vue `DashboardPage` at `/app` (+ `/app/dashboard` redirect); no migrations/cache/charts; Pest `DashboardTest` 11/11.

## Context

MVP modules (Contracts, Meetings, Decisions, Tasks, Documents, Inventory, Assets/Custodies, Notifications) are implemented. The authenticated home at `/app` is still a temporary welcome / quick-actions page. Stakeholders need an **operational overview**, but early stubs risked:

- Fake/vanity KPIs and BI-style charts
- Cross-UOM inventory quantity sums
- Role-name dashboard presets
- Returning zeroed KPIs for modules the User cannot view (authorization leakage)
- Turning assignee/holder self-service into tenant-wide aggregates
- Dashboard owning business data or mutating workflows
- Premature caching with weak tenant/permission keys
- Duplicating Notifications as the Attention feed
- Multiple frontend waterfall calls with inconsistent auth

## Decision

### 1. Dashboard is a read model only

`Modules/Dashboard` aggregates; owning modules remain sources of truth. No Dashboard migrations. No mutations from Dashboard endpoints.

### 2. Capability-driven visibility (not role presets)

- Page/API gate: existing `dashboard.view`
- Sections gated by underlying `*.view` (Notifications unread via recipient ownership)
- **No** `dashboard.contracts`-style permissions
- **No** role-name layout switches

### 3. Single aggregated endpoint

`GET /api/v1/dashboard` returns one permission-trimmed payload. Prefer omit-unauthorized-keys over null/`visible:false` zeros.

### 4. Self-service boundaries

Tenant-wide KPIs follow module list Policy scope. Optional `my_tasks` / `my_custodies` overlays are Employee-scoped and never substitute for unauthorized tenant totals.

### 5. No cross-UOM totals

Inventory metrics are **counts of balance rows** by `StockStateResolver` state. Never `SUM(on_hand)` across items.

### 6. Attention ≠ Notifications

Attention Center ranks business SoR problems. Notifications remain the recipient event inbox (bell + `/app/notifications`).

### 7. No MVP Dashboard cache

Compute on request with indexed `COUNT`/`EXISTS`/capped lists. Revisit cache only via Change Request with `tenant:{id}:` + permission fingerprint keys.

### 8. No charts in Sprint 017

KPI cards + lists only; do not add chart dependencies.

### 9. Canonical route `/app`

Replace temporary home; optional `/app/dashboard` redirect. Arabic RTL using existing shell.

### 10. Fail closed; no view audit

Aggregation errors fail the whole GET. Routine views are not audited.

## Consequences

- Positive: clear security model, reuse of domain semantics, simple frontend contract, aligned with multi-tenancy and RBAC.
- Trade-off: Users with few permissions see a sparse Dashboard (acceptable).
- Trade-off: No cache means every visit hits aggregates — acceptable at MVP volumes if indexes are used.
- Trade-off: Omitting keys requires frontend to tolerate partial payloads (mandatory).

## References

- [13-dashboard/](../09-modules/13-dashboard/)
- [PERMISSION_MODEL.md](../06-security/PERMISSION_MODEL.md)
- [MULTI_TENANCY.md](../02-architecture/MULTI_TENANCY.md)
- ADR-0013 (Notifications), ADR-0009 (Tasks self-service), ADR-0012 (Custody self-view), ADR-0011 (Inventory balances)

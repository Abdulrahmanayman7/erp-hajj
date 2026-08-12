# Module: Administrative Dashboard (لوحة التحكم الإدارية)

> **Status:** Specification complete — implementation pending (Sprint 017)
> **Last updated:** 2026-08-12
> ADR: [ADR-0014](../../10-decisions/ADR-0014-PERMISSION-AWARE-DASHBOARD-AGGREGATION.md)

## Purpose

Provide a **tenant-scoped, permission-aware operational overview** so authenticated Users can answer:

- What needs attention **now**?
- What is **overdue**?
- What is happening **today / soon**?
- What are the **critical operational counts** across implemented modules?

Dashboard is a **read model only**. It does **not** own business data, does **not** mutate entities, and does **not** replace module list pages or the Notifications inbox.

This is an **administrative / operational** dashboard — **not** a BI platform, command center, financial console, or Hajj GPS/operations center.

## Scope (MVP)

- Single aggregated read API: `GET /api/v1/dashboard` (see [API.md](API.md)).
- Page gate: existing catalog permission `dashboard.view`.
- Section gate: underlying module `*.view` (and Notifications recipient ownership for unread count).
- KPI cards, Attention Center (يحتاج انتباهك), Today/Soon lists, Work + Resources sections.
- Deep-links into owning modules with documented filter query params.
- Replace the temporary `AppHomePage` welcome/quick-actions at `/app` with the real Dashboard.
- Arabic RTL, light theme, responsive layout using existing design system (KPI Card, Empty/Loading/Error states).

## Out of scope (MVP)

- BI / report builder / arbitrary date-range analytics warehouse.
- Charting library / pie-chart-heavy design (see [UI.md](UI.md) — **no charts** in Sprint 017).
- Financial KPIs (revenue, expense, profit, budget, depreciation, contract value rollups as “finance”).
- Command center, GPS, pilgrims, transportation, crisis ops ([OUT_OF_SCOPE.md](../../00-project/OUT_OF_SCOPE.md)).
- Dashboard mutations (approve, assign, stock adjust, close task, etc.).
- Per-user layout customization / widget drag-drop.
- Role-name dashboard presets (capability-driven only — ADR-0014).
- Cross-UOM stock quantity totals.
- Recent Audit Trail widget (Audit **viewer** module not implemented yet — defer to Audit sprint).
- Documents vanity totals; HR headcount analytics.
- WebSockets / live “real-time” claims.
- Dashboard-specific Redis cache in MVP (indexed queries only — ADR-0014).
- Global BI filters (`organization_unit_id`, warehouse, employee) on the Dashboard endpoint.

## Personas (capability-driven)

| Persona (templates) | Typical experience |
|---|---|
| Tenant Owner / General Manager | Full operational overview (modules they hold `*.view` for — Owner holds all) |
| Department Manager | Contracts/meetings/decisions/tasks/inventory/assets sections per grants |
| Supervisor | Tasks, documents, warehouses/inventory, assets as granted |
| Employee | Tasks + assets sections; optional personal “مهامي / عُهَدي” blocks when linked Employee exists |
| Auditor | Read-only operational counts for granted modules |
| Read-only (`dashboard.view` only) | Empty operational shell + guidance (no module sections) |

Visibility is **never** decided by role name checks. Templates only seed permissions.

## Source modules

| Area | Source of truth |
|---|---|
| Tasks | Tasks module |
| Contracts | Contracts module (`expiring_soon_days` config) |
| Meetings | Meetings module |
| Decisions | Decisions module (+ Tasks for “approved with open tasks”) |
| Inventory | `inventory_balances` + `StockStateResolver` |
| Assets / Custodies | Assets module |
| Notifications | Notifications unread COUNT for **current User only** |
| Employees / Org / Documents / Audit viewer | **Not** primary MVP widgets |

## Binding decisions

| Topic | Decision |
|---|---|
| Permission | Keep `dashboard.view` + per-section module views ([PERMISSIONS.md](PERMISSIONS.md)) |
| API shape | One aggregated endpoint ([API.md](API.md)) |
| Caching | **None** in MVP |
| Charts | **None** in MVP |
| Route | Canonical `/app` (Dashboard); optional alias `/app/dashboard` → `/app` |
| Attention vs Notifications | Attention from **business SoR aggregates**; Notifications remain recipient event feed |

## References

- [ADR-0014](../../10-decisions/ADR-0014-PERMISSION-AWARE-DASHBOARD-AGGREGATION.md)
- [BUSINESS_RULES.md](BUSINESS_RULES.md) · [API.md](API.md) · [UI.md](UI.md) · [PERMISSIONS.md](PERMISSIONS.md)
- [DESIGN_GUIDELINES.md](../../05-ui-ux/DESIGN_GUIDELINES.md) (KPI Card)
- Implemented modules under [docs/09-modules/](../)

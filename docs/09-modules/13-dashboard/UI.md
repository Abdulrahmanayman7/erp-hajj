# Dashboard — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Page

- **Dashboard page** (landing page after login for users with `dashboard.view`).
- Grid of **KPI Cards** (counts) and list widgets (contracts nearing expiry, upcoming meetings, recent decisions, overdue tasks, low-stock alerts, recent audit activity).
- Each widget links to its module's filtered list page.

## Rules

- RTL grid layout; deep green/gold accents per the design system; consistent KPI Card component.
- Widgets render only with the underlying module permission; loading/empty/error states via shared components.
- Read-only — no mutations from the dashboard.

## TBD

- Widget arrangement and priority order: TBD.
- Landing page for users without `dashboard.view`: TBD.

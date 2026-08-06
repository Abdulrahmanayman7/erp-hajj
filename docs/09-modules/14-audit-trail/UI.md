# Audit Trail — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Pages and components

- **Audit logs page** — Data Table with filters (user, action, entity type, date range); expandable rows showing old/new values (masked); export button for `audit_logs.export` holders.
- **Audit History Panel** (shared component) — embedded in module detail pages (contract, employee, decision...) showing that entity's audit records chronologically.

## Rules

- Read-only UI — no edit/delete affordances exist at all.
- Old/new values rendered as readable diffs; sensitive fields shown masked.
- RTL Timeline presentation for the history panel.

## TBD

- Diff presentation detail (side-by-side vs. inline): TBD.
- Export UX (immediate download vs. notification when ready): follows the queued export decision.

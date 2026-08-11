# Organizational Structure — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Pages

- **Structure tree page** — RTL tree view of units (expand/collapse), type badges, employee counts; create/edit/move via drawer or dialog.
- **Positions list** — simple Data Table for job titles.

## Rules

- Uses shared Department Selector component (also consumed by other modules' forms).
- Deleting a unit with employees/children shows a blocking explanation (per decided rule).
- Actions gated by `departments.*` via Permission Guard.

## TBD

- Drag-and-drop reordering/moving: TBD (not required; do not build without approval).
- Org-chart graphical view: TBD.

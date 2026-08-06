# Decisions — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Pages

- **Decisions list** — Data Table with filters (status, priority, department, originating meeting); status badges.
- **Decision details** — subject/description, origin meeting link (if any), responsible department/person, dates, priority, **related tasks list with statuses and progress**, attachments, audit history panel.
- **Create/edit form** — optional meeting reference, responsible selectors, dates, priority.

## Rules

- Approve and close actions gated by their permissions, with Confirmation Dialog; close is disabled (with explanation) while required tasks are incomplete.
- "Create task" affordance from the decision details for holders of `tasks.create`.

## TBD

- Visualization of task completion progress toward decision closure: TBD.

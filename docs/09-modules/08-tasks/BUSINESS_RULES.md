# Tasks — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- Tasks must have responsible users or employees (assignee or assignees).
- The MVP supports **individual and group assignments**.
- Task progress and status must be measurable (status + progress percentage).
- Priorities: **High, Medium, Low**.
- Statuses: **New, In Progress, Blocked, Overdue, Completed, Cancelled.**
- **Overdue is derived** from due date and current status — not manually set.
- Task assignment and completion are audited events.
- Cross-tenant assignment is impossible (assignees must belong to the same tenant).
- Completing tasks feeds decision closure rules (see decisions module) — one task's completion never auto-closes a decision.

## TBD

- Group assignment semantics (one shared task vs. per-assignee copies; who completes): TBD.
- Recurring task automation: TBD — do not build without approval.
- Completion evidence requirements (mandatory? file vs. text): TBD.
- Comment editing/deletion rules: TBD.
- Seasonal task specifics: TBD.

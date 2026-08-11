# Module: Decisions (القرارات)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Manage decisions as a **separate business entity** (never merged into Tasks). A decision may originate from a meeting or exist independently, and may generate one or more tasks.

## Scope

- Decisions CRUD with statuses: New, Approved, In Progress, Completed, Blocked, Cancelled.
- Optional link to an originating meeting; links to generated tasks.
- Responsible department and responsible person.
- Attachments via the documents module.

## Out of scope

- Automatic decision closure from a single task completion (explicitly forbidden — all required tasks must complete).

## References

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) (Workflow 1) · [06-meetings/](../06-meetings/) · [08-tasks/](../08-tasks/)

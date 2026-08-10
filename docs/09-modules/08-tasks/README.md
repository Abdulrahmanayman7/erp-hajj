# Module: Tasks and Assignments (المهام والتكليفات)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Manage measurable units of work assigned to responsible users or employees, tracked from creation through execution to completion.

## Scope

- **Individual and group assignments** (MVP requirement).
- Task types: Individual, Group, Seasonal, Recurring — advanced recurring-task automation is **TBD**.
- Priorities (High/Medium/Low), statuses, progress percentage, comments, attachments, completion evidence.
- Links to related entities (notably decisions): future Tasks own nullable `decision_id` → `decisions.id` ([07-decisions/](../07-decisions/), ADR-0008). Do not put task arrays on Decision.

## Out of scope

- Advanced project management (future scope).
- Full recurring automation (TBD unless approved).

## References

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) (Workflow 1) · [07-decisions/](../07-decisions/)

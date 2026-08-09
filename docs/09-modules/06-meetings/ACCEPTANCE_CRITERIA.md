# Meetings — Acceptance Criteria

> **Status:** Implemented (Sprint 010) — acceptance verified via Pest/Vitest
> **Last updated:** 2026-08-10

A Meetings implementation is done only when all items below are true and [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md) is satisfied.

## Functional

- [ ] Meetings can be created as `draft` with server-generated `MTG-######`
- [ ] Lifecycle actions schedule / reschedule / start / complete / cancel work per graph; no PATCH status
- [ ] Attendees (employees only) can be added/removed and attendance updated before completion
- [ ] Agenda items are ordered and editable before completion
- [ ] Minutes editable via dedicated endpoint; required to complete
- [ ] Recommendations are first-class; auto-finalized on complete; immutable after complete/cancel
- [ ] Draft-only hard delete for never-advanced drafts
- [ ] Arabic RTL list + details UX with permission-aware controls
- [ ] Documents/Notifications/Decisions/Tasks not implemented as functional features

## Security / tenancy

- [ ] Every tenant-owned table scoped; cross-tenant → 404
- [ ] Foreign employee/org refs rejected
- [ ] Policies enforce `meetings.*` capabilities (no role-name checks)
- [ ] Critical actions audited with correlation ID
- [ ] Threat model cases in [TEST_PLAN.md](TEST_PLAN.md) pass

## Quality

- [ ] Pest matrix green (incl. numbering concurrency, lifecycle, recommendations isolation)
- [ ] Vitest matrix green
- [ ] Migrations match [DATA_MODEL.md](DATA_MODEL.md) order
- [ ] Permissions seeded idempotently; role templates updated safely
- [ ] Module docs marked **Implemented** only after critical tests pass

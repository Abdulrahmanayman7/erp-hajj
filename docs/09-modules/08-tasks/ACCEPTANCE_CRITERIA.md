# Tasks — Acceptance Criteria

> **Status:** Specified (Sprint 012) — **not implemented**  
> **Last updated:** 2026-08-10

A Sprint 012 implementation is done only when all items below pass and match [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md).

## Domain

- [ ] Task is first-class and distinct from Decision
- [ ] Tasks own nullable `decision_id`; Decision has no task_ids array
- [ ] Standalone Tasks allowed
- [ ] Single Employee assignee only (no multi-assignee pivot)
- [ ] No auto-create Tasks on Decision approve/close
- [ ] No Documents / notification delivery

## Numbering

- [ ] `TSK-000001…` tenant-scoped, immutable, sequence + `FOR UPDATE`

## Lifecycle

- [ ] Statuses: draft → assigned → in_progress → completed; cancel from early states
- [ ] Overdue derived, not a status
- [ ] No free PATCH of status
- [ ] No reopen from terminal
- [ ] Completion requires `completion_notes`; sets `completed_at` and progress 100

## Decision integration

- [ ] Create only against `approved` Decision
- [ ] Decision details shows linked Tasks + إنشاء مهمة
- [ ] Close Decision blocked while open linked Tasks (`DECISION_CLOSE_NOT_ALLOWED`)
- [ ] Completing last Task does not auto-close Decision

## Assignee / self-service

- [ ] Assign/reassign with history + audit
- [ ] Linked User assignee can view/start/progress/complete own Task
- [ ] Cannot self-cancel / self-reassign / self-delete
- [ ] No linked Employee → no self-service

## Delete

- [ ] Untouched draft only; no SoftDeletes

## Security

- [ ] Cross-tenant 404 suite
- [ ] Policies capability + assigneeSelf rules only
- [ ] Permissions seeded per [PERMISSIONS.md](PERMISSIONS.md)

## API / audit

- [ ] Endpoints match [API.md](API.md)
- [ ] Audit events for create/update/assign/reassign/start/progress/complete/cancel/delete

## UI

- [ ] Sidebar المهام + list + details
- [ ] مهامي filter; overdue badge; lifecycle dialogs
- [ ] Decision Tasks section + close blocked UX
- [ ] RTL / responsive

## Tests

- [ ] Pest matrix green including tenancy, close gate, self-service
- [ ] Vitest matrix green
- [ ] `tsc` passes

## Docs

- [ ] Module docs marked Implemented after ship
- [ ] Decisions docs updated for Tasks section + close gate
- [ ] Documents/Notifications still not implemented

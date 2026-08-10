# Decisions — Acceptance Criteria

> **Status:** Specified (Sprint 011) — **not implemented**  
> **Last updated:** 2026-08-10

A Sprint 011 implementation is done only when all items below pass and match [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md).

## Domain

- [ ] Decision is a first-class entity distinct from MeetingRecommendation and Task
- [ ] No Task rows created; no auto task generation
- [ ] No `decision_id` column on `meeting_recommendations`
- [ ] Standalone Decisions allowed (`source_recommendation_id` null)
- [ ] One final recommendation maps to at most one Decision (unique FK)
- [ ] No `source_meeting_id` column; meeting derived when sourced
- [ ] No type/category/priority fields in MVP

## Numbering

- [ ] Format `DEC-000001` …
- [ ] Tenant-scoped, server-generated, immutable
- [ ] Sequence + `FOR UPDATE` (no `MAX+1`)
- [ ] Separate sequences per tenant

## Lifecycle

- [ ] Statuses: draft → pending_approval → approved → closed; cancel from draft|pending_approval; return-draft from pending_approval
- [ ] No free PATCH of status
- [ ] No separate active/rejected statuses
- [ ] Return-draft requires comment
- [ ] Close only from approved; administrative only (no fake task progress)
- [ ] Content editable only in draft

## Conversion

- [ ] `POST /decisions` with `source_recommendation_id` creates draft Decision
- [ ] Requires final recommendation + completed meeting + same tenant
- [ ] Duplicate conversion → `DECISION_ALREADY_CREATED_FROM_RECOMMENDATION`
- [ ] Prefill title/body/responsible/org as specified
- [ ] Does not auto-approve

## Delete

- [ ] Hard delete only untouched draft (no transition history / never left draft)
- [ ] No SoftDeletes

## Security / tenancy

- [ ] Cross-tenant list/show/update/transition/convert → 404
- [ ] Cannot assign foreign org/employees/recommendation
- [ ] `tenant_id` not writable from payload
- [ ] Policies capability-only
- [ ] Permissions seeded per [PERMISSIONS.md](PERMISSIONS.md)

## API / audit

- [ ] Endpoints match [API.md](API.md)
- [ ] Audit events emitted for create/update/submit/return/approve/cancel/close/delete
- [ ] Correlation ID on transitions and audit

## UI

- [ ] Sidebar القرارات + `/app/decisions` + details route
- [ ] List filters/columns per [UI.md](UI.md)
- [ ] Create/edit Drawer; lifecycle via confirm dialogs
- [ ] Meetings details shows إنشاء قرار for eligible final recommendations (`decisions.create`)
- [ ] No Tasks section on Decision details
- [ ] RTL / responsive behavior

## Tests

- [ ] Pest matrix in [TEST_PLAN.md](TEST_PLAN.md) green
- [ ] Vitest matrix green
- [ ] At least one explicit cross-tenant attack suite
- [ ] `tsc` passes

## Docs

- [ ] Module docs marked Implemented after ship
- [ ] ROADMAP / CHANGELOG updated
- [ ] Tasks still marked not implemented

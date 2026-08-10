# Decisions — Business Rules

> **Status:** Implemented (Sprint 011)
> **Last updated:** 2026-08-10

Binding product rules for the Decisions module. Implementation must not invent fields, statuses, or automatic Task creation.

## 1. Identity and numbering

1. Every Decision belongs to exactly one tenant (`tenant_id` from `TenantContext` only — never from the client payload).
2. `decision_number` is **server-generated**, **immutable**, and **tenant-unique**.
3. Format: `DEC-` + six zero-padded digits (`DEC-000001`, `DEC-000002`, …).
4. Allocation uses a per-tenant sequence row with `SELECT … FOR UPDATE` (same pattern as Contracts `CTR-######` and Meetings `MTG-######`). **No `MAX+1`.**
5. Clients never supply or PATCH `decision_number` or `tenant_id`.

## 2. Content (MVP fields)

| Field | Required | Notes |
|---|---|---|
| `title` | Yes | Short subject |
| `body` | Yes | Decision text / description |
| `notes` | No | Internal follow-up notes |
| `source_recommendation_id` | No | Set only via conversion path; immutable after create |
| `organization_unit_id` | No | Same tenant; active on new assignment |
| `issued_by_employee_id` | No | Employee who officially issues/owns the Decision |
| `responsible_employee_id` | No | Employee expected to **oversee** follow-up (not a Task assignee) |
| `effective_date` | No | `DATE` — when the Decision takes effect |
| `due_date` | No | `DATE` — informational target for follow-up until Tasks exist |
| `status` | System | Lifecycle only — never free PATCH |

**Omitted in MVP (do not invent):** decision type/category catalog, priority enum, rationale separate from `body`, KPI/measurement fields, completion %, document FKs, finance fields, e-signature, `task_ids` / JSON arrays, redundant `source_meeting_id`.

## 3. Source recommendation

1. Decisions **own** the optional FK `source_recommendation_id` → `meeting_recommendations.id`.
2. Do **not** add `decision_id` to `meeting_recommendations` (ADR-0007 / ADR-0008).
3. Cardinality: **one final recommendation → at most one Decision** (unique index on `source_recommendation_id` where not null).
4. Conversion prerequisites (all must hold):
   - Same tenant as the Decision.
   - Recommendation `status = final`.
   - Parent meeting `status = completed` (Workflow 1: decisions come from completed meetings when sourced).
   - No existing Decision already referencing that recommendation.
5. Prefill on conversion (editable while Decision is `draft`):
   - `title` ← recommendation `title`
   - `body` ← recommendation `description` (nullable source → empty body rejected until filled)
   - `responsible_employee_id` ← recommendation `owner_employee_id` when present
   - `organization_unit_id` ← parent meeting `organization_unit_id` when present
6. Conversion does **not** auto-approve and does **not** mutate the recommendation into a Decision.

## 4. Standalone decisions

1. A Decision **may** be created without a meeting or recommendation (`source_recommendation_id` null).
2. Same numbering, lifecycle, permissions, and audit rules as recommendation-sourced Decisions.
3. No fabricated meeting link for standalone rows.

## 5. Source meeting (derived)

1. **No** `source_meeting_id` column on `decisions`.
2. When `source_recommendation_id` is set, source meeting is derived: `recommendation → meeting`.
3. API/details may expose a compact derived meeting summary for UX/links only.

## 6. Issuer / responsible / actors

| Role | Field | Points to |
|---|---|---|
| Issuer | `issued_by_employee_id` | Employee (business identity) |
| Responsible (oversight) | `responsible_employee_id` | Employee — **not** Task assignee |
| Created by | `created_by` | User (audit actor) |
| Transition actor | `decision_status_transitions.performed_by` | User |

1. Employee FKs must be same-tenant; **active** on new assignment; historical inactive references retained.
2. Do not conflate issuer, responsible, approver User, or future Task assignee.

## 7. Organization unit

1. `organization_unit_id` nullable; same tenant; active on new assignment; inactive historical OK.
2. Referenced units are delete-protected (same pattern as Meetings/Contracts).
3. No row-level org authorization in MVP — capability-only Policies.

## 8. Dates

1. `effective_date` and `due_date` are nullable `DATE` values (not datetimes).
2. Tenant timezone applies to “today” comparisons if any UI helper is added; storage remains calendar dates.
3. When both are set: `due_date >= effective_date` (`DECISION_INVALID_DATE_RANGE`).
4. Dates do **not** auto-activate or auto-close Decisions (no scheduler in Sprint 011).
5. `due_date` is **not** a Task deadline — Tasks own their own due dates later.

## 9. Lifecycle (locked)

Statuses:

| Status | Meaning |
|---|---|
| `draft` | Editable content; not submitted |
| `pending_approval` | Awaiting single-step approval |
| `approved` | Official Decision (effective immediately for governance) |
| `closed` | Administrative closure (terminal) |
| `cancelled` | Abandoned before becoming closed (terminal) |

### Allowed transitions

| From | To | Action | Permission |
|---|---|---|---|
| `draft` | `pending_approval` | submit | `decisions.update` |
| `draft` | `cancelled` | cancel | `decisions.update` |
| `pending_approval` | `approved` | approve | `decisions.approve` |
| `pending_approval` | `draft` | return-draft | `decisions.approve` |
| `pending_approval` | `cancelled` | cancel | `decisions.update` |
| `approved` | `closed` | close | `decisions.close` |

**Not allowed:** free PATCH of `status`; reopen from `closed`/`cancelled`; `approved` → `cancelled` (use close); separate `rejected` terminal status; separate `active` status.

### Rejection / return

- Approver **returns to draft** with a **required** comment (revision path).
- No `rejected` terminal status in MVP (avoids status proliferation).

### Activation

- **`approved` means official immediately.** No separate `active` status. No activation scheduler. `effective_date` is informational metadata only.

### Closure (Sprint 011 → Sprint 012)

1. Closing means **administrative confirmation** that the Decision is finished as a governance record.
2. Completing Tasks never auto-closes a Decision.
3. **Sprint 012 close gate (ADR-0009):** `approved → closed` is **rejected** while any linked Task (`tasks.decision_id`) has status in `{draft, assigned, in_progress}` — error `DECISION_CLOSE_NOT_ALLOWED`.
4. Cancelled and completed Tasks do not block close; Decisions with zero linked Tasks may still close.
5. New Tasks may be created only while Decision is **`approved`** (not closed).

## 10. Editing rules

1. Content fields editable **only in `draft`**: `title`, `body`, `notes`, `organization_unit_id`, `issued_by_employee_id`, `responsible_employee_id`, `effective_date`, `due_date`.
2. After leaving `draft`: content immutable except via explicit transition endpoints.
3. `decision_number`, `source_recommendation_id`, `tenant_id`, `status`, `created_by` never editable via PATCH.
4. Return-to-draft restores content editability.

## 11. Delete policy

1. Hard delete **only** when status is `draft` **and** the Decision has **never left draft** (no transition history except optional create-side bookkeeping — lock: **zero rows** in `decision_status_transitions`, or equivalently never submitted).
2. Precise rule: delete allowed iff `status = draft` and no non-create transition has occurred; practical check: **no transition rows** with `to_status ≠ draft` and Decision never had `pending_approval`/`approved`/`cancelled`/`closed`. Simplest implementable rule: **`status = draft` AND no rows in `decision_status_transitions`**.
3. After any leave-draft transition: no hard delete; preserve history.
4. No SoftDeletes in MVP.

## 12. Documents

1. No attachments in Sprint 011.
2. Future Documents module may link via polymorphic / `decision_id` — document only; do not add upload placeholders.

## 13. Notifications (hooks only)

Future notification keys (no delivery in Sprint 011):

- `DECISION_SUBMITTED`
- `DECISION_APPROVED`
- `DECISION_RETURNED_TO_DRAFT`
- `DECISION_CLOSED`
- `DECISION_CANCELLED`

## 14. Future / linked Tasks integration (Sprint 012)

1. Tasks own nullable `decision_id` → `decisions.id` ([08-tasks/](../08-tasks/), ADR-0009).
2. Do **not** add `task_ids` or JSON arrays on Decision.
3. Do **not** auto-create Tasks on approve/close.
4. Decision details UI (Sprint 012): show linked Tasks section + إنشاء مهمة when approved; omit until Tasks is implemented.
5. Close gate: see Closure rules above (`DECISION_CLOSE_NOT_ALLOWED`).

## 15. Tenancy and security

1. All Decision queries use `UsesTenantScope` / `TenantOwned`.
2. Cross-tenant access → **404** (no leakage).
3. Same-tenant validation for recommendation, org unit, issuer, responsible.
4. `tenant_id` injection ignored / rejected.
5. Policies are capability-only — never role-name checks in application logic.

### Threat model (mandatory tests)

Tenant A must not be able to:

- list / view / update / transition Tenant B Decisions
- convert Tenant B recommendations
- assign Tenant B organization unit, issuer, or responsible employee
- inject `tenant_id` successfully

Also blocked:

- duplicate recommendation conversion
- status PATCH bypass
- mutating immutable `decision_number`
- unauthorized approve/return/close
- deleting non-untouched drafts
- destroying terminal history via delete

## 16. Performance

1. Paginate list endpoints; default page size per API standards.
2. Tenant-leading indexes per [DATA_MODEL.md](DATA_MODEL.md).
3. Eager-load compact relations on list/show; transitions on details only.
4. Avoid N+1 on list.
5. Employee/org selectors use existing scalable search patterns (not unbounded dumps).

## 17. Audit

Every critical action listed in [API.md](API.md) / [TEST_PLAN.md](TEST_PLAN.md) emits a named security/audit event with tenant, actor, target ids, correlation ID. No secrets.

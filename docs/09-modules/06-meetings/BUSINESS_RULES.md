# Meetings — Business Rules

> **Status:** Specified (Sprint 010) — approved for implementation
> **Last updated:** 2026-08-09

Binding Workflow 1: [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md). Domain separations: [README.md](README.md).

## 1. Identity and numbering

- Every meeting has an immutable `meeting_number`: **`MTG-` + zero-padded 6 digits** (`MTG-000001`…).
- Generated **server-side only** via tenant-scoped `meeting_number_sequences` with `SELECT … FOR UPDATE`.
- Never accept `meeting_number` or `tenant_id` from client payloads.
- Client-supplied numbers are ignored/rejected; duplicates impossible under `(tenant_id, meeting_number)` unique.

## 2. Meeting type

- **No `meeting_type` field** in MVP.
- No tenant catalog of meeting types.
- Classification (if needed later) requires Change Request / ADR — do not invent taxonomy in Sprint 010.

## 3. Status lifecycle

| Status | Arabic | Meaning |
|---|---|---|
| `draft` | مسودة | Working copy; not yet scheduled |
| `scheduled` | مجدول | Has `scheduled_at`; awaiting start |
| `in_progress` | جارية | Meeting started (`started_at` set) |
| `completed` | مكتملة | Terminal success; minutes locked |
| `cancelled` | ملغاة | Terminal cancel |

### Allowed transitions

```
draft        → scheduled | cancelled
scheduled    → in_progress | cancelled   (+ reschedule stays scheduled)
in_progress  → completed | cancelled
completed    → (none)
cancelled    → (none)
```

- Clients **must not** set `status` via generic `PATCH`.
- Use explicit action endpoints only ([API.md](API.md)).
- **No reopen** of `completed` / `cancelled` in MVP.
- Each successful transition appends one row to `meeting_status_transitions` and emits an audit event.

### Timestamps on transitions

| Action | System sets |
|---|---|
| Schedule | `scheduled_at` (from request); status → `scheduled` |
| Reschedule | new `scheduled_at`; status remains `scheduled` |
| Start | `started_at = now()`; status → `in_progress` |
| Complete | `ended_at = now()`; status → `completed` |
| Cancel | status → `cancelled` (reason/comment required) |

## 4. Scheduling

- `scheduled_at` is a **datetime** stored in UTC; display uses **tenant timezone** (default `Asia/Riyadh` for رفيع).
- Required when transitioning `draft → scheduled` and on reschedule.
- **Past `scheduled_at` is allowed** (operational backfill of meetings already held).
- No explicit duration field; duration may be derived later from `started_at`/`ended_at` when both set.
- No calendar product integration in Sprint 010.

## 5. Location

| Field | Rules |
|---|---|
| `location_type` | Required enum: `physical` \| `remote` \| `hybrid` (default `physical`) |
| `location_text` | Nullable free text (room / address) |
| `meeting_link` | Nullable URL/string for remote/hybrid; **not** validated against Zoom/Teams APIs |

No conferencing API integration.

## 6. Organization unit

- Optional `organization_unit_id` (same tenant).
- New assignment requires **active** unit; inactive historical reference may remain.
- Changing to a different inactive unit → `MEETING_ORGANIZATION_INVALID`.
- Deleting an organization unit that is referenced by meetings is **blocked** (`ORGANIZATION_UNIT_IN_USE` or equivalent) — same RESTRICT pattern as Employees/Contracts.
- **No row-level org authorization** in Sprint 010.

## 7. Chairperson and secretary

- Optional `chairperson_employee_id` and `secretary_employee_id` → `employees` (same tenant).
- New assignment requires **active** employee; inactive historical refs retained.
- These are **not** RBAC roles and are **not** inferred from OrganizationUnit manager.
- Chairperson/secretary **may also appear** as attendees (recommended for attendance records).
- RBAC still gates who may edit the meeting.

## 8. Attendees

- Table `meeting_attendees`; **employees only** in MVP.
- Unique `(meeting_id, employee_id)` within tenant.
- External guests / users-without-employee: **out of Sprint 010** (TBD future).
- Manage via `meetings.manage_attendees`.
- Removable while meeting is `draft` \| `scheduled` \| `in_progress`.
- After `completed` \| `cancelled`: attendees **immutable** (no add/remove/status change).

### Attendance status

| Status | Arabic | Use |
|---|---|---|
| `invited` | مدعو | Default when added |
| `attended` | حضر | Marked after/during meeting |
| `absent` | غائب | Did not attend |
| `excused` | معتذر | Excused absence |

Who marks attendance: any actor with `meetings.manage_attendees` (not the attendee self-service portal — none in MVP).

## 9. Agenda

- Structured rows in `meeting_agenda_items` (ordered by `sort_order`).
- Fields: `title` (required), `description` (nullable), `sort_order`.
- Editable with `meetings.update` while meeting is `draft` \| `scheduled` \| `in_progress`.
- Immutable after `completed` \| `cancelled`.
- Agenda items are **discussion topics**, not recommendations.

See [ADR-0007](../../10-decisions/ADR-0007-MEETING-AGENDA-AND-RECOMMENDATIONS.md).

## 10. Minutes

- Stored as `meetings.minutes_body` (TEXT, nullable).
- Edited via dedicated minutes endpoint (`meetings.manage_minutes`).
- Editable while `draft` \| `scheduled` \| `in_progress`.
- **Locked** when `completed` or `cancelled` (no amendments workflow in MVP — corrections require Change Request later).
- No rich-text package; plain/textarea content.
- No SoftDeletes; changes audited as `MEETING_MINUTES_UPDATED`.

## 11. Recommendations

- First-class `meeting_recommendations` rows belonging to a meeting.
- Optional `agenda_item_id` (same meeting) and optional `owner_employee_id` (active on assign).
- Status: `draft` \| `final` only — **not** a decision workflow.
- Editable (CRUD) while meeting is `draft` \| `scheduled` \| `in_progress` with `meetings.manage_minutes`.
- When meeting becomes `completed` or `cancelled`: recommendations become **immutable**.
- On **complete**: any remaining `draft` recommendations are automatically set to `final` in the same transaction (so completed meetings expose finalized outcomes).
- **No `decision_id`** column in Sprint 010.
- Future Decisions module may add optional `meeting_recommendation_id` / `meeting_id` when converting — **owned by Decisions**, not Meetings.
- No UI control “إنشاء قرار” in Sprint 010.

## 12. Completion requirements

`in_progress → completed` requires **all** of:

1. Current status is `in_progress`.
2. `minutes_body` is present and non-empty after trim → else `MEETING_MINUTES_REQUIRED` / `MEETING_COMPLETION_REQUIREMENTS_NOT_MET`.
3. Actor has `meetings.update` (complete uses update capability).

Not required: attendance fully marked; any recommendation rows; agenda items.

## 13. Cancel and reschedule

### Cancel

- Allowed from: `draft`, `scheduled`, `in_progress`.
- **Not** from `completed`.
- **Comment/reason required**.
- Permission: `meetings.cancel`.
- Recommendations/agenda/attendees/minutes preserved (immutable after cancel).

### Reschedule

- Allowed only from `scheduled`.
- New `scheduled_at` required; validated datetime.
- Permission: `meetings.update`.
- Emits `MEETING_RESCHEDULED` + optional transition history note (status stays `scheduled`; still append a history row with `from_status=scheduled`, `to_status=scheduled`, comment describing reschedule **or** record via audit only — **locked:** append transition row with same from/to + comment for timeline UX).

## 14. Delete policy

- Hard delete **only** if:
  - `status = draft`, and
  - meeting has **never left draft** (only create transition `null → draft` in history), and
  - actor has `meetings.update` (draft delete folded into update — no separate `meetings.delete` permission in MVP catalog).
- After any non-create transition: **no hard delete** — use cancel.
- No SoftDeletes.
- Cascades: deleting eligible draft removes attendees, agenda items, recommendations, and transition rows for that meeting only.

Supersedes earlier stub wording about “privileged delete of completed meetings” — completed meetings are **preserved**.

## 15. Documents

- No upload/download in Sprint 010.
- Future: meeting / agenda / minutes attachments via Documents module.
- UI may show placeholder: المرفقات ستتوفر مع وحدة المستندات.

## 16. Notifications (hooks only)

Named future hooks (no delivery in Sprint 010):

- `meeting_scheduled`
- `meeting_rescheduled`
- `meeting_cancelled`
- `meeting_starting_soon` (scheduler TBD with Notifications module)

## 17. Derived list hints (not statuses)

| Hint | Rule |
|---|---|
| `upcoming` | `scheduled` and `scheduled_at >= now(tenant tz)` |
| `today` | `scheduled` or `in_progress` and `scheduled_at` calendar-date = today(tenant tz) |
| `past` | `completed`/`cancelled` or `scheduled_at < now` while still scheduled (overdue) — filter docs in [API.md](API.md) |

Do not invent lifecycle statuses for these.

## 18. Audit

Every critical action listed in [API.md](API.md) / [TEST_PLAN.md](TEST_PLAN.md) emits a named security/audit event with tenant, actor, target ids, correlation ID. No secrets.

## TBD (non-blocking for Sprint 010 implementation)

| Item | Note |
|---|---|
| Exact GM/DeptMgr permission seed map | Safe defaults in [PERMISSIONS.md](PERMISSIONS.md); refine at seed PR |
| External attendees | Deferred |
| Minutes amendments after complete | Deferred |
| Decision conversion UX | Sprint 011 |
| `meeting_starting_soon` job timing | Notifications sprint |

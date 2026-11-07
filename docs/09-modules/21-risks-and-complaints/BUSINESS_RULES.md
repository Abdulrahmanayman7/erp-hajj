# Risks & Complaints — Business Rules

> **Status:** PART 0 FINAL — decisions Q1–Q6, P01–P04, A01, N01, D01–D12 binding
> **Module:** `21-risks-and-complaints` (S07)

## BR-00 Tenancy, season, and authorization inputs

1. Every risk and complaint row stores **`tenant_id`** and **`season_id`** (Q5).
2. `tenant_id` comes only from authenticated tenant context (ADR-0003). Never from request body.
3. Active season / `X-Season-Id` Season Context must match the row’s `season_id` for mutations and for season-scoped reads that require the header.
4. Cross-tenant or wrong-season resource id → **404** (never 403 for isolation misses).
5. Deny by default: every action requires the matching `module.action` permission via Policy (P04).
6. Staff-only intake (D10): no anonymous or pilgrim-portal create endpoints in S07.

## BR-01 Risk lifecycle (D01)

### States

`identified` → `under_assessment` → `approved` → `treating` → `closed`

On `closed`, require `closure_outcome` ∈ {`mitigated`, `accepted`, `transferred`, `cancelled`}.

### Allowed transitions

| From | To | Permission / gate |
|---|---|---|
| — | `identified` | `risks.create` (P01) |
| `identified` | `under_assessment` | `risks.assess` (start assessment; may set/update L/I) |
| `under_assessment` | `approved` | `risks.approve_assessment` + SoD (P02) + treatment present (D11) + L/I present |
| `approved` | `treating` | `risks.assign` or start-treatment action with treatment owner |
| `treating` | `closed` | `risks.close` + `closure_outcome` + reason/notes as implemented |
| Any non-allowed pair | — | `422` `RISK_INVALID_TRANSITION` |

### Assessment matrix (Q3, D02)

1. `likelihood` and `impact` are integers **1..5** inclusive.
2. `score = likelihood * impact` (integer 1..25).
3. `severity_level` derived:

| Score | severity_level |
|---|---|
| 1–4 | `low` |
| 5–9 | `medium` |
| 10–15 | `high` |
| 16–25 | `critical` |

4. Out-of-range L/I → `422` `RISK_MATRIX_OUT_OF_RANGE`.
5. Approval without L/I → `422` `RISK_ASSESSMENT_INCOMPLETE`.

### Separation of duties (P02)

1. Actor approving assessment **must not** be the same User as `created_by`.
2. Even if the actor holds `risks.approve_assessment`, reject with `422` `RISK_SELF_APPROVAL_FORBIDDEN`.
3. UI may hide the control; server remains authoritative.

### Treatment plan (D11)

Before/at approval, a treatment row (or embedded payload) must include:

- `title` (required)
- `plan_body` (required — actions/narrative)
- `owner_employee_id` (required — active same-tenant employee)
- `due_at` (optional unless Owner later tightens; Part 0 default **optional**)
- `status` (e.g. `planned` / `in_progress` / `done` — exact enum fixed in Part 1)

Missing treatment on approve → `422` `RISK_TREATMENT_REQUIRED`.

### Soft-delete (D05)

- Allowed only while `status = identified` and no assessment progression beyond identified.
- Otherwise `422` `RISK_DELETE_NOT_ALLOWED`.
- Numbers are never reused (D06).

## BR-02 Complaint lifecycle (Q4, P03, D03, D04)

### States

`new` → `under_review` → `in_progress` → `resolved` → `closed`

### Allowed transitions

| From | To | Actor / permission | Required payload |
|---|---|---|---|
| — | `new` | `complaints.create` | title, body, category_code, season |
| `new` | `under_review` | `complaints.update` (start review) | — |
| `under_review` | `in_progress` | `complaints.assign` | `assignee_employee_id` active same-tenant |
| `in_progress` | `resolved` | `complaints.resolve` (assignee proposes) | `resolution_text` required |
| `resolved` | `closed` | `complaints.close` (supervisor) | `close_reason` required (P03) |
| `closed` | `under_review` | `complaints.reopen` | `reopen_reason` required (D04); **no time limit** |
| Illegal | — | — | `422` `COMPLAINT_INVALID_TRANSITION` |

### Escalation (D03, Q1)

1. Escalation is allowed when status ∈ {`under_review`, `in_progress`, `resolved`} (not from `closed` without reopen; not before create).
2. Actors: the current **assignee’s User**, **or** any User with `complaints.escalate`.
3. Escalation records: `escalation_level` (monotonic int), `escalated_to_employee_id`, `escalate_reason`, `escalated_at`, actor.
4. Escalation **does not** jump to `closed`. It does not replace the Q4 graph.
5. Each escalation emits audit + notification (A01, N01).

### Post-close rules

1. No content PATCH while `closed`.
2. Only `reopen` may leave `closed`.
3. After reopen, workflow continues from `under_review` (re-assign as needed).

### Soft-delete (D05)

- Allowed only while `status = new` and not assigned.
- Otherwise `422` `COMPLAINT_DELETE_NOT_ALLOWED`.

## BR-03 Optional operational links (Q5, D08)

Nullable foreign keys (names finalised in DATA_MODEL):

| Link | Validation when present |
|---|---|
| Season pilgrim | Exists; same tenant; belongs to same season |
| Group | Exists; same tenant; season-aligned |
| Facility | Exists; same tenant; season scope / allocation check as Accommodation rules allow |
| Vehicle | Prefer season vehicle (`transport_season_vehicles`); same tenant + season |
| Project | SeasonProject exists; same tenant + season; **only if** SeasonProjects schema available |

Rules:

1. Omitting all links is valid.
2. Invalid / cross-tenant / wrong-season → `422` `LINKED_ENTITY_INVALID` (or 404 if id treated as resource — prefer **422** on create/update payload).
3. `project_id` never required to create (D08).
4. If `project_id` sent but SeasonProjects unavailable → `422` `SEASON_PROJECT_LINK_UNAVAILABLE`.

## BR-04 Numbering (D06)

1. On create, allocate next sequence for `(tenant_id, season_id, year)` in the same DB transaction.
2. Formats: `RSK-YYYY-######`, `CMP-YYYY-######`.
3. Soft-deleted numbers are **not** recycled.

## BR-05 SLA (D07)

No SLA due clocks, breach scanners, or SLA fields in S07 MVP.

## BR-06 Audit (A01)

Emit append-only audit (tenant_id + correlation id) for at least:

- risk/complaint create, material update, soft-delete
- assess, approve_assessment, assign, close (risk)
- assign, escalate, resolve, close, reopen, add_note (complaint)

Do not put secrets/tokens in audit values.

## BR-07 Notifications (N01 + ADR-0013)

1. Channel: **in-app only**; recipient = `User`; resolve Employee→User; skip if no `user_id`.
2. Triggers: assign (risk/complaint), complaint escalate, risk severity `high`|`critical` on assessment approval (and on re-assessment that newly enters/crosses those bands), complaint resolve, complaint reopen.
3. Dedupe via `UNIQUE(tenant_id, dedupe_key)` using **occurrence keys** (see readiness report / notifications section in TEST_PLAN). **No** automatic 24-hour suppression window for lifecycle events.
4. New notification types must be added to the curated catalog in Part 4 (engineering), without inventing external channels.

## BR-08 Concurrency (D12)

1. Transitions lock the aggregate row (`SELECT … FOR UPDATE`).
2. If persisted status ≠ expected precondition → reject with stable code `CONFLICT_STALE_STATE`.
3. **Preferred HTTP:** `409` for stale concurrency; `422` for domain rule failures (invalid transition, SoD, missing reason). Final mapping confirmed in Part 2 implementation review.

## BR-09 Stable error codes (machine-readable)

| Code | When |
|---|---|
| `RISK_INVALID_TRANSITION` | Illegal risk status change |
| `RISK_SELF_APPROVAL_FORBIDDEN` | P02 |
| `RISK_MATRIX_OUT_OF_RANGE` | L/I not in 1..5 |
| `RISK_ASSESSMENT_INCOMPLETE` | Approve without L/I |
| `RISK_TREATMENT_REQUIRED` | Approve without treatment |
| `RISK_DELETE_NOT_ALLOWED` | D05 |
| `COMPLAINT_INVALID_TRANSITION` | Illegal complaint status change |
| `COMPLAINT_CLOSE_REASON_REQUIRED` | P03 |
| `COMPLAINT_REOPEN_REASON_REQUIRED` | D04 |
| `COMPLAINT_DELETE_NOT_ALLOWED` | D05 |
| `LINKED_ENTITY_INVALID` | Q5 validation fail |
| `SEASON_PROJECT_LINK_UNAVAILABLE` | D08 module missing |
| `CONFLICT_STALE_STATE` | D12 |

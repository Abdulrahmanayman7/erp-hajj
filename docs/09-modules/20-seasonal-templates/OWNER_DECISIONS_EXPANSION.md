# S08 Expansion Owner Decisions (Option A — Field Allowlists)

> **Status:** BINDING for feature-branch coding under Owner Option A (2026-11-08)  
> **MATRIX_VERSION after expansion:** `2026-11-08.1`  
> Does **not** authorize merge/deploy/Owner Accepted.

## X01 — Independent snapshots (R1)

- Templates MAY store `snapshot_json` of allowlisted field payloads per item.  
- `from_template` MAY omit live `source_season_id` when every selected kind has a non-empty snapshot.  
- If any selected kind lacks a snapshot → require `source_season_id` (hybrid fallback) or reject with stable code.  
- Capture snapshots only via existing allowlisted adapters (never raw tables).

## X02 — Contract templates (R2)

| Field | Decision |
|---|---|
| `title` | APPROVED |
| `contract_category_id` | APPROVED (same-tenant exists) |
| `counterparty_kind` | APPROVED |
| `notes` | APPROVED (non-secret) |
| `currency` | APPROVED |
| `start_date` / `end_date` | APPROVED with `season_start_delta` remap; create requires start_date |
| `counterparty_name` | **DENY** (may be personal/commercial PII) — use placeholder `من القالب` |
| `value` | **DENY** (financial) |
| `employee_id`, `supplier_id`, `organization_unit_id` | **DENY** |
| `status`, numbers, transitions, signatures, attachments | **DENY** — always create via `CreateContract` as **draft** |

## X03 — SeasonProjects (R3) — D08 coding lift for Option A only

| Entity | APPROVED fields | DENY |
|---|---|---|
| Project | `title`, `description` | `owner_employee_id`, `budget_amount`, `currency_code`, progress/delay, lifecycle timestamps, number (regenerate), status (force **draft**) |
| Phase | `title`, `sort_order`, `starts_on`, `ends_on` (remap) | Any progress; status force **planned**; no task links |

PO links / budgets / expenditures: **DENY**.

## X04 — Task templates (R4)

| Field | Decision |
|---|---|
| `title`, `description`, `notes`, `priority` | APPROVED |
| `start_date`, `due_date` | APPROVED with remap |
| `organization_unit_id` | APPROVED if same-tenant exists; else omit |
| `assigned_to_employee_id` | **DENY** (no silent reassignment) |
| `decision_id`, completion fields, delay, status history | **DENY** — create via `CreateTask` as **draft** |
| Season link | Only via `project_id` created/selected in target season (S06 linkage) |

Recurrence engine / cron: **out of this slice** — template produces a **batch of pending draft tasks** at copy time, not a scheduler.

## X05 — Date remap (R5)

- Strategy: `season_start_delta` using `operational_starts_on` (fallback season date fields if null → `DATE_ANCHOR_MISSING` exclude).  
- Preview must list before/after.  
- Reject lines where remapped end < start.

## X06 — Reminder rules (R6)

> **Superseded (2026-11-08):** Owner approved S08-D11…D16 — implemented as `season_reminder_rules` under MATRIX `2026-11-08.2`.  
> See [OWNER_DECISIONS_D11_D20.md](OWNER_DECISIONS_D11_D20.md).  
> Copying `notifications` rows remains **DENY**. Legacy kind alias `reminder_rules` remains **DENIED**.

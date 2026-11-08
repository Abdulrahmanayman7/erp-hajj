# S08 Full-Scope Gap Audit (Phase 0)

> **Date:** 2026-11-08  
> **Branch:** `feature/s08-seasonal-templates` @ `a194841`  
> **Owner act:** Option A — complete original S08 concept + safe S06 integration (coding on feature branch; no merge/deploy)

## Coverage matrix

| # | Original / Owner requirement | Current implementation | Gap | Proposed safe implementation | Security constraints | Acceptance tests |
|---|---|---|---|---|---|---|
| R1 | Independent reusable template library (ADR-0020 S08; D02) | Template CRUD exists; items = kinds+codes only; `from_template` still requires live `source_season_id`; **no `snapshot_json` column** | Cannot instantiate without live season | Capture allowlisted snapshots at template create/approve; `from_template` plans from snapshots when present; nullable `source_season_id` for snapshot path | Snapshots = allowlisted fields only; hash into fingerprint; tenant-scoped capture | Preview/execute without source season; tamper hash fails; PII kinds still denied |
| R2 | Contract templates → NEW DRAFT only (Owner Phase 2) | Not in allowlist; no adapter | Missing | Adapter/`CreateContract` path; draft only | Deny signed/executing/history/signatures/attachments/financial txns; no status copy | Creates `draft` only; never copies non-draft status |
| R3 | Planning / SeasonProjects templates (Owner Phase 3; D08 lifted for coding by Option A) | Matrix DEFER; no adapter; S06 code present on branch | Missing | Copy project `title`/`description` + phases `title`/`sort_order`/remapped dates; status reset | Deny budgets, PO links, progress, delay, owner unless validated later; no execution history | Fresh draft projects + planned phases; no budget/PO copy |
| R4 | Recurring task templates (Owner Phase 4) | No recurrence model; Tasks omit recurring in MVP docs | Missing as generator from template defs | Template-defined pending tasks under target project (season via S06 linkage) | Deny completion/evidence/comments/history; assignees only if same-tenant employee validates | Creates draft/assigned-pending tasks; no historical status |
| R5 | Planning date remapping (ADR-0020; D03) | `date_mapping_json` stores delta; `transformed_fields=[]` | No field transforms | Apply `season_start_delta` to approved date fields; preview before/after | Never remap execution `*_at` | Preview shows transforms; end≥start validated |
| R6 | Notification/reminder **rule** templates (Owner Phase 5) | **No reminder-rule table** — only `notifications` rows + config scanners; CMS is Notifications non-goal | Domain model absent | **DISABLED** pending Owner decision to introduce rule entity | Must never copy sent notifications / delivery logs | N/A until model approved |

## Existing APPROVED kinds (unchanged)

`season_programs`, `season_pilgrim_groups`, `transport_entitlements`, `transport_season_vehicles`, `accommodation_season_facilities` — MATRIX `2026-11-07.1`.

## Gate application

- R1–R5: Owner Option A + Phase constraints = field-level approval for the **narrow allowlists** in [OWNER_DECISIONS_EXPANSION.md](OWNER_DECISIONS_EXPANSION.md).  
- R6: **left disabled** — prepare decision only.  
- Uncertain fields (contract `value`/`counterparty_name`, project `owner_employee_id`/`budget_*`, task assignees): **default DENY** unless listed as APPROVED below.

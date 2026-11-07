# S08 — Safe Copy Entity Matrix (Proposal)

> **Status:** `IMPLEMENTED — MATRIX_VERSION 2026-11-07.1` (coding Owner-authorized 2026-11-07)  
> **Policy:** Deny by default (S08-D01). Unlisted / unapproved = **NOT AUTHORIZED FOR COPYING**.  
> **Code source of truth:** `backend/app/Modules/SeasonalTemplates/Support/SafeCopyAllowlist.php`  
> **Baseline tables:** verified present on `develop` @ `a21e4ca` unless marked absent.  
> Rows marked **DENY** remain permanent for S08 unless a new Owner decision supersedes.

## Legend

| Approval status | Meaning |
|---|---|
| **APPROVED** | Implemented in `SafeCopyAllowlist` / adapters |
| **DENY** | Hard forbidden under S08-D01; no adapter may copy |
| **DISABLED** | Explicitly left out of v1 (staff contacts, optional catalogs) |
| **DEFER** | Blocked by S08-D08 / missing module (e.g. S06) |
| **N/A-SHARED** | Tenant-level shared master — reference only; missing → preview conflict (never auto-create) |

Conflict behavior defaults (D04): `skip_manual` \| `overwrite_if_system` \| `fail` — exact per-entity default proposed below; Owner may override at coding gate.

---

## A. Absolute denylist (all domains)

| Domain | Entity / table | Reason | Approval |
|---|---|---|---|
| SeasonPilgrims | `pilgrims` | PII identity | **DENY** |
| SeasonPilgrims | `pilgrim_identity_documents` | PII / ID numbers | **DENY** |
| SeasonPilgrims | `season_pilgrims` | Participation + PII linkage | **DENY** |
| SeasonPilgrims | `season_pilgrim_identity_snapshots` | Captured PII | **DENY** |
| SeasonPilgrims | `season_pilgrim_program_changes` | Execution history | **DENY** |
| SeasonPilgrims | `season_pilgrim_group_supervisors` | Operational assignment history | **DENY** |
| SeasonPilgrims | `season_family_groups` + memberships on `season_pilgrims` | Family composition / PII adjacency | **DENY** |
| SeasonPilgrims | `season_pilgrim_reference_sequences` | Operational numbering state | **DENY** (regenerate, never copy) |
| Transportation | `transport_movements` | Execution | **DENY** |
| Transportation | `transport_vehicle_runs` | Execution | **DENY** |
| Transportation | `transport_run_*_assignments`, `transport_run_passengers` | Assignments / PII adjacency | **DENY** |
| Transportation | `transport_timeline_events`, `transport_review_items` | History | **DENY** |
| Transportation | `transport_vehicle_breakdowns`, `transport_vehicle_replacements` | Ops history | **DENY** |
| Transportation | `transport_*_sequences` (movement) | Runtime counters | **DENY** |
| Accommodation | `accommodation_assignments` | Allocations | **DENY** |
| Accommodation | `accommodation_events` | Execution timeline | **DENY** |
| Accommodation | `accommodation_pilgrim_locks` | Operational locks | **DENY** |
| Inventory | `inventory_movements` | Transactions (D01) | **DENY** |
| Inventory | `inventory_balances` | Derived operational stock state | **DENY** |
| Procurement | `purchase_requests` (+ lines, versions, approvals) | Ops transactions | **DENY** |
| Procurement | RFQ / offers / PO / goods receipts / demand groups runtime | Ops cycle | **DENY** |
| Procurement | `supplier_evaluations` (+ scores, appeals) | Historical evaluations | **DENY** |
| Procurement | `supplier_bank_accounts` (+ editors, claims) | Sensitive financial | **DENY** |
| Procurement | `supplier_document_records` | Operational document instances | **DENY** |
| Procurement | `supplier_status_transitions` | History | **DENY** |
| Risks/Complaints (S07) | `operational_risks`, `operational_risk_treatments` | Ops register (D01) | **DENY** |
| Risks/Complaints (S07) | `operational_complaints`, `operational_complaint_notes` | Ops register (D01) | **DENY** |
| Risks/Complaints (S07) | `operational_*_number_sequences`, notification occurrences | Runtime/history | **DENY** |
| Audit | `audit_logs` | Immutable audit (D01) | **DENY** |
| S06 | Any `season_projects*` | Missing / D08 | **DEFER** |
| Platform | `geo_places` | Platform catalog — never tenant-copied | **N/A-SHARED** |

**Field rule:** Any `*_at` execution timestamp (`started_at`, `completed_at`, `checked_in_at`, `closed_at`, assessment times, etc.) is **never transferred**. Created/updated bookkeeping on *new* target rows is set by the target system clock at insert time — not copied from source.

---

## B. Candidate allowlist (season-owned planning / reference shells)

> Owner must approve each row’s **Eligible fields** before coding. Until then: **NOT AUTHORIZED FOR COPYING**.

### B1. SeasonPilgrims — planning structures

| Entity | Eligible fields (proposed) | Excluded fields | Relationships | Tenant | Season | Date transform | Dedup key | Conflict default | Security risk | Approval |
|---|---|---|---|---|---|---|---|---|---|---|
| `season_programs` | `code`, `name`, `description`, `is_active` | ids; pilgrim links; history | none to pilgrims | yes | yes | none | `(tenant, target_season, code)` | skip/overwrite rules in orchestrator | Low | **APPROVED** |
| `season_pilgrim_groups` | `code`, `name`, `notes` | membership; supervisors | no member links | yes | yes | none | `(tenant, target_season, code)` | skip_manual | Low | **APPROVED** — empty groups only |

`seasons` itself is **not** “copied”; target season already exists (draft). Template may store default season metadata hints only (name pattern) — no PII.

### B2. Transportation — reference & season fleet planning

| Entity | Eligible fields (proposed) | Excluded | Rel | Tenant | Season | Date transform | Dedup | Conflict | Risk | Approval |
|---|---|---|---|---|---|---|---|---|---|---|
| `transport_points` | — | — | — | yes | no | — | — | — | — | **DISABLED** (v1) |
| `transport_vehicles` | plate used as **reference key only** | never duplicated by copy | — | yes | no | — | plate | missing plate → conflict | — | **N/A-SHARED** |
| `transport_external_drivers` | — | all | — | — | — | — | — | — | High | **DENY** (staff personal data) |
| `transport_season_vehicles` | `plate_number`, `operational_capacity`, `service_level`, `is_active`, `notes` | driver/supervisor/external_driver FKs | vehicle by plate | yes | yes | none | `(target_season, plate_number)` | skip_manual; missing vehicle conflict | Med | **APPROVED** |
| `transport_entitlements` | `program_code`, `vehicle_class`, `service_level`, `notes` | ids | program by code | yes | yes | none | `(target_season, program_code)` | missing program → conflict | Low | **APPROVED** |

### B3. Accommodation — facility definitions & season shells

| Entity | Eligible fields (proposed) | Excluded | Notes | Approval |
|---|---|---|---|---|
| `accommodation_facilities` | code as **reference key only** | never duplicated | Tenant shared | **N/A-SHARED** |
| `accommodation_structures`, `accommodation_units`, `accommodation_positions` | — | — | — | **DISABLED** (v1) |
| `accommodation_season_facilities` | `facility_code`, `audience`, `is_active` | assignments/events/locks | Facility by code | **APPROVED** |
| `accommodation_season_units`, `accommodation_season_positions` | — | — | — | **DISABLED** (v1) |
| `accommodation_facility_scopes`, `accommodation_supervisor_scopes` | — | staff scope assignments | — | **DENY** |

Exact column lists must be taken from live migrations at coding time and re-approved.

### B4. Procurement — catalogs / criteria templates (not transactions)

| Entity | Eligible fields (proposed) | Excluded | Approval |
|---|---|---|---|
| `supplier_categories` / `suppliers` / contacts / bank / criteria packs | — | — | **DISABLED** (v1; contacts/bank remain **DENY**) |

### B5. Inventory — masters only

| Entity | Eligible fields (proposed) | Excluded | Approval |
|---|---|---|---|
| `warehouses` / `inventory_categories` / `inventory_items` | — | balances/movements still **DENY** | **DISABLED** (v1) |

### B6. S06 / other

| Entity | Approval |
|---|---|
| Seasonal projects, budgets, project templates | **DEFER** (S08-D08) |

---

## C. Date transformation rules (S08-D03)

| Rule ID | Description |
|---|---|
| **DT-01** | Eligible *planning* dates (e.g. planned window start/end on shells) map via Owner-approved offset: `target_field = source_field + (target_season.start_date - source_season.start_date)` (date-only) **or** explicit calendar remap table on the copy request |
| **DT-02** | If source or target season lacks the anchor date field required by the chosen rule → exclude field with reason `DATE_ANCHOR_MISSING` |
| **DT-03** | Execution timestamps never map (hard deny) |
| **DT-04** | Preview must show before/after for every transformed field |

Default mapping algorithm for Part 0 proposal: **season-start delta (DT-01)**. Alternatives remain **OPEN-Q** until coding authorization.

---

## D. Deduplication & manual protection (S08-D04)

| Mechanism | Design |
|---|---|
| Natural key | Per-entity dedup key above (usually business `code` within target season/tenant) |
| Provenance | Target row stores `copied_from_source_type`, `copied_from_source_id`, `copy_run_id`, `system_owned_flag`, `manual_touched_at` |
| Manual protect | If `manual_touched_at` set (any user update after copy) → conflict `MANUAL_MODIFIED`; default resolution `skip` unless user selects approved override with `season_copy.execute` |
| Idempotent rerun | Same allowlist fingerprint + source/target + mapping version → no duplicate inserts; updates only system-owned non-manual rows per policy |

---

## E. Security risks summary

| Risk | Control |
|---|---|
| Accidental PII copy | Hard DENY tables; preview redaction; adapter unit tests |
| Cross-tenant leak | Orchestrator asserts same `tenant_id`; Policies; 404 on foreign IDs |
| Stale preview abuse | Preview token + content hash; invalidate on version bump |
| Partial write corruption | Single DB transaction per execute; full rollback |
| Speculative S06 | DEFER + no adapter registration |

---

## F. Coding-phase decisions applied

- [x] Five kinds **APPROVED** and implemented (see Allowlist).
- [x] Shared masters: link-by-stable-key only; missing → conflict (never materialize).
- [x] Staff/driver/supplier contacts: **DENY** / not copied.
- [x] Date strategy: `season_start_delta` available on preview payload (kinds in v1 have no planning date fields to map).
- [x] Conflict defaults: skip / overwrite (overwrite only DUPLICATE_KEY + MANUAL_MODIFIED).
- [ ] Expanding DISABLED kinds requires new Owner decision + MATRIX_VERSION bump.

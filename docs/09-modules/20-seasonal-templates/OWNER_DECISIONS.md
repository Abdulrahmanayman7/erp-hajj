# S08 — Owner Decisions Register (Binding)

> **Status:** All **10** decisions **APPROVED / BINDING** (2026-11-07)  
> **Coding authorization:** **GRANTED** for implementation on `feature/s08-seasonal-templates` (Owner act superseding Part 0 D10 for development)  
> **Merge / deploy / Owner Accepted:** still **NOT** granted  
> **Baseline:** `develop` @ `a21e4ca`

## Summary

| ID | Title | Implication for Part 0 |
|---|---|---|
| **S08-D01** | Safe copy allowlist; deny-by-default | Entity matrix must list every candidate; unapproved = **NOT AUTHORIZED FOR COPYING** |
| **S08-D02** | Hybrid template library + season→season safe copy | Same controls for both paths |
| **S08-D03** | Source draft/active/closed; target **draft** only; same tenant; date mapping | Season status gates + date rules in BUSINESS_RULES / API |
| **S08-D04** | Safe reruns, idempotency, manual-protect, conflicts, preview diffs, atomic rollback, audit | Preview + execution + history design |
| **S08-D05** | Mandatory preview; invalidate on change; no PII in preview | Preview Engine contract |
| **S08-D06** | Five permissions + role matrix | PERMISSIONS.md; map Owner role labels → catalog codes carefully |
| **S08-D07** | Independent `SeasonalTemplates` module + domain adapters | ARCHITECTURE.md; no unrestricted cross-module SQL copy |
| **S08-D08** | Not blocked by S06; no speculative project-template copy | Matrix marks S06 entities deferred |
| **S08-D09** | Reconcile ROADMAP / MATRIX with evidence; no fabricated acceptance | Status audit doc |
| **S08-D10** | Originally Part 0 only; **superseded for coding** by Owner implementation authorization | Merge/deploy/acceptance still require separate acts |

---

## S08-D01 — Safe Copy Allowlist

**Rule:** Only explicitly approved reference entities, templates, and planning structures may be copied. **Deny by default.**

**Hard denylist (never copy):**

- Pilgrim PII and identity artifacts.
- Operational transactions and historical execution records.
- Operational risks and complaints (and notes/treatments).
- Inventory movement transactions.
- Actual transportation and accommodation execution records.
- Operational allocations and assignments.
- Audit logs.
- Historical execution timestamps.

**Part 0 implication:** [SAFE_COPY_ENTITY_MATRIX.md](SAFE_COPY_ENTITY_MATRIX.md) proposes candidates; each candidate stays **PENDING FIELD-LEVEL OWNER APPROVAL** until a separate coding-phase Owner act. Unlisted entities remain **NOT AUTHORIZED FOR COPYING**.

---

## S08-D02 — Hybrid Template Architecture

**Rule:** Support both:

1. Independent **approved** seasonal template library.
2. Direct safe copy of approved reference data from a previous season.

Both use the **same** security controls, entity allowlist, and validation policies.

**Part 0 implication:** Template records store *selections* of allowlisted entity kinds + optional frozen snapshots of reference payloads (non-PII). Season→season copy invokes the same orchestrator with `mode=from_season|from_template`.

---

## S08-D03 — Source and Target Season Rules

| Constraint | Value |
|---|---|
| Source status | `draft` \| `active` \| `closed` |
| Target status | `draft` only |
| Tenancy | Source and target **same** `tenant_id`; cross-tenant → fail closed (404/403 stable code per API design) |
| Dates | Explicit, reviewable date-mapping rules applied to eligible date fields only |
| Forbidden | Transfer of historical execution timestamps |

**Part 0 implication:** Date mapping defined in BUSINESS_RULES; adapters declare which fields participate.

---

## S08-D04 — Safe Reruns and Conflict Resolution

Design **must** support:

- Safe reruns into draft targets.
- Idempotency and duplicate prevention.
- Protection of manually modified target records.
- Explicit conflict detection and resolution.
- Diff preview before execution.
- Atomic execution with complete rollback on failure.
- Audit for every execution **attempt** (including failed).

---

## S08-D05 — Mandatory Preview

- Preview required before every execute, including reruns.
- Show: adds, proposed changes, conflicts, date transformations, exclusions + reasons.
- Must not expose prohibited PII.
- Any relevant source/target/config change invalidates preview.
- Execution must reject stale preview tokens.

---

## S08-D06 — RBAC

**Permission verbs (exact):**

- `season_templates.view`
- `season_templates.manage`
- `season_copy.preview`
- `season_copy.execute`
- `season_copy.history`

**Owner-approved matrix (persona labels):**

| Role (Owner label) | View | Manage | Preview | Execute | History |
|---|---|---|---|---|---|
| Tenant Owner | Yes | Yes | Yes | Yes | Yes |
| Tenant Administrator | Yes | Yes | Yes | Yes | Yes |
| Operations Manager | Yes | Yes | Yes | No | Only if explicitly granted |
| Department Supervisor | Yes | No | No | No | No |
| Regular User | No | No | No | No | No |

**Repository mapping note (not inventing grants):** Catalog templates today are `tenant_owner`, `general_manager`, `department_manager`, `supervisor`, `employee`, `auditor`, `procurement_officer`, `read_only` ([PermissionCatalog.php](../../../backend/app/Core/Authorization/PermissionCatalog.php)). Owner labels **Tenant Administrator** / **Operations Manager** do **not** match codes 1:1. Proposed mapping for implementation (requires Owner confirm before coding):

| Owner label | Proposed catalog `code` | Notes |
|---|---|---|
| Tenant Owner | `tenant_owner` | Exact |
| Tenant Administrator | `general_manager` | Closest existing “admin ops” template; **OPEN-Q** |
| Operations Manager | *no dedicated code* | Propose new template `operations_manager` **or** map to `general_manager` with execute withheld — **OPEN-Q** |
| Department Supervisor | `supervisor` | Closest; view-only for templates |
| Regular User | `employee` / `read_only` | No grants |

Tenant Owner may delegate within approved authorization rules (custom roles). Backend Policies enforce all verbs. Cross-tenant copy prohibited.

---

## S08-D07 — Independent Module + Central Orchestration

**Module name:** `SeasonalTemplates`  
**Docs path:** `docs/09-modules/20-seasonal-templates/`  
**Proposed backend path (future):** `backend/app/Modules/SeasonalTemplates/`

Capabilities: Template Management, Safe Copy Orchestrator, Preview Engine, Conflict Resolution, Execution History, Audit Trail, Domain Safe Copy Adapters.

Domain modules remain responsible for defining/validating their eligible reference entities. Orchestrator enforces tenancy, permissions, allowlist, preview validity, transactions, audit. **No** unrestricted cross-module database copying.

Adapters are architectural proposals — **not** authorization to copy all entities in a domain.

---

## S08-D08 — Independence from S06

S08 must not be blocked by S06. Seasonal project template integration deferred until:

1. S06 implementation status verified.
2. S06 completed and formally Owner-accepted.
3. Project-specific safe-copy rules explicitly approved.

No speculative S06 integrations in design or future code until then.

---

## S08-D09 — Documentation Reconciliation

Update ROADMAP / PHASE2_SCOPE_MATRIX (and relevant status docs) from **verifiable evidence** only. Distinguish: Implemented · Merged · Tested · Owner Accepted. Do not fabricate acceptance or test results. S06 remains **UNDER VERIFICATION**. S08 = Part 0 docs/design only.

---

## S08-D10 — Authorization Boundary

**Permitted now:** read-only inspection; documentation under `docs`; architecture/API/schema/security/test/implementation **proposals**.

**Prohibited now:** backend/frontend application code; creating/running migrations; DB mutation; seeders; implementing S08; commit/push/merge/deploy; reset/stash/switch branches without explicit permission.

---

## Dependencies

- Foundation seasons (`SeasonPilgrims`) — present on baseline.
- Explicit allowlist (this register + entity matrix) — Part 0 delivers proposal; field-level approval still required before coding.
- Domain adapters depend on existing Transport / Accommodation / Procurement / Inventory modules for *candidate* entities only.
- Audit (`Core/Audit`, ADR-0015) and Tenancy (ADR-0003) remain binding.
- S06 — **not** a dependency (D08).
- S07 — merged on baseline; supplies **deny** evidence for operational risks/complaints tables; not a copy source.

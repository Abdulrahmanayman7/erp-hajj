# S04 Phase V — Readiness Audit (Read-Only)

> **Status:** `OWNER DECISIONS APPROVED — ENGINEERING CLOSED — PENDING FINAL DESIGN ACCEPTANCE`  
> **Audit date:** 2026-11-01 (updated after V-D1…V-D8 approval)  
> **Branch / HEAD:** `feature/s04-phase-i-supplier-registry` @ `09a902a0a08aa31a8686ce3824e6ee25f922a112`  
> **Authorization:** documentation / design only — **no** coding, migrations, DB writes, Git mutations  
> **Depends on:** Phase IV **FINAL FUNCTIONAL ACCEPTANCE**; Phase V Owner decisions [S04-PHASE-V-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-V-OWNER-DECISIONS-2026-11-01.md)

---

## 1. Authoritative Phase V scope

### 1.1 Binding sources

| Source | Phase V content |
|---|---|
| [ADR-0020](../../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S04 | Supplier registry; evaluation; purchase requests; approval levels — S04 product stream (no Phase V label; phases are CR delivery slices) |
| [ADR-0022](../../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) **123** | Duplicate detection + **planning-only consolidation** of **approved** needs; each PR remains independent; **no** consolidated RFQ/PO in S04 |
| ADR-0022 **130** | Immutable audit of material events; **reports** on approval latency, delays, request outcomes, and compliance; tenant-scoped; retention detail deferred |
| [ADR-0023](../../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md) §2.7 | Planning-only demand groups as part of PR engineering shape |
| Module CR Phase V row | Planning groups + control reports + FE polish + hardening tests |
| [DATA_MODEL.md](DATA_MODEL.md) | `purchase_demand_groups` + `purchase_demand_group_items` — Phase V; approved PRs; no RFQ |
| [BUSINESS_RULES.md](BUSINESS_RULES.md) §3.5 / §4.6 | Demand group Phase V; control reports latency/overdue/outcomes/compliance Phase V UIs |
| [API.md](API.md) §§8–9 | Demand-group CRUD sketch; three report GETs |
| [UI.md](UI.md) §§6–7 | Planning consolidation; control reports; **CSV export required (V-D8=A)** — prior “DEFERRED” superseded |
| [PERMISSIONS.md](PERMISSIONS.md) | `purchase_requests.consolidate_planning`, `purchase_requests.view_control_reports` |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | AC-P09b (planning group ≠ RFQ/PO); AC-A08 (reports tenant-scoped) |

### 1.2 Business objectives (documented)

1. Allow an authorized official to **consolidate approved needs for planning only** without merging PRs into RFQ/PO (**123**).  
2. Provide tenant-scoped **control reports** on approval latency, delays/overdue, outcomes, and compliance (**130**).  
3. Keep supplier evaluation / registry / Phase IV approval contracts intact; hand off to S05 without S04 inventing commitments.

### 1.3 Explicit out of scope (Phase V)

| Item | Owner |
|---|---|
| RFQ, offers, selection, PO, GRN, cycle close | **S05** |
| Budget reservation / payments | Out of S04 |
| Consolidated RFQ/PO from demand groups | Forbidden in S04 (**123**) |
| Changing PR approval status via grouping | Not authorized by 123 |
| Bank ciphertext / secrets in reports | Forbidden (**PERMISSIONS** / **130**) |
| File export of control reports | **IN SCOPE** — CSV for RPT-V01…V04 (**V-D8=A**); no XLSX |
| Daily digests as Phase V reports | Excluded by IV-D14 |

### 1.4 Document inconsistencies (recorded — not silently reconciled)

| Location | Says | Conflict |
|---|---|---|
| `PHASE2_SCOPE_MATRIX.md` S04 row | Phase III DESIGN DRAFT; Phase IV–V NOT_STARTED | Stale vs Phase III/IV Owner functional acceptance |
| `ROADMAP.md` S04 row | Same stale Phase III/IV wording | Stale |
| Module `README.md` Phase IV line (pre-update) | Awaiting functional acceptance; IV-WP01 NOT AUTHORIZED | Stale vs acceptance + WP01–08 delivery |
| `UI.md` banner | Phase IV–V UI NOT IMPLEMENTED | Phase IV UI exists in worktree; Phase V still missing |
| `ACCEPTANCE_CRITERIA.md` §F | Phase IV–V NOT_STARTED | Stale |
| `PermissionCatalog.php` | No `consolidate_planning` / `view_control_reports` rows | Docs list them; catalog **MISSING** until Phase V coding |
| `TEST_PLAN.md` | No Phase V section found | Gap for V design/tests |

---

## 2. Actors, permissions, boundaries

| Actor need | Permission (docs) | Catalog today |
|---|---|---|
| Create/manage planning groups | `purchase_requests.consolidate_planning` (non-sensitive) | **MISSING** |
| View control reports | `purchase_requests.view_control_reports` (non-sensitive; Auditor template) | **MISSING** |
| View underlying PRs | `purchase_requests.view` | **EXISTS** |
| Sensitive approve/reject/etc. | Phase IV verbs | **EXISTS** (not required for V grouping itself) |

Tenant isolation: mandatory. Season/OU: PR already carries season + beneficiary unit; group-level scoping **not frozen** → Owner decisions (see register).

Integrations:

- **Supplier registry / eligibility / evaluations:** read-only join for report dimensions where PR has optional supplier; no new evaluation workflows in V.  
- **Purchase Requests + Phase IV engine:** groups consume **`approved`** PRs; reports read instances/steps/FR/audit.  
- **S05:** `procurement_engaged_*` lock columns exist on PR; V must not write engagement or invent RFQ/PO.

---

## 3. Component inventory (repository fact)

Classification key: **REUSABLE** · **EXTEND** · **MISSING** · **CONFLICT / OWNER**

### 3.1 Demand groups

| Item | Path / note | Class |
|---|---|---|
| Tables `purchase_demand_groups` / `_items` | No migration files found under `backend/database/migrations` matching demand | **MISSING** |
| Models / Actions / Controllers / routes | No PHP matches for `DemandGroup` / `purchase-demand-groups` | **MISSING** |
| Frontend pages/routes | No `purchase-demand` / consolidate UI matches | **MISSING** |
| Doc API sketch | `docs/.../API.md` §8 | Spec only |
| Doc data model stub | `DATA_MODEL.md` § Phase V | Spec only |

### 3.2 Purchase Requests (prerequisite)

| Item | Path | Class |
|---|---|---|
| PR header/lines/versions | `backend/app/Modules/SuppliersAndProcurement/Models/PurchaseRequest*.php` · migrations `230002–230005` | **REUSABLE** |
| Status enum incl. `approved` / cancel paths | `Enums/PurchaseRequestStatus.php` · Phase IV actions | **REUSABLE** |
| Optional supplier + OU + season | PR model relations | **REUSABLE** |
| Engagement lock columns | `procurement_engaged_*` on PR | **REUSABLE** (V must respect; S05 writes) |
| Duplicate soft-warn | Phase III submit ack | **REUSABLE** (orthogonal to groups) |

### 3.3 Approval / FR / audit (reports sources)

| Item | Path | Class |
|---|---|---|
| Approval instances/steps | models + migrations `240010–240011` | **REUSABLE** |
| Financial reviews | `PurchaseRequestFinancialReview` · `FinalApprovalGate` | **REUSABLE** / **EXTEND** (follow-up `fr_count=2`) |
| SLA due/escalate timestamps | steps + SLA policies | **REUSABLE** |
| Domain security events | `AuthorizationSecurityEvent` / PR audits | **REUSABLE** |
| Platform audit UI | `frontend/src/modules/audit/` | **REUSABLE** (navigation; not V reports) |
| Dedicated report services | none found | **MISSING** |
| Export infrastructure for PR reports | none found; UI defers export | **MISSING** / out unless Owner expands |

### 3.4 Permissions / tenancy / UI kit

| Item | Path | Class |
|---|---|---|
| Phase III–IV PR permissions in catalog | `PermissionCatalog.php` | **REUSABLE** |
| Phase V permission names | docs only | **MISSING** in catalog |
| Tenant scope / `UsesTenantScope` | Core Tenancy | **REUSABLE** |
| Arabic RTL PR UI patterns | `frontend/src/modules/purchase-requests/` | **EXTEND** (add V pages) |
| Nav slot for control reports | `UI.md` route `/app/purchase-control-reports` | Spec; **MISSING** in router |

### 3.5 Tests / fixtures

| Item | Class |
|---|---|
| Phase III/IV Pest + Vitest suites | **REUSABLE** as regression baseline |
| Phase V TEST_PLAN section / factories | **MISSING** |

---

## 4. Audit & retention

- Material events already audited in Phase III/IV; **130** reporting UIs are Phase V.  
- Detailed retention/protection policy remains **engineering/ops deferred** (ADR-0022 **130**).  
- Phase V must not dump bank ciphertext or secrets into report payloads.

---

## 5. S04 sprint closure readiness (post–Phase IV acceptance)

| Gap | Status |
|---|---|
| Phase IV Owner functional acceptance | **CLOSED** (2026-11-01) |
| Phase V delivery | **OPEN** — design draft now; coding unauthorized |
| Phase II-C acceptance doc alignment | **OPEN** (gap report) |
| Commit/push/merge/deploy | **OPEN** |
| Non-blocking `fr_count=2` | **OPEN** follow-up (does not block V design) |

**S04 is still not sprint-complete** until Phase V is delivered or formally deferred, plus remaining hygiene items above.

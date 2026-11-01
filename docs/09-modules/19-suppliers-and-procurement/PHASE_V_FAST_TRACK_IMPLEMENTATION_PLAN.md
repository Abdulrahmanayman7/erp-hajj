# S04 Phase V — Fast Track Implementation Plan

> **Status:** Fast Track V-WP01…V-WP07 **IMPLEMENTED — ENGINEERING PASS** — **OWNER FINAL FUNCTIONAL ACCEPTANCE GRANTED** (2026-11-01)  
> **Date:** 2026-11-01  
> **Owner decisions:** V-D1…V-D8 **APPROVED** — [S04-PHASE-V-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-V-OWNER-DECISIONS-2026-11-01.md)  
> **Design acceptance:** [S04-PHASE-V-FINAL-DESIGN-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-V-FINAL-DESIGN-ACCEPTANCE-2026-11-01.md)  
> **Functional acceptance:** [S04-PHASE-V-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-V-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md)  
> **Evidence:** [PHASE_V_FINAL_IMPLEMENTATION_AND_ACCEPTANCE_EVIDENCE.md](PHASE_V_FINAL_IMPLEMENTATION_AND_ACCEPTANCE_EVIDENCE.md) · [PHASE_V_MULTIPROCESS_CONCURRENCY_VERIFICATION_EVIDENCE.md](PHASE_V_MULTIPROCESS_CONCURRENCY_VERIFICATION_EVIDENCE.md)  
> **Design:** [PHASE_V_DETAILED_DESIGN.md](PHASE_V_DETAILED_DESIGN.md)  
> **Does not:** authorize deploy · Git release · S04 overall closure · S05

---

## Package map

| WP | Name | Depends on | Stop if |
|---|---|---|---|
| **V-WP01** | Schema + catalog perms + sequences | Design accepted | Unique-active design fails on MariaDB 10.4 |
| **V-WP02** | Demand Group lifecycle Actions | V-WP01 | Transition matrix mismatch V-D3 |
| **V-WP03** | PR link/unlink + cancel unlink + engagement freeze | V-WP02; Phase IV cancel stable | Phase IV cancel regression |
| **V-WP04** | Four report services + CSV export core | V-WP01 perms | Formula/encoding/size gate fails |
| **V-WP05** | HTTP APIs, Policies, audit | V-WP02–04 | Cross-tenant leak |
| **V-WP06** | Arabic RTL UI + CSV download UX | V-WP05 | Approve/RFQ-looking UI creep |
| **V-WP07** | Security, regression, evidence | V-WP01–06 | Any AC-V fail or IV regression |

CSV export (**V-D8=A**) is **in V-WP04** (services) + **V-WP05** (routes) + **V-WP06** (UI buttons) + **V-WP07** (tests).

---

## V-WP01 — Data model & tenant boundaries

| | |
|---|---|
| **Deliverables** | Migrations: `purchase_demand_groups`, `purchase_demand_group_items`, `purchase_demand_group_active_memberships`, number sequence; Models + TenantOwned; add `consolidate_planning` + `view_control_reports` to `PermissionCatalog` (no sensitive IV template grants) |
| **Files** | `database/migrations/2026_11_01_25xxxx_*`; `Models/PurchaseDemandGroup*`; `PermissionCatalog.php` |
| **Tests** | Schema; unique active insert conflict; tenant scope fail-closed |
| **Docs** | DATA_MODEL.md |
| **Gate** | EC-V01 constraints live on `erp_hajj_testing` only after coding auth |
| **Complexity** | M |

---

## V-WP02 — Demand Group lifecycle

| | |
|---|---|
| **Deliverables** | Create/Update/Transition Actions per EC-V03 matrix; domain codes |
| **Files** | `Actions/CreatePurchaseDemandGroup.php`, `Update…`, `TransitionPurchaseDemandGroupStatus.php` |
| **Tests** | Valid/invalid transitions; frozen/closed edit rules |
| **Docs** | BUSINESS_RULES §3.5 |
| **Gate** | AC-V08 |
| **Complexity** | M |

---

## V-WP03 — PR integration, cancel unlink, engagement

| | |
|---|---|
| **Deliverables** | Link/Unlink Actions; integrate unlink into `CancelApprovedPurchaseRequest` **same transaction**; engagement membership lock; multi-process contention tests |
| **Files** | `LinkPurchaseRequestToDemandGroup.php`, `Unlink…`; touch Phase IV cancel Action carefully |
| **APIs** | item POST/DELETE |
| **Tests** | AC-V02–V07; concurrent dual-link; cancel unlink idempotent; no PR status side effects; no engagement writes |
| **Stop** | Any Phase IV cancel/engagement test regression |
| **Docs** | API demand-group items |
| **Complexity** | L |

---

## V-WP04 — Reports + CSV export core

| | |
|---|---|
| **Deliverables** | Services for RPT-V01…V04; CSV builders (BOM, injection prefix, stable headers); row/date caps; export audit helper |
| **Files** | `Support/PurchaseControlReports/*`; CSV encoder util |
| **Perms** | `view_control_reports` |
| **Tests** | Aggregate fixtures; unpriced≠0; export encoding/injection/size 422; audit fired |
| **Gate** | AC-V10–V11 |
| **Complexity** | L |

---

## V-WP05 — APIs, policies, audit

| | |
|---|---|
| **Deliverables** | Controllers, Form Requests, Resources, routes (incl. four `.export.csv`); Policies; security events |
| **Files** | Controllers under SuppliersAndProcurement; `routes/api.php` |
| **Tests** | Feature matrix 401/403/404/422; cross-tenant 404 |
| **Gate** | Contract matches API.md |
| **Complexity** | M |

---

## V-WP06 — Arabic RTL UI

| | |
|---|---|
| **Deliverables** | Demand groups pages; control reports tabs; CSV download buttons; mixed-scope warnings; permission CTAs |
| **Files** | `frontend/src/modules/purchase-requests/` (or `purchase-planning/`); router; `ar.ts` |
| **Tests** | Vitest visibility; tsc |
| **Gate** | AC-V14; no S05 screens |
| **Complexity** | M |

---

## V-WP07 — Security, regression, acceptance evidence

| | |
|---|---|
| **Deliverables** | Full AC-V\* execution; Phase III/IV sample regression; evidence markdown; gap report update |
| **Stop** | Failed security test |
| **Gate** | Ready for Owner **functional** acceptance of Phase V (later act) |
| **Complexity** | M |

---

## Future authorization shape

Owner: (1) **FINAL PHASE V DESIGN ACCEPTANCE**, (2) coding auth for V-WP01–V-WP07 continuous, (3) separate commit/push/merge/deploy.

---

## Non-goals

Implement now · S05 · XLSX · reopen Phase IV acceptance · fix `fr_count=2` in this stream

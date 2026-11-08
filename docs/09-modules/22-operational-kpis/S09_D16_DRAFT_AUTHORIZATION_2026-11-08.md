# S09-D16 — Draft Implementation Authorization (Owner text)

> **STATUS: `NOT AUTHORIZED` · `DRAFT — NOT APPROVED — DO NOT EXECUTE`**  
> **Date received:** 2026-11-08  
> Documenting this draft does **not** approve D16 or authorize coding.  
> Related prior proposal: `S09_D16_IMPLEMENTATION_AUTHORIZATION_PROPOSAL_2026-11-08.md`

---

## 1. Verbatim Owner draft (preserved)

**Owner:** ERP Hajj Project Owner  
**Status:** PENDING OWNER APPROVAL  
**Purpose:** Authorize implementation of the explicitly approved and technically defined S09 scope, with S10 and GPS workstreams included only to the extent that their detailed scope has been verified and authorized.

**Prerequisites:**

1. D01–D15 approvals documented.
2. GPS minimum scope identified and approved.
3. S10 implementation scope verified against official documents.
4. Critical S05/S06/S07 validation risks classified and mitigated or blocked from release.
5. Implementation branch and stash preservation plan verified.
6. Test and acceptance criteria finalized.

**Proposed implementation scope:**

- S09 API and 24 approved KPI calculations.
- Five domain calculators and orchestrator.
- Shared DashboardClock snapshot.
- Tenant and season isolation.
- `operational_kpis.view` permission and approved domain checks.
- Vue operational KPI page.
- Authorized drill-down navigation.
- D14 numeric precision and presentation.
- No KPI-result cache.
- Feature, unit, authorization and performance tests.
- S10 executive dashboard components explicitly defined and approved.
- GPS integration components explicitly defined and approved.

**Proposed safety boundaries:**

- No production database writes.
- No production migrations without separate deployment approval.
- No automatic merge into develop.
- No deployment.
- Preserve existing stash and unrelated work.
- Any additional KPI, formula change or undefined GPS/S10 feature requires explicit Owner-approved scope clarification or CR.
- Stop on critical tenant-isolation or authorization failures.

**Closing clause:** Implementation must not begin until the Owner explicitly approves this D16 authorization with the final workstream scope.

**STATUS: NOT AUTHORIZED.**

---

## 2. Prerequisite audit (historical — first draft assessment)

> **Preserved as historical.** At first write-up, the local register still showed D10–D15 as `PENDING`, so prerequisite #1 was marked **FAIL**.  
> **Correction (same day):** that FAIL was a **documentation synchronization gap**. The Owner had already approved D10–D15 on 2026-11-08; see `S09_D10_D15_OWNER_APPROVAL_2026-11-08.md`.  
> Do **not** rewrite this section as if the original findings never existed — use §6 for the corrected matrix.

| # | Prerequisite | Historical result | Historical evidence |
|---|---|---|---|
| 1 | D01–D15 approvals documented | **FAIL** *(sync gap)* | Register lagged Owner acts |
| 2 | GPS minimum scope identified and approved | **FAIL** | Provider/API/schema open (ADR-0021 gates) |
| 3 | S10 implementation scope verified against official docs | **FAIL** | ADR bullets only; NOT_STARTED |
| 4 | Critical S05/S06/S07 risks classified / mitigated / blocked | **FAIL / OPEN** | D13 then unread as pending |
| 5 | Branch + stash preservation plan verified | **PARTIAL PASS** | Plan only; no execute |
| 6 | Test & acceptance criteria finalized | **PARTIAL** | D14 then unread as pending |

**Historical overall gate:** CLOSED (as assessed then).

---

## 3. Scope conflict analysis (S09 vs S10/GPS)

> Historical verdicts below reflected register lag. **Updated class** column uses post-reconciliation truth.

| Draft line | Binding / official constraint | Historical verdict | Updated class |
|---|---|---|---|
| S09 API + 24 KPIs + calculators + snapshot + RBAC + Vue + no KPI cache + tests | D01–D09 (+D11/D14) | Eligible after D10–D15 | **Documented implementation requirement** — ready pending D16 |
| Authorized drill-down | D11=A approved | Was pending | **Approved + implementable** after D16 |
| D14 precision | D14 approved | Was pending | **Approved + implementable** after D16 |
| “S10 executive dashboard components explicitly defined and approved” | D15=B direction; ADR-0020 bullets; NOT_STARTED | Not verified for coding | **Open technical specification** — not blanket-authorized |
| “GPS integration components explicitly defined and approved” | D10=B direction; ADR-0021 gates; no SoR in code | Not verified for coding | **Open technical specification** — not blanket-authorized |

**Interpretation of Owner draft language (“only to the extent … verified and authorized”):**

D10=B and D15=B are **Owner-approved directions**. Until GPS minimum scope and S10 MVP surface are **technically frozen and explicitly authorized in a D16 stage**, they remain **non-executable**. Prefer D16 Option C (staged).

---

## 4. Required Owner clarifications before any GO

1. Approve or amend **D10–D15** (consolidated sheet).  
2. Confirm whether D16 authorizes **S09-only**, or also opens **S10** and/or **GPS** workstreams.  
3. If S10/GPS included: attach **verified scope packs** (endpoints, permissions, data source, acceptance gates) — do not invent vendors or mock-as-live map.  
4. Close **D13** residual-risk posture for S05/S06/S07.  
5. Freeze **D14** precision.  
6. Issue an **explicit** approval sentence that replaces `NOT AUTHORIZED` (not merely editing this draft).

---

## 5. Safety confirmation

```
S09-D16 = DRAFT_RECEIVED_NOT_AUTHORIZED
NO_APPLICATION_CODE
NO_NEW_PERMISSIONS
NO_MIGRATIONS
NO_DB_WRITES
NO_BRANCH_SWITCH
NO_COMMIT
NO_PUSH
NO_MERGE
NO_PR
NO_DEPLOY
stash@{0} PRESERVED
```

---

## 6. Corrected D16 readiness reassessment (post D10–D15 record)

### 6.1 Corrected prerequisite matrix

| # | Prerequisite | Owner-decision status | Tech-spec completeness | Implementation readiness | Remaining blockers |
|---|---|---|---|---|---|
| 1 | D01–D15 approvals | **PASS** — all `OWNER_APPROVED` | N/A | Decisions frozen | None for approval status |
| 2 | GPS minimum scope | D10=B **approved** | **INCOMPLETE** — no provider/API/schema; ADR-0021 gates open; no lat/lng SoR in code | **NOT ready to code live GPS** | Owner/engineering freeze of minimum GPS slice; Engineering ADR/CR if ADR-0018 conflict |
| 3 | S10 scope verified | D15=B **approved** (direction) | **INCOMPLETE** — ADR-0020 bullets only; widgets/alerts/map/API/perms not frozen | **NOT ready to code full S10** | Owner freeze of S10 MVP widget list + page perm |
| 4 | S05/S06/S07 debt | D13=B **approved** | Register created | Parallel coding OK **after D16**; release claims still gated | `S09_VALIDATION_DEBT_RISK_REGISTER.md`; S06 MySQL/E2E residual for release |
| 5 | Branch + stash plan | N/A | Plan documented | Ready to **propose** branch after D16 | Must not execute until D16 GO |
| 6 | Tests / acceptance | D14 **approved** | Precision matrix frozen; FE/API shapes still design-level | Core S09 testable once coded | Finalize response DTO field names at kickoff |

### 6.2 What is ready vs gated

| Workstream | Owner decision | Tech spec | Ready for D16 coding? |
|---|---|---|---|
| S09 core (24 KPIs, calculators, snapshot, RBAC, Vue, D14, drill-down D11=A, no cache) | Approved D01–D14 | **Sufficient** | **Yes — pending D16 act** |
| GPS live integration | D10=B direction | Open | **No** until minimum scope freeze |
| S10 executive surface | D15=B direction | Open | **No** until widget/API freeze; map further gated on GPS |

### 6.3 D16 authorization options (for Owner)

#### Option A — S09 core first; GPS/S10 separately gated

| | |
|---|---|
| **Scope** | Authorize coding of S09 core only (24 KPIs + D08/D09/D11/D14). GPS/S10 design may continue; **no** GPS/S10 application code under this act |
| **Dependencies** | D01–D15 recorded; branch from agreed base; preserve stash |
| **Risks** | Combined-direction (D15=B) delayed in code; rework if S10 API needs early hooks |
| **Delivery impact** | **Fastest safe** path to a shippable KPI page |
| **Required Owner approvals** | Explicit D16 GO for Option A; later separate GO for GPS and S10 slices |

#### Option B — Combined implementation only after full GPS+S10 specification

| | |
|---|---|
| **Scope** | Single D16 GO covering S09+S10+GPS after all tech specs frozen |
| **Dependencies** | Provider selection; Engineering ADR/CR; S10 widget freeze; ADR-0021 gates plan |
| **Risks** | Long idle; blocks 24 KPI value delivery |
| **Delivery impact** | Slowest to first usable KPI UI |
| **Required Owner approvals** | GPS min scope + S10 MVP spec + D16 GO |

#### Option C — Staged coordinated workstream (recommended)

| | |
|---|---|
| **Scope** | One D16 **coordinated** authorization: Stage-1 = S09 core **now**; Stage-2 = S10 shell / GPS **only after** explicit scope approval documents — **initial GO ≠ blanket** for Stage-2 |
| **Dependencies** | Same as A for Stage-1; Stage-2 packs (`S09_GPS_S10_SCOPE_IMPACT` follow-ups) |
| **Risks** | Process discipline required so Stage-2 is not assumed |
| **Delivery impact** | Fast KPI delivery **and** honors D10=B / D15=B direction |
| **Required Owner approvals** | D16 GO wording that names Stage-1 in-scope and Stage-2 gated; later Stage-2 GO |

**Recommendation:** **Option C** (fastest safe that respects D10=B and D15=B without inventing GPS/S10). If Owner prefers maximum isolation, choose **A**.

### 6.4 Current status

```
S09-D01...D15 = OWNER_APPROVED
S09-D16 = PENDING / NOT AUTHORIZED
RECONCILIATION_COMPLETE
NO_APPLICATION_CODE
```

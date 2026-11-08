# S09 — Owner Decision Register

> **Status:** D01–D15 approved (D05 technically frozen) · **D16 = OWNER_APPROVED (C) — Phase 1 authorized** (S10/GPS Phase 2 gated)  
> **Last Owner act (D16):** 2026-11-08 — `S09_D16_OWNER_APPROVAL_2026-11-08.md`  
> **Prior Owner act (D10–D15):** 2026-11-08 — `S09_D10_D15_OWNER_APPROVAL_2026-11-08.md`  
> **Prior Owner acts (same day):** D01–D09 (+ D05 C1–C3)  
> **Note:** Earlier register rows left D10–D15 as PENDING after the consolidated review — that was a **documentation sync gap**; Owner approvals existed the same day.  
> **Fast-track pack:** consolidated review · D10–D15 approval · D16 draft (historical) · D16 approval (C)  
> **Authorization:** D16 Phase 1 authorizes S09 core implementation only — **not** production deploy, S10, GPS, formula change, or KPI result cache  

---

### S09-D01 — Final KPI list & units

- **Question:** Which catalog IDs ship in S09 v1? What capacity unit (beds/positions/pilgrims)?  
- **Options:** (A) All FEASIBLE only (B) FEASIBLE+PARTIAL with frozen formulas (C) Custom subset  
- **Owner decision (2026-11-08):** Exactly **24** identifiers: T01,T02,T08 · A02,A03 · P01–P08 · J01,J02,J03,J07,J09 · R01–R05,R09  
- **Unit residual (resolved under D02 Q3 = A):** A02 unit = **assignment count** (not physical bed/facility capacity)  
- **Approval:** `OWNER_APPROVED` — 24 identifiers  

### S09-D02 — Formula definitions & statuses

- **Question:** Exact included/excluded statuses and ratio denominators for the 24 IDs.  
- **Options:** (A) Review all detailed formulas before approval ← selected path · (B) Accept catalog sketches · (C) Defer subset  
- **Artifacts:** `S09_D02_DETAILED_FORMULA_REVIEW.md`, `S09_D02_FORMULA_EVIDENCE_MATRIX.md`, `S09_D02_FORMULA_EXAMPLES.md`, `S09_D02_OPEN_QUESTIONS.md`, `S09_D02_OWNER_APPROVAL_2026-11-08.md`  
- **Owner decisions (2026-11-08):** Q17=A, Q1=A, Q2=B, Q3=A, Q4=A, Q5=A, Q6=A, Q7=A, Q8=C, Q9=A, Q10=A, Q11=A, Q13=A, Q12=A, Q14=A, Q15=A, Q16=A, Q18=A  
- **Approval:** `OWNER_APPROVED_WITH_DEFERRED_PRESENTATION_POLICY` — presentation closed by **D14** (`OWNER_APPROVED`, 2026-11-08)  
- **Note:** Approves business semantics; does not certify every edge case is implementation-ready without D03–D08/D04 clarifications; does **not** finalize Q18 presentation.  

| Q | Choice | Binding |
|---|---|---|
| Q1 | A | T01 `planned`+`in_progress` |
| Q2 | B | T08 all non-cancelled breakdown events |
| Q3 | A | A02 assignment count |
| Q4 | A | A02/A03 snapshot now (custom → D04) |
| Q5 | A | A03 `arrived`+`checked_in` |
| Q6 | A | P01/P04 exclude cancelled |
| Q7 | A | P03 `published`+`awarded` |
| Q8 | C | P06 status **and** full line qtys |
| Q9 | A | P07 PO line `quantity_received` SoR |
| Q10 | A | P07 → `purchase_orders.view` |
| Q11 | A | J01/J09 exclude cancelled |
| Q12 | A | J07 AVG `progress_percent` non-cancelled |
| Q13 | A | J09 denom active+completed |
| Q14 | A | R04 open complaint set |
| Q15 | A | R05 `resolved` only |
| Q16 | A | R02 open high/critical |
| Q17 | A | Procurement null `season_id` excluded |
| Q18 | A | Defer precision/N/A to D06/D14 (temp assumptions only) |


### S09-D03 — Included/excluded statuses (domain freezes)

- **Question:** Confirm transport/accommodation/procurement/project/risk enums for each KPI.  
- **Owner decision (2026-11-08):** Freeze **existing** Laravel enum / model / DB status values for all 24 KPIs; **no** new enum values; **no** lifecycle or guard changes; preserve D02 inclusion/exclusion sets, CHECK constraints, SoftDeletes, and approved edge cases  
- **Audit outcome:** 24/24 compatible · 0 formula↔enum conflicts · 0 new statuses · D02 not reopened  
- **Record:** `S09_D03_OWNER_APPROVAL_2026-11-08.md`  
- **Approval:** `OWNER_APPROVED`  
- **Does not authorize:** application implementation (D16 still pending)  

### S09-D04 — Time windows & timezone

- **Question:** Season snapshot vs rolling window; tenant TZ vs UTC day bounds; on-time tolerance minutes.  
- **Owner decisions (2026-11-08):** Q1=A, Q2=A, Q3=A, Q4=A, Q5=A, Q6=A, Q7=C, Q8=A, Q9=C  
- **Binding summary:** Tenant IANA TZ · `DashboardClock::now()` one instant/response · inclusive season calendar dates (row scope still `season_id`) · season-only v1 (no date-range params) · A02/A03 `starts_at <= now < effective_ends_at` · event fields reference-only · no historical reconstruction · closed seasons read-only + Arabic label (not closure snapshot) · no freshness TTL · blank TZ fallback design = `Asia/Riyadh` (align vs current DashboardClock/UTC later)  
- **Record:** `S09_D04_OWNER_APPROVAL_2026-11-08.md`  
- **Approval:** `OWNER_APPROVED`  

| Q | Choice | Binding |
|---|---|---|
| Q1 | A | Tenant IANA timezone for calendar/display |
| Q2 | A | `DashboardClock::now()`; no client clock |
| Q3 | A | Inclusive season dates; v1 filter = `season_id` |
| Q4 | A | Season-only + A02/A03 current interval |
| Q5 | A | Event timestamps documented; not active in v1 |
| Q6 | A | No historical reconstruction |
| Q7 | C | Closed season read-only + Arabic closed label |
| Q8 | A | No freshness/tolerance in v1 |
| Q9 | C | Blank TZ → design fallback `Asia/Riyadh` |

### S09-D05 — Tenant/season scope rules

- **Question:** Behavior with no season; closed seasons; procurement nullable season; API season selection.  
- **Owner decisions (2026-11-08, initial):** Q1=B, Q2=A, Q3=B, Q4=B, Q5=A, Q6=A, Q7=A, Q8=A, Q9=A  
- **Owner clarifications (2026-11-08):** C1=R1, C2=404, C3=yes — see `S09_D05_OWNER_CLARIFICATIONS_2026-11-08.md`  
- **Records:** `S09_D05_OWNER_APPROVAL_2026-11-08.md` · clarifications file above  
- **Approval:** `OWNER_APPROVED_TECHNICALLY_FROZEN`  

| Q / C | Choice | Effective binding |
|---|---|---|
| Q1 | B *(historical)* | **C1=R1:** KPI API requires explicit `season_id`; no server Active fallback; UI may suggest Active |
| Q2 | A | Omit → `422 SEASON_REQUIRED` |
| Q3 | B *(historical)* | **C2=404:** foreign-tenant season → **404** (supersedes 403) |
| Q4 | B | UI suggests Active; explicit confirmation (**C3=yes**) |
| C3 | yes | Never silently select season; send confirmed `season_id` |
| Q5 | A | Closed visible + «مغلق»; `seasons.view` + domain view |
| Q6 | A | Exclude procurement null season (confirms D02-Q17) |
| Q7 | A | Page gate + omit cards without `*.view` (→ D08) |
| Q8 | A | Empty counts → `0`; ratios → D06/D14 |
| Q9 | A | No seasons → «لا مواسم» (not KPI zeros) |

### S09-D06 — Zero-denominator & missing-data UX

- **Question:** N/A vs 0% vs hide; unavailable vs no-data copy; null vs zero; JSON state.  
- **Owner decisions (2026-11-08):** Q1=A, Q2=A, Q3=A, Q4=A, Q5=A, Q6=A, Q7=A, Q8=B, Q9=A, Q10=A  
- **Record:** `S09_D06_OWNER_APPROVAL_2026-11-08.md`  
- **Approval:** `OWNER_APPROVED`  
- **Note:** Q8=B defers precision/rounding to **D14**; closes prior “temp 1 d.p.” assumption as non-binding. Forbidden/card metadata shape → **D08**.  

| Q | Choice | Binding |
|---|---|---|
| Q1 | A | Empty COUNT → 0 |
| Q2 | A | Empty P07 SUM → 0 |
| Q3 | A | Empty J07 AVG → undefined |
| Q4 | A | J09 denom 0 → undefined (not 0%) |
| Q5 | A | `value: null` + `state` |
| Q6 | A | UI «غير متاح» |
| Q7 | A | Exclude null from AVG; keep stored 0 |
| Q8 | B | Precision → D14 |
| Q9 | A | State metadata (placement vs HTTP → D08) |
| Q10 | A | Distinct no-season / empty / zero / undefined / unauthorized |

### S09-D07 — Architecture / KPI calculation services

- **Question (updated):** Calculator architecture, aggregation, clock, DTO, auth boundaries, testing, extensibility.  
- **Owner decisions (2026-11-08):** Q1=B, Q2=A, Q3=A, Q4=A, Q5=A, Q6=A, Q7=A, Q8=A, Q9=A, Q10=A, Q11=A  
- **Record:** `S09_D07_OWNER_APPROVAL_2026-11-08.md`  
- **Approval:** `OWNER_APPROVED`  
- **Note:** External API route shape (extend dashboard vs dedicated `/operational-kpis`) remains alignable with earlier hybrid notes; **calculation** architecture is frozen as five domain calculators + orchestrator. Permission keys → **D08**.  

| Q | Choice | Binding |
|---|---|---|
| Q1 | B | Domain calculators + orchestrator |
| Q2 | A | Five domain calculators |
| Q3 | A | Safe combined aggregates; split when formulas require |
| Q4 | A | One DashboardClock instant / response |
| Q5 | A | Normalized DTO id/value/state |
| Q6 | A | Page + domain auth; no unauthorized calc/serialize |
| Q7 | A | No N+1 in v1 |
| Q8 | A | Per-KPI technical error isolation; never bypass security |
| Q9 | A | Reuse calculators for S09 + future S10 |
| Q10 | A | Feature + unit tests |
| Q11 | A | Register calculators; new KPIs need CR |

### S09-D08 — Permission model

- **Question:** Page gate + domain omit policy for Operational KPIs.  
- **Owner decisions (2026-11-08):** Q1=B, Q2=A, Q3=A, Q4=A, Q5=A, Q6=A, Q7=A, Q8=A, Q9=A, Q10=A, Q11=A  
- **Record:** `S09_D08_OWNER_APPROVAL_2026-11-08.md`  
- **Approval:** `OWNER_APPROVED`  
- **Note:** `operational_kpis.view` was **designed** here and is created in code under D16 Phase 1 (`PermissionCatalog`). Do not substitute `dashboard.view`.  

| Q | Choice | Binding |
|---|---|---|
| Q1 | B | Page = `operational_kpis.view` (future create) |
| Q2 | A | Existing domain `*.view` matrix (finer PR/RFQ/PO and risks/complaints) |
| Q3 | A | Omit unauthorized cards |
| Q4 | A | No forbidden metadata for omitted cards |
| Q5 | A | Auth order: authn→tenant→page→season→domain→calc→serialize |
| Q6 | A | Closed readable if authorized; `seasons.view` for picker |
| Q7 | A | Page only → 200 empty + `no_domain` |
| Q8 | A | Request-scoped effective permissions snapshot |
| Q9 | A | Design safe authz audit events (no implement now) |
| Q10 | A | S10 reuses domain omit; own page perm possible |
| Q11 | A | Roll out permission only in authorized implementation |

### S09-D09 — Caching policy

- **Question:** None vs short TTL vs domain/KPI caches; query budgets; indexes; N+1; observability.  
- **Owner decisions (2026-11-08):** Q1=A, Q2=15, Q3=500ms, Q4=A, Q5=MEASURE_THEN_CR, Q6=CI_ENFORCED, Q7=LIVE, Q8=FUTURE_CACHE_PERMISSION_SAFE, Q9=NON_PRODUCTION_OBSERVABILITY, Q10=PEST_FIRST, Q11=DEFER_S10_CACHE  
- **Record:** `S09_D09_OWNER_APPROVAL_2026-11-08.md`  
- **Approval:** `OWNER_APPROVED`  
- **Note:** 15 queries / 500ms p95 are **provisional** targets — not production baselines.  

| Q | Choice | Binding |
|---|---|---|
| Q1 | A | No KPI result cache in v1 |
| Q2 | 15 | Provisional max DB queries (full auth) |
| Q3 | 500ms | Provisional p95 latency |
| Q4 | A | Safe aggregates; formulas unchanged |
| Q5 | MEASURE_THEN_CR | Indexes only after measure + CR |
| Q6 | CI_ENFORCED | N+1 fails CI |
| Q7 | LIVE | Snapshot-sensitive KPIs live |
| Q8 | FUTURE_CACHE_PERMISSION_SAFE | Future cache must isolate tenant/season/perms |
| Q9 | NON_PRODUCTION_OBSERVABILITY | Instrument non-prod first |
| Q10 | PEST_FIRST | Pest before load tests |
| Q11 | DEFER_S10_CACHE | S10 cache after S09 measured |

### S09-D10 — GPS dependency

- **Question:** Any KPI may use GPS/live location?  
- **Historical recommendation (pre-approval):** No GPS in S09 (consolidated review)  
- **Owner decision (2026-11-08):** **B — Include GPS in S09**  
- **Boundary:** Does **not** invent provider/API/schema; ADR-0021 gates remain  
- **Impact pack:** `S09_GPS_S10_SCOPE_IMPACT_2026-11-08.md`  
- **Record:** `S09_D10_D15_OWNER_APPROVAL_2026-11-08.md`  
- **Approval:** `OWNER_APPROVED`  

### S09-D11 — Drill-down navigation

- **Question:** Deep-links required? Which routes?  
- **Owner decision (2026-11-08):** **A — Authorized drill-down** (allow-listed list routes; permission-safe)  
- **Record:** `S09_D10_D15_OWNER_APPROVAL_2026-11-08.md`  
- **Approval:** `OWNER_APPROVED`  

### S09-D12 — Incomplete source data

- **Question:** Drop BLOCKED KPIs vs show “unavailable”?  
- **Owner decision (2026-11-08):** **A — Preserve D06 states**; no additional forbidden-source UX  
- **Note:** D01 list has no BLOCKED IDs among the 24  
- **Approval:** `OWNER_APPROVED`  

### S09-D13 — Validation debt (S05/S07/S06)

- **Question:** Must S05 OA certificate, S07 OA, S06 MySQL 8 complete before S09 coding?  
- **Options:** (A) Hard gate (B) Parallel with risk accepted (C) Soft warnings only  
- **Owner decision (2026-11-08):** **B — Parallel** with tracked validation debt  
- **Register:** `S09_VALIDATION_DEBT_RISK_REGISTER.md`  
- **Approval:** `OWNER_APPROVED`  

### S09-D14 — Acceptance thresholds & UAT

- **Question:** UAT scenarios and numeric tolerance / precision & rounding?  
- **Owner decision (2026-11-08):** COUNT integers; **P07** ≤4 dp trim trailing zeros; **J07** 1 dp; **J09** 1 dp; server **ROUND_HALF_UP** for display  
- **Matrix:** `S09_D14_PRECISION_UAT_MATRIX.md`  
- **Approval:** `OWNER_APPROVED` — `PRESENTATION_PRECISION_FROZEN`  

### S09-D15 — S09 vs S10 responsibilities

- **Question:** Where do executive rollups / alerts / live map live?  
- **Historical recommendation (pre-approval):** A — S09 engine / S10 executive  
- **Owner decision (2026-11-08):** **B — Combined S09/S10 implementation direction**  
- **Boundary:** Does **not** invent unspecified S10 features; GPS map still gated  
- **Impact pack:** `S09_GPS_S10_SCOPE_IMPACT_2026-11-08.md`  
- **Approval:** `OWNER_APPROVED`  

### S09-D16 — Coding authorization

- **Question:** Authorize S09 (+ gated GPS/S10) implementation?  
- **Proposal pack:** `S09_D16_IMPLEMENTATION_AUTHORIZATION_PROPOSAL_2026-11-08.md`  
- **Owner draft:** `S09_D16_DRAFT_AUTHORIZATION_2026-11-08.md` — historical; superseded by the approval record below  
- **Reassessment:** D01–D15 Owner approvals **PASS**; GPS/S10 remain **tech-spec open**; draft §6 options A/B/C  
- **Owner decision (2026-11-08):** **C — Staged coordinated workstream.** Phase 1 (S09 core, 24 KPIs, D01–D15 bound) **authorized**; Phase 2 (S10 shell / GPS) **gated** behind separate explicit scope approval  
- **Record:** `S09_D16_OWNER_APPROVAL_2026-11-08.md`  
- **Approval:** `OWNER_APPROVED` (C) — Phase 1 authorized  
- **Does not authorize:** production deployment · S10 · GPS · formula changes · KPI result caching · merge without review  

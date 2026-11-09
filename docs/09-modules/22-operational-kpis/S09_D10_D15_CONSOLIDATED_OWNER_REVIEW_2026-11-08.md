# S09 — D10–D15 Consolidated Owner Review (2026-11-08)

> **Status:** Historical design package — recommendations only at authorship  
> **Superseding approval record:** `S09_D10_D15_OWNER_APPROVAL_2026-11-08.md` — D10–D15 are now **`OWNER_APPROVED`**  
> **Does not** authorize D16 implementation  
> Official decision titles from `S09_OWNER_DECISIONS.md`  
> Preserve this file; do not delete historical recommendations that differ from final Owner choices (e.g. D10/D15)

---

## Preservation notice

D01–D09 are Owner-approved and **must not** be reinterpreted by answers below.

---

## Official decision titles (discovered)

| ID | Official title (from Owner Decisions register) | Status |
|---|---|---|
| D10 | GPS dependency | PENDING |
| D11 | Drill-down navigation | PENDING |
| D12 | Incomplete source data | PENDING |
| D13 | Validation debt (S05/S07/S06) | PENDING |
| D14 | Acceptance thresholds & UAT (includes precision/rounding deferred from D02-Q18 / D06-Q8) | PENDING |
| D15 | S09 vs S10 responsibilities | PENDING |
| D16 | Coding authorization | PENDING (separate proposal file) |

No invented renumbering.

---

## D10 — GPS dependency

| Field | Content |
|---|---|
| Scope | May any of the 24 KPIs use GPS / live location? |
| Evidence | D02 non-questions: T01/T02/T08 independent of GPS; ROADMAP S10 for gated map themes |
| Options | **A)** No GPS in S09 · **B)** Allow optional GPS for subset · **C)** Defer entirely to S10 with source gate |
| **Recommendation** | **A / C combined:** **No** GPS inputs for S09 v1; live location deferred to **S10** after source readiness |
| Security | Avoid unverified location SoR; no PII location leakage |
| Performance | Avoid external GPS calls on KPI path |
| UX | No “live map” expectation on S09 page |
| Dependencies | D15 |
| Acceptance | No calculator reads GPS; docs/tests assert independence |
| **Owner Q** | Confirm **No GPS in S09** (recommended)? |

---

## D11 — Drill-down navigation

| Field | Content |
|---|---|
| Scope | Deep-links from KPI cards to domain lists/details? |
| Evidence | ADR-0014 dashboard hrefs pattern; module list routes; D08 omit unauthorized |
| Options | **A)** Allow-listed module list routes only · **B)** No drill-down in v1 · **C)** Full entity deep-links |
| **Recommendation** | **A** — allow-listed filtered list routes (`transportation`, accommodation assignments, POs, projects, risks/complaints) with same domain permission; no unauthorized destinations |
| Security | Links only if card authorized; no ID enumeration beyond user’s scope |
| UX | Arabic RTL cards clickable where safe |
| Dependencies | D08 permissions; FE router |
| Acceptance | Pest/FE tests: no link without permission; closed season still navigable read-only where allowed |
| **Owner Q** | Approve allow-listed list drill-down (**A**)? Any domains without links? |

### Related API/UI surface (also under D11 / presentation)

| Topic | Recommendation (PENDING) |
|---|---|
| HTTP route | Dedicated `GET /api/v1/operational-kpis?season_id=` (season required) — aligns D05/D07; do not overload MVP dashboard payload |
| Page route | `/app/operational-kpis` (or Owner-chosen path) gated by `operational_kpis.view` |
| Season picker | Suggest Active + explicit confirm (D05-C3); closed labeled «مغلق»; `seasons.view` for picker |
| Cards | Group by 5 domains; omit unauthorized; «غير متاح» for undefined; `no_domain` empty page state |
| Loading | Skeleton + retry; single shared snapshot from response meta |

---

## D12 — Incomplete source data

| Field | Content |
|---|---|
| Scope | BLOCKED / missing SoR KPIs behavior |
| Evidence | D01 = 24 with no BLOCKED IDs in approved set; D06 undefined for math empty; catalog historical BLOCKED rows out of scope |
| Options | **A)** N/A — no blocked KPIs in v1 list · **B)** Show unavailable if future gap · **C)** Drop from UI |
| **Recommendation** | **A** for current 24 — treat as fully specified; if a runtime SoR gap appears, surface as technical `error` state (D07) not fake zero, and raise CR |
| Security | Do not invent data |
| Dependencies | D06/D07 |
| Acceptance | No silent zeros for failed SoR |
| **Owner Q** | Confirm no additional “unavailable source” UX beyond D06/D07 for the 24? |

---

## D13 — Validation debt (S05 / S07 / S06)

| Field | Content |
|---|---|
| Scope | Must S05/S07 OA certificates and S06 MySQL 8 validation complete before S09 coding? |
| Evidence | Register options A hard gate / B parallel / C soft warnings; ROADMAP Phase 2 parallel modules |
| Options | **A)** Hard gate · **B)** Parallel with residual risk register · **C)** Soft warnings only |
| **Recommendation** | **B** — allow S09 coding after D16 with explicit residual risks logged (tenant isolation & formula tests still hard gates) |
| Security | Cross-tenant tests remain merge blockers regardless |
| Dependencies | D16 |
| Acceptance | Risk register attached to implementation PR |
| **Owner Q** | Approve **B** parallel with risk register? |

---

## D14 — Acceptance thresholds, UAT & presentation precision

| Field | Content |
|---|---|
| Scope | Closes D02-Q18 / D06-Q8 deferral: rounding, formatting, UAT tolerances |
| Evidence | Counts integer; P07 decimal:4 storage; J07 AVG; J09 ratio; Arabic UI |
| Options (precision) | See matrix below |
| **Recommendation (PENDING)** | Counts as integers; P07 show up to **4** decimal places trimming trailing zeros **or** fixed 4 — Owner pick; J07/J09 display **1** decimal place for rates/averages; round **half-up on server** for display fields; JSON retains sufficient numeric precision; null stays null; client formats locale but does not re-round differently |
| Security | No precision tricks that hide unauthorized data |
| UX | Arabic numerals optional per product norm; «غير متاح» unchanged |
| Dependencies | D06 |
| Acceptance | UAT script with fixtures; exact counts; documented rate tolerance |
| **Owner Qs** | (1) P07 display decimals? (2) J07/J09 display decimals? (3) Server vs client rounding authority? (4) Approve Arabic UAT checklist? |

### D14 precision option table (for Owner)

| Metric type | Option P1 | Option P2 | Option P3 |
|---|---|---|---|
| Counts | Integer only (**rec**) | — | — |
| P07 SUM | Trim trailing zeros, max 4 (**rec**) | Always 4 dp | Integer units only |
| J07 AVG % | 1 dp (**rec**) | 2 dp | Integer |
| J09 rate % | 1 dp (**rec**) | 2 dp | Integer |
| Rounding | Server half-up for `display_value` (**rec**) | Client only | Both must match tests |

**Do not** treat any prior “temp 1 d.p.” sketch as already approved until Owner answers D14.

---

## D15 — S09 vs S10 responsibilities

| Field | Content |
|---|---|
| Scope | Boundary between operational KPI engine and executive/S10 surfaces |
| Evidence | D07-Q9 reuse calculators; D08-Q10 domain omit; D10 GPS→S10; ROADMAP Operational KPIs S09 / later executive |
| Options | **A)** S09 = domain KPI engine + page; S10 = executive composition, alerts, gated map · **B)** S09 includes executive rollups · **C)** Merge S09/S10 |
| **Recommendation** | **A** |
| Security | S10 page permission separate; same domain omit |
| Performance | S10 caching deferred (D09-Q11) |
| Dependencies | D10, D09-Q11, D16 |
| Acceptance | No S10 features in S09 PR |
| **Owner Q** | Confirm **A** boundary? |

---

## Cross-cutting acceptance checklist (design)

- [ ] 24 IDs only  
- [ ] Explicit season_id; 422/404/403/401 contracts  
- [ ] Omit unauthorized; `no_domain`  
- [ ] Empty COUNT/SUM=0; undefined AVG/ratio  
- [ ] One snapshot; live A02/A03  
- [ ] ≤15 queries provisional; no N+1 CI  
- [ ] No KPI result cache  
- [ ] No GPS  
- [ ] Drill-down allow-list (if D11=A)  
- [ ] D14 precision frozen before UAT sign-off  
- [ ] S10 not in S09 scope  

---

## Proposed Owner answer sheet (fill)

| ID | Recommended | Owner answer |
|---|---|---|
| D10 | No GPS in S09 | |
| D11 | Allow-listed list drill-down + dedicated API/page | |
| D12 | No extra blocked UX for current 24 | |
| D13 | Parallel coding with risk register | |
| D14 | Counts int; P07 ≤4dp trim; J07/J09 1dp; server half-up display | |
| D15 | S09 engine; S10 executive | |
| D16 | Separate implementation act | PENDING |

---

## Status

```
HISTORICAL_REVIEW_PACKAGE
SUPERSEDED_BY = S09_D10_D15_OWNER_APPROVAL_2026-11-08.md
S09-D10...D15 = OWNER_APPROVED (see approval record)
S09-D16 = PENDING
NO_APPLICATION_CODE
```

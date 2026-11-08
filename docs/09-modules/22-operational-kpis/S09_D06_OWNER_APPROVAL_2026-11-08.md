# S09-D06 — Owner Approval Record (2026-11-08)

> **Authorization boundary:** DESIGN DOCUMENTATION ONLY.  
> Does **not** authorize application code, migrations, commits, push, merge, PR, or deploy.  
> Does **not** approve S09-D07…D16.  
> **Status:** `OWNER_APPROVED`

---

## Authorization

| Field | Value |
|---|---|
| Date | **2026-11-08** |
| Decision | Null / empty / undefined / zero-denominator presentation semantics |
| Prior | D01–D05 approved (D05 technically frozen via C1–C3) |
| Exact choices | **Q1=A, Q2=A, Q3=A, Q4=A, Q5=A, Q6=A, Q7=A, Q8=B, Q9=A, Q10=A** |

---

## Exact 24 KPI scope (unchanged)

T01, T02, T08 · A02, A03 · P01–P08 · J01, J02, J03, J07, J09 · R01–R05, R09

---

## Binding selections

| Q | Choice | Binding |
|---|---|---|
| Q1 | **A** | Valid authorized season, empty eligible set → **COUNT = 0** (≠ missing season) |
| Q2 | **A** | P07 empty eligible qty set → **SUM = 0**; SoR remains `purchase_order_lines.quantity_received` only |
| Q3 | **A** | J07 empty eligible AVG sample → **undefined** (not numeric 0) |
| Q4 | **A** | J09 denom 0 → **undefined** (not 0%; do not hide solely for zero denom) |
| Q5 | **A** | Undefined → JSON `value: null` + machine-readable `state` (not string `"N/A"`) |
| Q6 | **A** | UI undefined → **«غير متاح»** (optional N/A); visually ≠ numeric 0 |
| Q7 | **A** | AVG: exclude source **null** from sample; stored **0** included; no `COALESCE(null,0)` |
| Q8 | **B** | Precision/rounding/formatting → **D14**; no frozen 1 d.p.; no arbitrary rounding in formulas |
| Q9 | **A** | Machine-readable state metadata in design; placement constraints below |
| Q10 | **A** | Keep distinct: no-season · empty season · numeric zero · undefined avg/ratio · unauthorized |

---

## Empty COUNT / SUM / AVG

| Case | Result |
|---|---|
| COUNT (most KPIs) with valid season, zero eligible rows | `0` |
| P07 SUM empty | `0` |
| J07 AVG empty sample | undefined (`value: null`) |
| Confusion with «لا مواسم» | Forbidden — use distinct state / UX (Q9/Q10 + D05-Q9) |

---

## J09 zero denominator

```
completed / (active + completed)
```

Denomination 0 → undefined. Do **not** return 0%. Do **not** hide the card solely because denom is zero (authorization may still hide for other reasons).

---

## Null versus zero (J07 / averages)

- Exclude null `progress_percent` (if ever present) from AVG sample.  
- Stored numeric `0` is a valid observation.  
- Preserve D02 J07 eligibility: non-cancelled, not soft-deleted, season match.  
- Do not fabricate missing observations.

---

## JSON + UI

| Layer | Rule |
|---|---|
| API | `value: null` + `state` for undefined; never `"N/A"` as numeric stand-in |
| UI | «غير متاح» (± N/A); zero remains a number |

---

## State metadata (Q9) — response-level vs card-level

Candidate states from audit: `ok` · `undefined` · `no_season` · `forbidden`

| Concern | Rule |
|---|---|
| Forbidden KPI | Must **not** leak unauthorized data, counts, or card metadata — omit/deny pattern (D05-Q7); exact shape → **D08** |
| Missing season | Preserve HTTP **`422 SEASON_REQUIRED`** (D05-C1/Q2) — may be response-level, not a card `state` |
| Cross-tenant season | HTTP **`404`** (D05-C2) — non-disclosure; not a KPI card state |
| Card-level `state` | Appropriate for `ok` / `undefined` on authorized cards |
| D08 | Finalizes authorization enforcement and which metadata appears where |

---

## Distinct states (Q10)

1. Tenant without seasons → «لا مواسم» (D05-Q9).  
2. Valid season, no eligible rows → COUNT/SUM **0** where applicable.  
3. Valid numeric zero → show **0**.  
4. Undefined AVG/ratio → `value: null` + «غير متاح».  
5. Unauthorized → omit/deny (no zero fake) — **D08**.

---

## Deferrals

| Topic | Decision |
|---|---|
| Precision / rounding / formatting | **D14** (Q8=B; also closes D02-Q18 presentation side with D14) |
| Full RBAC / forbidden payload shape | **D08** |
| Architecture / caching | **D07 / D09** |
| Implementation | **D16** |

---

## Compatibility with D01–D05

| Prior | Compatible? |
|---|---|
| D01 24 IDs | Yes |
| D02 formulas (incl. J07/J09/P07) | Yes — presentation only |
| D03 enums | Yes |
| D04 snapshot / season scope | Yes |
| D05 C1/C2/C3, Q8 counts=0, Q9 no-season | Yes |

---

## No-code boundary

| Action | Authorized? |
|---|---|
| Record D06 in S09 design docs | **Yes** |
| Application / migrations / commit / push / deploy | **No** |
| Approve D07 | **No** |
| Freeze rounding in formulas | **No** (Q8=B) |

---

## Status lines

```
S09-D06 = OWNER_APPROVED
S09-D07...D16 = PENDING_OWNER_DECISION
S09-D14 = PRESENTATION_PRECISION_PENDING
S09-D08 = RBAC_DETAIL_PENDING
NO_APPLICATION_CODE / NO_MIGRATIONS / NO_COMMIT / NO_PUSH / NO_MERGE / NO_DEPLOY
```

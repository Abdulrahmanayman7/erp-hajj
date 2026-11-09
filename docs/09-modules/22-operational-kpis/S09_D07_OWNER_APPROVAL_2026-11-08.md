# S09-D07 — Owner Approval Record (2026-11-08)

> **Authorization boundary:** DESIGN DOCUMENTATION ONLY.  
> Does **not** authorize application code, migrations, commits, push, merge, PR, or deploy.  
> Does **not** approve S09-D08…D16.  
> **Status:** `OWNER_APPROVED`

---

## Authorization

| Field | Value |
|---|---|
| Date | **2026-11-08** |
| Decision | KPI service / calculator architecture for S09 v1 |
| Prior | D01–D06 approved (D05 technically frozen) |
| Exact choices | **Q1=B, Q2=A, Q3=A, Q4=A, Q5=A, Q6=A, Q7=A, Q8=A, Q9=A, Q10=A, Q11=A** |

---

## Exact 24 KPI scope (unchanged)

T01, T02, T08 · A02, A03 · P01–P08 · J01, J02, J03, J07, J09 · R01–R05, R09

---

## Binding selections

| Q | Choice | Binding |
|---|---|---|
| Q1 | **B** | Domain calculators + central orchestrator (not monolith; not one query-object per KPI) |
| Q2 | **A** | Five domain calculators: Transport · Accommodation · Procurement · Seasonal Projects · Risks & Complaints |
| Q3 | **A** | Combine safe aggregates; separate queries when needed for formula fidelity (esp. P06/P07/P08/J07/J09) |
| Q4 | **A** | One `DashboardClock` instant per response; pass to all calculators |
| Q5 | **A** | Normalized result DTO: KPI id · value|null · machine-readable state (precision → D14) |
| Q6 | **A** | Page-level **and** domain-level auth; never calculate/serialize unauthorized values (detail → **D08**) |
| Q7 | **A** | Prevent N+1 in v1; prefer DB aggregates (no index work now) |
| Q8 | **A** | Isolate unexpected technical KPI failures to that KPI (`error` ≠ `undefined`); never bypass security |
| Q9 | **A** | Same calculation services reusable by S09 page and future S10 (no S10 implementation) |
| Q10 | **A** | Feature tests + unit calculator tests |
| Q11 | **A** | Register domain calculators without expanding v1 scope; new KPI IDs need CR + Owner |

---

## Approved architecture

### Central orchestrator (proposed design role)

Responsibilities:

1. Require authentication / page authorization (exact keys → D08).  
2. Require explicit `season_id` → else `422 SEASON_REQUIRED`.  
3. Resolve season in tenant scope → foreign → `404`.  
4. Capture **one** snapshot instant via `DashboardClock::now()`.  
5. Invoke only **authorized** domain calculators.  
6. Merge normalized DTO results.  
7. Serialize response without unauthorized cards/values.

### Five domain calculators

| Calculator (design) | KPIs | Notes |
|---|---|---|
| Transport | T01, T02, T08 | Status counts; season_id required |
| Accommodation | A02, A03 | Interval vs snapshot instant |
| Procurement | P01–P08 | May need **per-permission** gates inside domain (PR / RFQ / PO view keys differ) — finalize in D08 |
| Seasonal Projects | J01, J02, J03, J07, J09 | SoftDeletes; AVG/ratio undefined rules (D06) |
| Risks & Complaints | R01–R05, R09 | One domain calculator; may separate risk vs complaint **queries** internally; permissions differ (`risks.view` / `complaints.view`) — D08 |

Names above are **design labels**, not existing classes.

### Query aggregation policy (Q3=A)

- Prefer `GROUP BY status` / conditional aggregates when semantics identical to approved formulas.  
- Keep dedicated queries for: P06 (status ∧ line fulfillment), P07 SUM, P08 short lines, J07 AVG, J09 ratio.  
- Formula correctness > query count.

### Snapshot (Q4=A)

Reuse existing `App\Modules\Dashboard\Support\DashboardClock` API (`now()`, `timezone()`, …). Preserve D04 TZ / Asia/Riyadh blank-fallback **design** policy (code alignment later).

### Result DTO (Q5=A)

Minimum conceptual fields: `id`, `value` (number|null), `state` (`ok` | `undefined` | `error` | …).  
Card-level `forbidden` / omit → **D08**.  
Rounding → **D14**.

---

## Error isolation & security (Q8=A)

| Case | Handling |
|---|---|
| Math undefined (empty AVG, denom 0) | `state=undefined`, `value=null` (D06) — **successful** calc |
| Unexpected technical failure on authorized KPI | May isolate as `state=error` without stack/SQL/tenant leakage |
| Missing season / foreign season / page auth | **Request-level** 422 / 404 / 403 — not KPI error cards |
| TenantScope / auth / security failures | **Must not** be swallowed as benign KPI `error` |

**Open design detail (does not block D07 freeze):** exact `error` payload fields and whether partial HTTP 200 with mixed ok/error cards is allowed — confirm under D08/D11 if needed.

---

## Reuse S09 ↔ S10 (Q9=A)

Calculators + orchestrator core are the SoR for operational KPI numbers. Presentation endpoints may differ. **Do not implement S10.**

---

## Testing (Q10=A)

| Layer | Coverage |
|---|---|
| Feature | Authn/z, tenant isolation, season_id, contracts, omit unauthorized |
| Unit | Formulas, empty sets, null vs zero, denom 0, error isolation |

Follow Pest conventions (`DashboardTest` pattern).

---

## Extensibility (Q11=A)

Register additional **domain** calculators only via CR + Owner for **new KPI IDs**. v1 remains exactly 24.

---

## Dependencies

| Topic | Decision |
|---|---|
| Exact permissions / omit vs forbidden | **D08** |
| Caching | **D09** |
| GPS | **D10** |
| Drill-down / page UX | **D11** |
| Precision | **D14** |
| Implementation | **D16** |

---

## Compatibility with D01–D06

Compatible: 24 IDs; formulas untouched; enums; clock/season; C1–C3; empty/undefined D06; no unauthorized serialize (Q6).

---

## No-code boundary

| Action | Authorized? |
|---|---|
| Record D07 in S09 design docs | **Yes** |
| Application / migrations / commit / push / deploy | **No** |
| Approve D08 | **No** |
| Implement calculators | **No** |

---

## Status lines

```
S09-D07 = OWNER_APPROVED
S09-D08...D16 = PENDING_OWNER_DECISION
NO_APPLICATION_CODE / NO_MIGRATIONS / NO_COMMIT / NO_PUSH / NO_MERGE / NO_DEPLOY
```

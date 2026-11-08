# S09-D08 — Owner Approval Record (2026-11-08)

> **Authorization boundary:** DESIGN DOCUMENTATION ONLY.  
> Does **not** authorize application code, migrations, commits, push, merge, PR, or deploy.  
> Does **not** create `operational_kpis.view` in PermissionCatalog/seeders now.  
> Does **not** approve S09-D09…D16.  
> **Status:** `OWNER_APPROVED`

---

## Authorization

| Field | Value |
|---|---|
| Date | **2026-11-08** |
| Decision | RBAC & authorization for S09 Operational KPIs |
| Prior | D01–D07 approved (D05 technically frozen) |
| Exact choices | **Q1=B, Q2=A, Q3=A, Q4=A, Q5=A, Q6=A, Q7=A, Q8=A, Q9=A, Q10=A, Q11=A** |

---

## Exact 24 KPI scope (unchanged)

T01, T02, T08 · A02, A03 · P01–P08 · J01, J02, J03, J07, J09 · R01–R05, R09

---

## Binding selections

| Q | Choice | Binding |
|---|---|---|
| Q1 | **B** | Page permission = **`operational_kpis.view`** (does **not** exist yet; create only in authorized implementation / D16) |
| Q2 | **A** | Domain keys: existing `*.view` mapping below; auth **before** calc/serialize |
| Q3 | **A** | **Omit** unauthorized KPI cards entirely |
| Q4 | **A** | No forbidden metadata / IDs / `state:forbidden` for omitted cards |
| Q5 | **A** | Auth order: authn → tenant → page → season_id → season resolve → domain perms → calc → serialize |
| Q6 | **A** | Closed season: authorized domain cards readable; `seasons.view` for picker/detail — not auto-required for every KPI calc |
| Q7 | **A** | Page OK + no domains → **200** empty collection + machine-readable **`no_domain`** |
| Q8 | **A** | Effective-permissions **snapshot for request**; reevaluate next request; no persistent permission cache assumption |
| Q9 | **A** | Design safe security audit events for page/season denials (no implement now) |
| Q10 | **A** | S10 reuses domain omit + `*.view`; may have own page permission; no S10 now |
| Q11 | **A** | Create/assign `operational_kpis.view` only in future authorized implementation (D16 gate) |

**Do not** silently substitute `dashboard.view` for the S09 page gate.

---

## Complete 24-KPI domain permission matrix

| KPI IDs | Required domain permission |
|---|---|
| T01, T02, T08 | `transportation.view` |
| A02, A03 | `accommodation.view` |
| P01, P02 | `purchase_requests.view` |
| P03 | `rfq.view` |
| P04, P05, P06, P07, P08 | `purchase_orders.view` |
| J01, J02, J03, J07, J09 | `season_projects.view` |
| R01, R02, R03 | `risks.view` |
| R04, R05, R09 | `complaints.view` |

Five domain calculators (D07) must honor **finer** permission boundaries inside Procurement and Risks & Complaints (check each KPI’s key before calculating that KPI).

---

## Omit & metadata policies

| Rule | Binding |
|---|---|
| Unauthorized card | **Omitted** — no value, no zero placeholder |
| Forbidden card state | **Not used** for omitted cards |
| Authorized D06 states | `ok` / `undefined` / `error` (technical) remain valid on **authorized** results |
| `no_domain` | Response-level when page allowed but zero domain KPIs authorized |

---

## Authorization order (Q5=A)

1. Authentication → **401** if missing  
2. Active tenant context → lifecycle denials per platform  
3. Page permission `operational_kpis.view` → **403** if missing  
4. Required `season_id` → **422 `SEASON_REQUIRED`**  
5. Tenant-scoped season resolve → foreign/unknown **404** (indistinguishable)  
6. Domain permissions (per KPI / sub-domain)  
7. Capture **one** D04/D07 `DashboardClock` snapshot; pass to authorized calculators  
8. Safe serialization (omit unauthorized)

---

## Closed season & picker (Q6=A)

- Authorized domain KPI cards may be read for `closed` seasons (label «مغلق» per D04/D05).  
- `seasons.view` required for season **picker** / season-detail display.  
- Do **not** automatically require `seasons.view` solely because season is closed for every KPI calculation.  
- No writes.

---

## Permission snapshot (Q8=A)

Use consistent effective permissions for the request duration. Next request reevaluates. Do not introduce persistent permission caching as part of D08.

---

## Security audit events (Q9=A) — design only

Plan events for relevant **page-level** and **season** authorization denials. Avoid: SQL, stacks, unauthorized KPI values, unnecessary tenant/season disclosure. **No logging implementation now.**

---

## S10 reuse (Q10=A)

Same domain `*.view` + omit policy. S10 may define its own page permission. No S10 implementation; no S09 scope expansion.

---

## Future permission rollout (Q11=A)

| Step | When |
|---|---|
| Add `operational_kpis.view` to PermissionCatalog | Authorized implementation (after D16) |
| Seed/assign to appropriate roles | Same phase; least privilege |
| Gate + policy / `authorize` | Same phase |
| Tests: 401/403/omit/no_domain/404/422 | Same phase |

**D16** remains the implementation authorization gate. Document only — do not execute.

---

## Dependencies

| Topic | Decision |
|---|---|
| Performance / caching | **D09** |
| GPS | **D10** |
| UI / drill-down | **D11** |
| Precision | **D14** |
| Coding | **D16** |

---

## Compatibility with D01–D07

Compatible: 24 IDs; formulas; season C1–C3; D06 omit/no leak; D07 page+domain auth + calculators.

---

## No-code / no-permission boundary

| Action | Authorized? |
|---|---|
| Record D08 in S09 design docs | **Yes** |
| Add `operational_kpis.view` to catalog/seeders/code | **No** |
| Application / migrations / commit / push / deploy | **No** |
| Approve D09 | **No** |

---

## Status lines

```
S09-D08 = OWNER_APPROVED
S09-D09...D16 = PENDING_OWNER_DECISION
NO_APPLICATION_CODE / NO_NEW_PERMISSIONS / NO_MIGRATIONS / NO_COMMIT / NO_PUSH / NO_MERGE / NO_DEPLOY
```

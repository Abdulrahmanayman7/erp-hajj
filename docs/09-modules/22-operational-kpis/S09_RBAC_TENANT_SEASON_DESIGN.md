# S09 — RBAC, Tenant & Season Design

> **Status:** D05–D08 Owner-approved · D09+ pending  
> **Date:** 2026-11-08  
> **Record:** `S09_D08_OWNER_APPROVAL_2026-11-08.md`  
> **Not:** implementation · permission catalog mutation · D16  

---

## Tenant isolation

| Rule | Status |
|---|---|
| Resolution | `TenantContext` only |
| Cross-tenant season | **404** (D05-C2) |
| Client `tenant_id` | Never authorization input |

---

## Season (S09 KPI)

| Rule | Status |
|---|---|
| Explicit `season_id` | Required (D05-C1) |
| Missing | `422 SEASON_REQUIRED` |
| UI confirm | C3=yes |
| Closed | Authorized domain cards readable; picker uses `seasons.view` (D08-Q6) |
| Procurement null season | Excluded (D02-Q17 / D05-Q6) |

---

## Page & domain authorization (D08 OWNER_APPROVED)

| Layer | Rule |
|---|---|
| Page | **`operational_kpis.view`** — **not in catalog yet**; create only in authorized implementation |
| Domain | Existing `*.view` per KPI matrix in D08 approval |
| Unauthorized cards | **Omit** — no zeros, no `forbidden` metadata |
| Page without domains | **200** + empty KPIs + `no_domain` |
| Order | authn → tenant → page → season_id → season resolve → domain → snapshot → calc → serialize |
| Permissions snapshot | Request-scoped effective permissions (D08-Q8) |

### Domain matrix (summary)

| Domain permission | KPIs |
|---|---|
| `transportation.view` | T01, T02, T08 |
| `accommodation.view` | A02, A03 |
| `purchase_requests.view` | P01, P02 |
| `rfq.view` | P03 |
| `purchase_orders.view` | P04–P08 |
| `season_projects.view` | J01–J03, J07, J09 |
| `risks.view` | R01–R03 |
| `complaints.view` | R04, R05, R09 |

---

## Empty / undefined (D06)

COUNT/SUM empty → 0; AVG empty / J09 denom0 → undefined; precision → D14.

---

## Explicit non-claims

- Do not add `operational_kpis.view` to code now.  
- Do not substitute `dashboard.view` for S09 page gate.  
- Do not implement S10.  

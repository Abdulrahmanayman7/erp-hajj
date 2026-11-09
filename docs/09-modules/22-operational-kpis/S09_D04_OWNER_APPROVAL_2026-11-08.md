# S09-D04 — Owner Approval Record (2026-11-08)

> **Authorization boundary:** DESIGN DOCUMENTATION ONLY.  
> Does **not** authorize application code, migrations, commits, push, merge, PR, or deploy.  
> Does **not** approve S09-D05…D16.  
> Does **not** authorize S09 implementation.

---

## Authorization

| Field | Value |
|---|---|
| Date | **2026-11-08** |
| Decision | Time windows, timezone & snapshot semantics for S09 v1 |
| Prior | D01–D03 `OWNER_APPROVED` (D02 with deferred presentation) |
| Result | `S09-D04 = OWNER_APPROVED` |

Exact Owner choices: **Q1=A, Q2=A, Q3=A, Q4=A, Q5=A, Q6=A, Q7=C, Q8=A, Q9=C**

---

## Exact 24 KPI scope (unchanged)

T01, T02, T08 · A02, A03 · P01–P08 · J01, J02, J03, J07, J09 · R01–R05, R09

---

## Binding decisions

### D04-Q1=A — Official timezone

| Aspect | Rule |
|---|---|
| Business | Interpret KPI calendar / local display using the tenant’s IANA timezone |
| Storage | Keep application UTC storage conventions (`config/app.php` timezone = `UTC`) |
| Evidence | `tenants.timezone` (default `Asia/Riyadh`); settings validation `timezone:all` / IANA list |

### D04-Q2=A — Snapshot clock

| Aspect | Rule |
|---|---|
| Source | Server-side **`DashboardClock::now()`** |
| API shape (existing) | `timezone(): string`, `now(): CarbonInterface`, `today()`, `todayDate()`, `generatedAtIso()` — `DashboardClock.php` |
| Consistency | Capture **one** instant per KPI response; reuse across all cards in that response |
| Forbidden | Client-supplied authoritative snapshot timestamp |

### D04-Q3=A — Season date boundaries

| Aspect | Rule |
|---|---|
| Calendar | `operational_starts_on` / `operational_ends_on` = **inclusive** local calendar dates (tenant TZ) |
| Future datetime equiv. | Half-open `[local start-of-first-day, local start-of-day-after-last-day)` |
| v1 row scope | Still **`season_id = :selected` only** — do **not** add a date filter to every KPI merely because calendar bounds exist |

### D04-Q4=A — S09 v1 reporting scope

| Aspect | Rule |
|---|---|
| Scope | Selected season only — **no** optional date-range query params in v1 |
| A02/A03 | Additionally: `starts_at <= snapshot_instant AND snapshot_instant < effective_ends_at` |
| Labeling | Current-state KPIs are **not** historical snapshots |

### D04-Q5=A — Event timestamps (reference only)

Document for future; **do not activate** event-date filtering in v1:

| KPI | Field (where present) |
|---|---|
| T02 | `completed_at` |
| T08 | `occurred_at` |
| P03 | `published_at` |
| P05 | `issued_at` |
| R05 | `resolved_at` |

Do **not** invent missing fields (`purchase_requests.approved_at`, `operational_risks.closed_at`).

### D04-Q6=A — Historical reconstruction

Forbidden: reconstruct past KPI states without recorded history; approximate via `updated_at`; invent past progress / PO quantities / risk status.

### D04-Q7=C — Closed seasons

| Aspect | Rule |
|---|---|
| Access | Authorized **read-only** KPI view for `SeasonStatus::Closed` |
| UI | Clear Arabic label that the season is **closed** |
| Semantics | Values computed from **available data now** — **not** an immutable snapshot frozen at closure |
| Forbidden | Invent `archived` season status |
| Writes | Preserve existing `SeasonGuard` write restrictions |

### D04-Q8=A — Freshness / tolerance

No custom KPI freshness TTL; no lateness/SLA thresholds in S09 v1. Caching remains **D09** (not approved here).

### D04-Q9=C — Missing / blank tenant timezone

| Aspect | Rule |
|---|---|
| S09 design fallback | Explicit **`Asia/Riyadh`** when tenant timezone missing or blank |
| App storage default | Remains UTC in `config/app.php` |
| **Alignment requirement** | Existing `DashboardClock::timezone()` uses `$tenant->timezone ?: (string) config('app.timezone', 'Asia/Riyadh')` — with current config this yields **`UTC`** for blank timezone, **not** Asia/Riyadh. Future S09 KPI implementation must apply the Owner-approved fallback (or align DashboardClock via separate authorized change). **No code change authorized now.** |
| Invalid nonblank TZ | Separate technical edge case — inspect validation/clock; do **not** broaden this blank-fallback rule |

---

## Technical evidence summary

| Topic | Path |
|---|---|
| App TZ UTC | `backend/config/app.php` L80 |
| Tenant TZ column | `2026_08_06_100000_create_tenants_table.php`; `Tenant::$fillable` |
| DashboardClock | `backend/app/Modules/Dashboard/Support/DashboardClock.php` L15–24 |
| Season dates / status | `Season` model; `SeasonStatus` draft/active/closed |
| Closed = read-only writes blocked | `SeasonGuard.php` L12–21 |
| A02/A03 interval family | `AccommodationCapacityGuard` half-open stays |

---

## Dependencies (not approved by D04)

| Decision | Dependency |
|---|---|
| Mandatory season API / null UX / cross-tenant season | **D05** |
| Zero-denom / N/A / precision | **D06 / D14** (Q18) |
| Full KPI permission architecture | **D08** |
| Caching | **D09** |
| Implementation | **D16** |

---

## Consistency with D01–D03

- Does not change the 24 IDs (D01).  
- Does not change status inclusion sets or formulas (D02/D03).  
- Refines D02 Q4=A (“now”) with explicit clock + interval predicate.  
- Refines closed-season policy as Q7=C (read + Arabic label; not snapshot-at-close).

---

## No-code authorization boundary

| Action | Authorized? |
|---|---|
| Record D04 in S09 design docs | **Yes** |
| Application / migrations / commit / push / deploy | **No** |
| Align DashboardClock fallback in code | **No** (documented requirement only) |
| Approve D05 | **No** |

---

## Status lines

```
S09-D01 = OWNER_APPROVED
S09-D02 = OWNER_APPROVED_WITH_DEFERRED_PRESENTATION_POLICY
S09-D03 = OWNER_APPROVED
S09-D04 = OWNER_APPROVED
S09-D05...D16 = PENDING_OWNER_DECISION
NO_APPLICATION_CODE / NO_MIGRATIONS / NO_COMMIT / NO_PUSH / NO_MERGE / NO_DEPLOY
```

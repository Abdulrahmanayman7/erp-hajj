# S09-D05 — Owner Approval Record (2026-11-08)

> **Authorization boundary:** DESIGN DOCUMENTATION ONLY.  
> Does **not** authorize application code, migrations, commits, push, merge, PR, or deploy.  
> Does **not** approve S09-D06…D16.  
> **Status:** `OWNER_APPROVED_TECHNICALLY_FROZEN`  
> **Binding clarifications (same day):** `S09_D05_OWNER_CLARIFICATIONS_2026-11-08.md` — **C1=R1 · C2=404 · C3=yes**

### Historical vs binding (do not erase history)

| Item | Historical selection | Later binding clarification |
|---|---|---|
| Q1=B | Optional API `season_id` + Active fallback (as first written) | **C1=R1:** KPI API requires explicit `season_id`; no server Active fallback; UI may suggest Active only |
| Q3=B | Foreign season → 403 (as first written) | **C2=404:** Foreign season → **404**; supersedes 403; ADR preserved |
| Q4=B | UI suggest + confirm | **C3=yes:** Confirms explicit confirmation + send confirmed id; never silent UI selection |

Initial Q1–Q9 table below is **preserved as historical**. Effective S09 KPI contract = historical Q2/Q4–Q9 + clarifications C1–C3.

---

## Authorization

| Field | Value |
|---|---|
| Date | **2026-11-08** |
| Decision | Season scope, tenant isolation & closed-season access for S09 KPIs |
| Prior | D01–D04 `OWNER_APPROVED` (D02 deferred presentation) |
| Exact choices (initial) | **Q1=B, Q2=A, Q3=B, Q4=B, Q5=A, Q6=A, Q7=A, Q8=A, Q9=A** |
| Clarifications | **C1=R1, C2=404, C3=yes** |

---

## Exact 24 KPI scope (unchanged)

T01, T02, T08 · A02, A03 · P01–P08 · J01, J02, J03, J07, J09 · R01–R05, R09

---

## Binding selections (recorded exactly)

| Q | Choice | Business semantics (as selected) |
|---|---|---|
| Q1 | **B** | *(historical)* Optional API `season_id` + Active fallback — **clarified by C1=R1** (API must be explicit; UI suggestion only) |
| Q2 | **A** | When `season_id` is omitted → **`422 SEASON_REQUIRED`** *(binding; reinforced by C1)* |
| Q3 | **B** | *(historical)* Foreign season → 403 — **superseded by C2=404** |
| Q4 | **B** | UI suggests active season; requires **explicit user confirmation** *(binding; reinforced by C3)* |
| Q5 | **A** | Closed seasons visible with Arabic «مغلق»; need `seasons.view` + domain `*.view` |
| Q6 | **A** | Exclude procurement `season_id` null unconditionally; **no** join inference (**confirms D02-Q17=A**) |
| Q7 | **A** | Page-level gate + hide/deny cards lacking domain `*.view` (detail → **D08**) |
| Q8 | **A** | Empty eligible **count** datasets → numeric **0**; ratios/undefined → **D06/D14** |
| Q9 | **A** | Tenant with no seasons → explicit «لا مواسم» — not misleading KPI zeros |

---

## Existing implementation evidence

| Topic | Evidence |
|---|---|
| Optional id → Active fallback | `SeasonContextResolver::resolve` L27–34: null/empty → Active; else missing Active → `SEASON_CONTEXT_MISSING` |
| Explicit vs implicit flag | Resolver returns `[Season, bool $explicit]` L20–22, L34 vs L47 |
| Cross-tenant season | Tenant-scoped `Season::query()->whereKey` → null → `ModelNotFoundException` → **404** (resolver comment L12–14; `SeasonContextTest` “returns 404…”) |
| Platform rule | `docs/02-architecture/MULTI_TENANCY.md`: cross-tenant lookups **404, never 403** |
| Closed = read-only writes | `SeasonGuard` + context tests after close |
| Dashboard omit unauthorized | `GetDashboard.php` gates sections on `*.view`; unauthorized domains omitted |
| KPI integer floor | `DashboardKpi::make` uses `max(0, $value)` — counts only; no N/A pattern |

---

## Contradictions — resolution record

> Historical issue text retained for audit trail. **Owner resolved 2026-11-08** via C1–C3.

### D05-ISSUE-01 — Q1=B versus Q2=A → **RESOLVED (C1=R1)**

KPI API requires explicit `season_id`; omit → `422 SEASON_REQUIRED`; no server Active fallback. UI may suggest Active only (with C3 confirmation). Domain `SeasonContextResolver` Active-fallback is **unchanged** and **out of scope** for S09 KPI API.

### D05-ISSUE-02 — Q3=B (403) versus 404 → **RESOLVED (C2=404)**

Foreign-tenant season → **404**. Historical Q3=B superseded. ADR + existing tests preserved.

### D05-ISSUE-03 — Q4=B versus API fallback → **RESOLVED (C3=yes + C1=R1)**

UI suggests Active → user confirms → sends confirmed `season_id`. Never silent selection.

---

## Security implications summary

| Topic | Note |
|---|---|
| TenantScope | Remains fail-closed; `tenant_id` never from client |
| Cross-tenant season | **404** (C2) — ADR aligned |
| Q6=A | Prevents null-season leakage via joins |
| Q7=A | Matches ADR-0014 omit pattern; D08 freezes keys |
| Q9=A | Avoids fake operational zeros when no seasons |

---

## Dependencies

| Item | Decision |
|---|---|
| Empty counts = 0 | Q8=A settled for **counts** |
| Ratios / N/A / JSON undefined | **D06 / D14** (D02-Q18) |
| Full permission catalog / page gate keys | **D08** |
| Implementation | **D16** |

---

## Compatibility verdict

| Area | Verdict |
|---|---|
| D01 scope | Compatible |
| D02 formulas / Q17 | Compatible (Q6 confirms) |
| D03 enums | Compatible |
| D04 closed read + season-only row scope | Compatible (Q5 aligns D04-Q7=C label) |
| ISSUE-01 / 02 / 03 | **Resolved** by C1–C3 |
| ADR cross-tenant 404 | Compatible (C2) |
| Technically frozen? | **Yes** |

---

## No-code authorization boundary

| Action | Authorized? |
|---|---|
| Record D05 + clarifications in design docs | **Yes** |
| Change Q1–Q9 / C1–C3 without Owner | **No** |
| Application / migrations / commit / push / deploy | **No** |
| Approve D06 | **No** |

---

## Status lines

```
S09-D05 = OWNER_APPROVED_TECHNICALLY_FROZEN
S09-D06...D16 = PENDING_OWNER_DECISION
NO_APPLICATION_CODE / NO_MIGRATIONS / NO_COMMIT / NO_PUSH / NO_MERGE / NO_DEPLOY
```

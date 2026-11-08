# S09 — Discovery Audit (Dashboard + Domains)

> **Status:** `S09_PART0_DESIGN_IN_PROGRESS`  
> **Date:** 2026-11-08  
> **Evidence baseline:** `origin/develop` @ `3a18b16`  
> **Method:** Read-only code/docs inspection

---

## 1. Repository baseline (Part 0)

| Item | Value |
|---|---|
| Remote | `Abdulrahmanayman7/erp-hajj` |
| Expected integrated tip | `origin/develop` = `3a18b16` (Merge PR #46) |
| Post-merge CI | [42444820257](https://github.com/Abdulrahmanayman7/erp-hajj/actions/runs/42444820257) — **success** (Laravel, MySQL 8, Frontend) |
| Local branch during Part 0 | `feature/s08-seasonal-templates` @ `d1dc512` (behind develop by merge commit) |
| Branch switch / pull | **Not performed** (Owner rule) |
| Stash | `stash@{0}: local-package-lock-before-s07-sync` preserved |

Authoritative product text for S09: ADR-0020 §S09; matrix row **Operational KPIs = NOT_STARTED (~5% MVP dashboard only)**.

---

## 2. Existing Dashboard (EXISTING)

### Endpoint

`GET /api/v1/dashboard` — `DashboardController::show` → `GetDashboard::execute`  
Middleware: `auth:sanctum`, `user.active`, `tenant.active` + `ResolveTenantContext`.

### Current KPI keys (18) — MVP governance modules only

`tasks_*`, `contracts_*`, `meetings_*`, `decisions_*`, `inventory_*`, `assets_*`, `custodies_*`  
Builders under `backend/app/Modules/Dashboard/Support/*DashboardMetrics.php`.

### Findings vs S09 needs

| Question | Finding |
|---|---|
| Tenant-scoped? | **Yes** — `TenantContext::require()` + `UsesTenantScope` |
| Season-scoped? | **No** — no season dimension in Dashboard module |
| Module permissions? | **Yes** — omit entire section without `*.view` |
| Cached? | **No** server cache (ADR-0014 §7) |
| Unauthorized as zero? | **No** — keys **omitted**; zeros only when authorized |
| Queries modules user cannot view? | **No** (gated in `GetDashboard`) |
| Reusable for S09? | Orchestration pattern, `DashboardKpi`, sparse FE types, Attention builder |
| Would violate ADR-0014? | Role presets; unauthorized zeros; cross-UOM sums; Dashboard mutations; parallel unauth dashboard |

### Existing vs proposed

| Aspect | EXISTING MVP Dashboard | PROPOSED S09 |
|---|---|---|
| Domains | Tasks/Contracts/Meetings/Decisions/Inventory/Assets | Transport / Accommodation / Procurement / Projects / Risks-Complaints |
| Season | None | Required (selected season; validate tenant ownership) |
| KPI nature | Admin operational counts | Domain ops rates/capacity/status with explicit formulas |
| GPS | N/A | Must not invent GPS-derived KPIs (ADR-0021 gate) |

**Rule:** An existing MVP count is **not** automatically a valid Phase 2 KPI.

Tests: Pest `DashboardTest` (11); Vitest dashboard display/api (~15).

---

## 3. Domain readiness snapshot

| Domain | Module path | Season in SoR | Verdict for S09 Part 0 |
|---|---|---|---|
| Transport | `Modules/Transportation` | Yes (`season_id` on movements/fleet/…) | Counts FEASIBLE; delay/on-time PARTIAL (tolerance TBD); no GPS |
| Accommodation | `Modules/Accommodation` | Yes (season facilities/units/assignments) | Allocation vs occupancy distinguishable; readiness BLOCKED |
| Procurement | `Modules/SuppliersAndProcurement` | Nullable `season_id` on PR/RFQ/PO | Counts FEASIBLE; PO “approved” = issued; delivery SLA BLOCKED without formula |
| Seasonal Projects | `Modules/SeasonProjects` | Required | Status counts FEASIBLE; milestones / planned progress BLOCKED |
| Risks & Complaints | `Modules/RisksAndComplaints` | Required | Open/severity/mitigated FEASIBLE; complaint overdue BLOCKED (no SLA) |

---

## 4. Binding ADR notes (do not rewrite)

| ADR | Role for S09 |
|---|---|
| ADR-0020 | S09 product membership |
| ADR-0014 | Permission-aware aggregation; omit unauthorized |
| ADR-0017 | Season bounded context; accommodation capacity rules |
| ADR-0018 | Transport statuses/timestamps; no GPS in schema |
| ADR-0019 | Still PROPOSED / NOT ACCEPTED — do not treat as accepted accommodation engineering ADR |
| ADR-0021 | GPS in Phase 2 product scope but gated; S09 must not claim live GPS KPIs |

---

## 5. Residual debt impacting S09 (not blocking Part 0 docs)

- S05 Owner final acceptance certificate not located.  
- S07 Owner final acceptance not assumed from merge.  
- S06 MySQL 8 deferred / browser E2E not run.  
- Living ROADMAP/MATRIX may still say S08 NOT_MERGED (stale vs `3a18b16`).

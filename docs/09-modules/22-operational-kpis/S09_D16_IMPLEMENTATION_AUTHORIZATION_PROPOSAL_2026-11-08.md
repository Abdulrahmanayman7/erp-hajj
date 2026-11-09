# S09-D16 — Implementation Authorization Proposal

> **Status:** `PENDING_OWNER_DECISION` — **NOT** approved by documenting this file.  
> Fast-track design completion ≠ coding authorization.  
> Date prepared: 2026-11-08  
> **Owner draft (same day):** `S09_D16_DRAFT_AUTHORIZATION_2026-11-08.md` — still **`NOT AUTHORIZED` / DO NOT EXECUTE**.  
> **D10–D15:** now recorded `OWNER_APPROVED` (D10=B, D15=B). GPS/S10 remain **tech-spec gated**. See draft §6 options A/B/C; recommendation **C**.

---

## 1. Exact implementation scope (when Owner approves D16)

Implement **read-only** Operational KPIs for the frozen **24** IDs only, under D01–D09 (+ Owner answers to D10–D15).

| In scope | Out of scope |
|---|---|
| Backend orchestrator + 5 domain calculators | New KPI IDs without CR |
| Explicit `season_id` API | Silent Active season fallback |
| `operational_kpis.view` catalog + role assign | GPS / live map |
| Vue RTL KPI page + season picker confirm | Historical reconstruction |
| Pest feature + unit + N+1 CI gates | Production KPI result cache |
| Safe authz audit events (D08-Q9) | S10 executive dashboard |
| Align blank TZ fallback per D04-Q9 if in scope of coding task | Changing approved formulas |

---

## 2. Likely modules / files (illustrative)

| Area | Likely touchpoints |
|---|---|
| Backend module | New `Modules/OperationalKpis` (or agreed namespace) — Actions, Calculators, Requests, Resources, Policy/Gate |
| Routes | `routes/api.php` under tenant auth middleware |
| Permissions | `PermissionCatalog.php` + role seed mapping |
| Frontend | `frontend/src/modules/operational-kpis/` (or agreed path): page, cards, season picker, queries |
| Router / nav | Permission-gated entry |
| Tests | `tests/Feature/OperationalKpis/*`, calculator unit tests |
| Docs | Module README / TEST_PLAN per MODULE_TEMPLATE after coding |

Exact paths finalized at implementation kickoff; this list is **proposal only**.

---

## 3. Permission & role rollout

1. Add `operational_kpis.view` to PermissionCatalog.  
2. Assign to least-privilege roles (Owner-approved role matrix at coding time).  
3. Gate page + API; domain omit via existing `*.view`.  
4. Do **not** substitute `dashboard.view`.

---

## 4. API & UI deliverables

- Authenticated `GET` (or agreed) operational KPIs with required `season_id`.  
- Contracts: 401 / 403 / 422 `SEASON_REQUIRED` / 404 foreign season / 200 + omit / `no_domain`.  
- DTO: id, value|null, state (`ok`|`undefined`|`error`…).  
- UI: domain groups, «مغلق», «غير متاح», «لا مواسم», loading/retry, RTL.

---

## 5. All 24 calculations

Implement exactly per D02–D06 matrices; no formula drift for performance.

---

## 6. Required tests

Tenant isolation; cross-tenant 404; missing season 422; page 403; domain omit; closed season; no seasons; empty COUNT/SUM; undefined AVG/ratio; error isolation (non-security); one snapshot; N+1 CI fail; query budget assertions (provisional); no KPI result cache; FE RTL/responsive smoke.

---

## 7. Performance & security gates

- ≤15 queries (provisional) for full auth set — report if unmet.  
- p95 &lt; 500ms provisional — non-prod measurement.  
- CI N+1 enforcement.  
- No unauthorized calc/serialize.  
- Indexes only via MEASURE_THEN_CR.

---

## 8. Migration policy

- Prefer **no** schema migrations for S09 v1.  
- Index CR only after measure.  
- Permission is catalog/seed, not ad-hoc SQL.

---

## 9. Git / PR workflow (when authorized)

1. Preserve `stash@{0}` untouched.  
2. Create dedicated branch from agreed base (`develop` tip after sync).  
3. Conventional Commits; PR to `develop`; CI green; dual review for security-relevant auth.  
4. No force-push; no commit of secrets.

**Do not execute now.**

---

## 10. Deployment exclusions

No production deploy from this proposal. No production DB access for S09 rollout until separate deploy authorization.

---

## 11. Rollback

- Feature flag or route disable if needed.  
- Revert PR / disable permission assignment.  
- No destructive data migrations expected.

---

## 12. Explicit Owner authorization wording (to approve later)

> **I authorize S09-D16 implementation** of the Operational KPIs module for the frozen 24 KPI IDs, under D01–D09 and the Owner-approved answers to D10–D15, including creation of `operational_kpis.view`, API + Vue UI, and the required Pest/CI gates.  
> This does **not** authorize production deployment, S10, GPS, formula changes, or KPI result caching in v1.

Until the Owner issues that act: **`S09-D16 = PENDING_OWNER_DECISION`**.

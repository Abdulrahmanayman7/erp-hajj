# Accommodation & Camps — Sprint 2 Scoped Approval & Implementation Plan

> **Type:** Documentation only. No application code, migration, seed, API, or frontend change was made in this pass.  
> **Approval date:** 2026-10-19  
> **Authority:** Explicit product-owner approval of the seven Sprint 2 decisions recorded below.  
> **Prerequisite:** Sprint 1 **ACCEPTED** (`ACCOMMODATION_SPRINT1_ACCEPTANCE.md`).  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Gate A status:** Complete when Module 18 docs are aligned with this file (see §12).  
> **Implementation:** Not started. Do not begin Gate B automatically from this document alone without an engineering start decision; this file **authorizes** Gate B work when the team chooses to start.

This scoped approval is **narrower than ADR-0019**. It does **not** accept the ADR and does **not** silently approve unrelated ADR provisions (arrival, check-in, relocation, cancellation listener, physical_capacity formula, Closed-season corrections, etc.).

---

## 1. The seven approved decisions (exact)

| # | Approved decision |
|---|---|
| **D1** | Confirmation is allowed only for SeasonPilgrim statuses `registered` and `active`. Reject `completed` and `cancelled`. |
| **D2** | Confirmation into a Family unit requires a non-null, valid `family_group_id`. Enforce the approved family exclusivity rules (different family groups cannot overlap in one Family unit) and preserve atomic family-group confirmation. |
| **D3** | For explicit-position mode, operational capacity equals the count of enabled season positions, subject to the configured `capacity` ceiling. A position must be enabled and must not have an overlapping confirmed assignment. |
| **D4** | Cross-season physical inventory uses detection-only fail-closed for Sprint 2. Reject overlapping confirmed intervals on the same stable physical unit across seasons, regardless of unused numeric capacity. Do not invent a cross-season sharing formula. |
| **D5** | Reject capacity decreases or position deactivations that would conflict with any confirmed assignment. Preserve ADR provisional decision 8. Reconcile conflicting Module 18 wording for this scoped implementation. |
| **D6** | Sprint 2 implementation is approved only for confirmation and capacity integrity. ADR-0019 remains **PROPOSED / NOT ACCEPTED**. |
| **D7** | Stable, distinct API error codes (existing envelope `success` / `message` / `code`) for: pilgrim confirmed-assignment overlap; unit or position over-capacity/conflict; Family-unit exclusivity conflict. |

---

## 2. Authorized Sprint 2 scope

| Authorized | Notes |
|---|---|
| Confirm eligible draft → `booking_status=confirmed` | Active season (`SeasonGuard::assertActive`); D1–D4 checks |
| Atomic family-group confirmation | All-or-nothing; D2 |
| Numeric-capacity peak enforcement | Peak concurrent confirmed stays ≤ season-unit `capacity` |
| Position exclusivity + D3 operational capacity | Enabled position; no overlapping confirmed on that position; peak ≤ min(enabled count, `capacity` ceiling) |
| Same-pilgrim confirmed overlap prevention | Via `accommodation_pilgrim_locks` (engineering name from ADR §4) |
| Cross-season detection-only fail-closed | D4; no `physical_capacity` |
| Capacity-decrease / position-deactivation safety | D5 on season allocation admin writes |
| Permission `accommodation.confirm` | Facility or supervisor scope; **zero** role-template grants |
| Confirm APIs, audit, confirm timeline events | Append-only confirm events as needed for this slice |
| Schema: widen assignment CHECK; pilgrim locks; events | Focused only |
| Deterministic lock order + concurrency tests | MariaDB / MySQL |
| Frontend confirmation flows | **After** backend Gate C verification |

### Pre-check-in staff cancellation — **excluded**

`ACCOMMODATION_SPRINT2_READINESS.md` proposed staff cancel of confirmed pre-check-in as a capacity-release path, but it was **not** one of the seven product-owner decisions and was not established as an explicit Sprint 2 dependency.

**Dependency reported:** Without a later cancel/release slice, a confirmed stay continues to consume capacity until a future approved cancellation or operational release path exists. Sprint 2 must not invent cancel APIs or listeners to fill that gap.

---

## 3. Explicit exclusions

- Check-in / check-out / arrival  
- Relocation / extension  
- Automatic `SeasonPilgrimCancelled` listeners / cancellation tasks  
- Historical correction workflows / post-Closed writes  
- Cross-season sharing formulas / `physical_capacity` column  
- Staff cancel of confirmed assignments (see above)  
- Family compatibility reviews table (membership drift after confirm)  
- Unrelated Foundation or Transportation behavior changes  
- Role-template permission grants (separate approval required)  
- Any ADR provision not listed in §2  

---

## 4. Dependencies and implementation gates

| Gate | Content | Exit criteria |
|---|---|---|
| **A** | Scoped approval + Module 18 alignment | This file + Module 18 updates recording D1–D7 |
| **B** | DB migration + concurrency foundation on isolated `erp_hajj_testing` (MariaDB) | CHECK widened; pilgrim locks; events; locking smoke tests; `erp_hajj` untouched |
| **C** | Backend confirm APIs + security/capacity tests | Pest green on MariaDB; 401/403/404/422 matrix; D1–D5 behaviors |
| **D** | Frontend confirmation flows + contract tests | Vitest + `vue-tsc`; confirm UX only |
| **E** | Integrated acceptance + concurrency verification | Multi-process confirm races; cross-season detection; acceptance note |

**Do not begin Gate B in this documentation pass.** Engineering starts Gate B only after an explicit implementation kickoff.

### Technical dependencies (flagged)

| Dependency | Status |
|---|---|
| Foundation `SeasonPilgrimStatus` values | Exists: `registered`, `active`, `completed`, `cancelled` — read-only |
| `SeasonGuard::assertActive` | Exists — reuse; do not weaken |
| Valid `family_group_id` | Must reference a Foundation family group for the same tenant/season (existing FK/validation patterns); do not invent new Foundation APIs |
| Gender known at confirm | Already provisional product rule; enforce at confirm (nullable gender → reject). Not a new PO decision in the seven; carry from ADR confirmation eligibility |
| Audience match (male/female/family) | Same — confirm-time enforcement carried from ADR §5; in scope as part of confirmation integrity |
| No confirmed-release path in Sprint 2 | Capacity may remain reserved until a later slice (reported dependency) |
| MySQL 8 CI | Still not proven locally; Gate E should include MariaDB multi-process at minimum |
| ADR Accepted | **Not required** for this scoped Sprint 2; ADR stays PROPOSED |

---

## 5. Database migration plan (Gate B — not executed now)

1. **Preflight** on `erp_hajj_testing` only: all assignment rows still `booking_status='draft'`.  
2. **Create** `accommodation_pilgrim_locks`: unique `(tenant_id, season_id, season_pilgrim_id)`, FK to `season_pilgrims` RESTRICT, no stay payload.  
3. **Create** `accommodation_events` (append-only confirm events for this slice; minimal columns per ADR engineering proposal — event type, assignment, occurred/recorded, actor, correlation).  
4. **Replace** `acc_asg_draft_ck` with a status-aware CHECK:  
   - **draft:** keep Sprint 1 predicates (`operational_status` / `family_group_id` / `checkout_source` null; `effective_ends_at = original_ends_at`; interval valid).  
   - **confirmed:** `booking_status='confirmed'`; `operational_status` null; `checkout_source` null; `effective_ends_at = original_ends_at`; interval valid; `family_group_id` nullable except enforced in app for Family audience (D2).  
   - Do **not** allow other booking statuses in Sprint 2.  
5. **Indexes** for pilgrim confirmed overlap and unit/position peak scans.  
6. **Seed** `accommodation.confirm` with **zero** role-template grants.  
7. **Admin write guards** (application): season-unit capacity decrease, season-position deactivate/delete, season-unit deallocation — reject when any conflicting confirmed interval exists (D5).

Rollback: only if no confirmed rows exist.

---

## 6. Transaction and deterministic lock-order strategy

```
DB::transaction(fn () => {
  // 1. Stable accommodation_units (target + cross-season unit), ORDER BY id, lockForUpdate
  // 2. Ensure + lock accommodation_pilgrim_locks for affected pilgrims, ORDER BY season_pilgrim_id
  // 3. Season units / season positions / overlapping assignments, ORDER BY id, lockForUpdate
  // 4. Re-check live facility or supervisor scope
  // 5. SeasonGuard::assertActive
  // 6. D1 status; known gender; audience; D2 family rules
  // 7. Same-pilgrim confirmed overlap
  // 8. D4 cross-season confirmed overlap detection
  // 9. Peak / position / D3 operational capacity
  // 10. Transition draft → confirmed; snapshot family_group_id; event + audit
}, attempts: 3);
```

Never `lockForUpdate` on `season_pilgrims` for occupancy serialization. Family batch locks the full deterministic set, validates every member, commits all or none.

---

## 7. API contract and error-code matrix

Base: `/api/v1`, `auth:sanctum`, `tenant.active`, `season.context`. Envelope per [API_STANDARDS.md](../04-api/API_STANDARDS.md): `success`, `message`, `code`, optional `errors` / `data`.

### Endpoints (authorized)

| Method | Path | Verb | Purpose |
|---|---|---|---|
| `POST` | `/accommodation/assignments/{id}/confirm` | `accommodation.confirm` | Confirm one draft |
| `POST` | `/accommodation/assignments/confirm-family` | `accommodation.confirm` | Atomic family-group confirmation (body: family group + draft ids or server-resolved members — exact payload frozen at Gate C against Form Request) |

No cancel-confirmed, arrive, check-in, check-out, or relocate routes in Sprint 2.

### Stable codes (D7 + confirm-adjacent reused codes)

| Code | HTTP | When |
|---|---|---|
| `ACCOMMODATION_PILGRIM_OVERLAP` | 422 | Candidate confirmed interval overlaps another **confirmed** stay for the same SeasonPilgrim |
| `ACCOMMODATION_CAPACITY_CONFLICT` | 422 | Unit peak would exceed capacity; or position overlapping confirmed; or D3 operational capacity (enabled count vs ceiling) violated; or D4 cross-season overlapping confirmed on same stable unit; or D5 admin capacity/position change conflicts with confirmed |
| `ACCOMMODATION_FAMILY_EXCLUSIVITY` | 422 | Family unit: missing/invalid family group; or overlapping confirmed stay with a different family snapshot |

Reuse existing codes where they already apply (do not rename):

| Code | Use |
|---|---|
| `SEASON_CLOSED` / season-not-active codes from Foundation | Closed / non-Active context |
| `AUTHORIZATION_DENIED` / `FORBIDDEN` | Missing verb or same-tenant missing scope |
| Standard 401 / 404 | Unauthenticated / cross-tenant or cross-season |
| `ACCOMMODATION_INTERVAL_INVALID` | Invalid interval on confirm path if revalidated |
| `ACCOMMODATION_INVALID_REFERENCE` | Bad unit/position/family reference |

**D7 required three distinct codes** are the three `ACCOMMODATION_*` rows above. Cross-season and D5 admin conflicts share `ACCOMMODATION_CAPACITY_CONFLICT` (same capacity-integrity class); message text distinguishes the case. Do not invent additional business-rule codes beyond this matrix without a further approval.

---

## 8. Test matrix (Gates B–E)

| ID | Case | Expect |
|---|---|---|
| T1 | Confirm `registered` / `active` + Active season + known gender | 201/200 confirmed |
| T2 | Confirm `completed` or `cancelled` | 422 (eligibility; may use validation or domain code — not one of D7 trio unless folded into message) |
| T3 | Unknown gender | 422 reject |
| T4 | Family unit, null family → reject; valid family → ok | `ACCOMMODATION_FAMILY_EXCLUSIVITY` / success |
| T5 | Two different families overlap on Family unit | `ACCOMMODATION_FAMILY_EXCLUSIVITY` |
| T6 | Family batch one ineligible member | Entire batch rolls back |
| T7 | Numeric peak staggered OK / concurrent over-cap fail | success / `ACCOMMODATION_CAPACITY_CONFLICT` |
| T8 | Position disabled or double-booked | `ACCOMMODATION_CAPACITY_CONFLICT` |
| T9 | D3: peak ≤ min(enabled positions, capacity ceiling) | enforced |
| T10 | Same pilgrim two units overlap (multi-process) | exactly one confirms; loser `ACCOMMODATION_PILGRIM_OVERLAP` |
| T11 | Cross-season overlapping confirmed on same unit | `ACCOMMODATION_CAPACITY_CONFLICT` |
| T12 | Capacity decrease conflicting with confirmed | `ACCOMMODATION_CAPACITY_CONFLICT` |
| T13 | Position deactivate conflicting with confirmed | `ACCOMMODATION_CAPACITY_CONFLICT` |
| T14 | Missing `confirm` / missing scope | 403 |
| T15 | Cross-tenant assignment id | 404 |
| T16 | Draft CRUD unchanged (overlapping drafts still allowed) | Sprint 1 behavior preserved |
| T17 | `erp_hajj` table count unchanged by Pest | safety |
| T18 | FE contract tests for confirm payloads and D7 codes | Vitest |

---

## 9. Implementation phases and acceptance criteria

| Phase | Gate | Acceptance |
|---|---|---|
| Docs alignment | A | This file + Module 18 record D1–D7; ADR still PROPOSED |
| Schema + locks | B | Migrations on `erp_hajj_testing` only; draft CHECK preserved for drafts; pilgrim locks exist |
| Confirm APIs | C | Individual + family batch; D1–D5; D7 codes; audit/events; security tests |
| Frontend | D | Confirm actions; error mapping; no check-in/cancel UI |
| Acceptance | E | Concurrency + integrated review note; Sprint 1 regressions green |

**Sprint 2 slice acceptance (future):** all T1–T18 relevant cases green; no Sprint 1 draft behavior regression; ADR-0019 still PROPOSED; no listener/check-in/relocate shipped.

---

## 10. Risks carried from Sprint 1

| Risk | Carry-forward |
|---|---|
| Scope revoke vs write TOCTOU residual | Confirm must re-check scope under locks |
| Structure re-parent concurrency | Unchanged prep risk |
| MySQL 8 CI not run | Gate E must use real InnoDB locking (MariaDB minimum) |
| Browser E2E for Sprint 1 unexecuted | Confirm UI needs explicit QA at Gate E |
| Disk pressure on C: | Protect build/test environment |
| No confirmed-release path | Capacity stuck until later cancel/ops slice |

---

## 11. ADR-0019 statement

**ADR-0019 remains PROPOSED / NOT ACCEPTED.**

This scoped approval authorizes only the confirmation and capacity-integrity slice described here. It does not accept the ADR, does not authorize later ADR slices, and does not change ADR status in `docs/10-decisions/ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md`.

---

## 12. Gate A completion

| Item | Status |
|---|---|
| Product-owner seven decisions recorded | Yes (§1) |
| Scope / exclusions / cancel dependency reported | Yes (§2–§3) |
| Migration, locks, API codes, tests, phases | Yes (§5–§9) |
| Module 18 docs aligned to D1–D7 | Yes (companion edits in this pass) |
| ADR status unchanged | Yes |
| Implementation started | **No** |

**Gate A: COMPLETE** (documentation).

**Final verdict for next step:** **READY FOR GATE B** — when engineering explicitly starts implementation. Do not begin Gate B from this documentation pass automatically.

# Accommodation & Camps — Sprint 2 Readiness (Confirmation & Capacity)

> **Type:** Documentation and architecture readiness only. No application code, migration, seed, or ADR status change was made.  
> **Date:** 2026-10-19  
> **Prerequisite:** Sprint 1 **ACCEPTED** (`docs/reviews/ACCOMMODATION_SPRINT1_ACCEPTANCE.md`).  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Superseded for GO/NO-GO:** Product-owner decisions and Gate A authorization are in [`ACCOMMODATION_SPRINT2_SCOPED_APPROVAL.md`](ACCOMMODATION_SPRINT2_SCOPED_APPROVAL.md). This readiness file remains the pre-decision analysis.

## Verdict (historical — pre product-owner decisions)

### **NO-GO** for implementation *(superseded by scoped approval 2026-10-19)*

Prior to product-owner approval, Sprint 2 could not start until the blockers in §3 were decided. Those seven decisions are now recorded in the scoped approval; ADR-0019 remains PROPOSED.

What follows separates:

| Label | Meaning |
|---|---|
| **Decided (provisional product)** | ADR-0019 §2 groups / hard boundaries — product intent preserved in the ADR, **not** Accepted |
| **Engineering proposal** | ADR §4–§10 / this document — safe candidate, **not** authority to build |
| **Unresolved blocker** | Must be decided before confirm API / occupancy enforcement ships |
| **Smallest safe proposal** | Narrowest fail-closed rule for design review — still a proposal |

---

## 1. Sprint 2 boundary

### In scope (this readiness)

Confirmation of individual draft stays into **confirmed** bookings with **capacity integrity**:

1. Confirmation eligibility and SeasonPilgrim status matrix  
2. Peak capacity (numeric mode) and position exclusivity (position mode)  
3. Cross-season physical overlap **detection** (fail-closed while formula unresolved)  
4. Same SeasonPilgrim overlapping confirmed stays  
5. Family unit exclusivity (different family groups)  
6. Atomic family-group confirmation (design; may be a sub-phase — see §6)  
7. MySQL/MariaDB transaction and lock order  
8. Safe migration of Sprint 1 draft-only CHECK  
9. `accommodation.confirm` + facility/supervisor scope  
10. Confirmed-assignment cancellation rules **required so capacity stays correct** (staff cancel of confirmed pre-check-in only — **not** the after-commit `SeasonPilgrimCancelled` listener)

### Explicitly out of Sprint 2

- Arrival, check-in, check-out, relocation, extension  
- `SeasonPilgrimCancelled` listener / `accommodation_cancellation_tasks`  
- Closing-readiness contributors  
- Family compatibility reviews / `accommodation_family_reviews`  
- Historical corrections / Closed-season write exception  
- `physical_capacity` column unless separately approved  
- QR, offline, notifications, demand/queue/auto-allocation  
- Changing ADR status; modifying Sprint 1 draft behavior beyond what confirmation migrations require

ADR §10 maps “individual confirm + capacity” to delivery slice **2**, and “family atomic batch” to slice **4**. This readiness analyzes both; the implementation phases (§8) keep batch as a gated sub-phase so Sprint 2 can still ship individual confirm if batch remains blocked.

---

## 2. Analysis of the ten readiness items

### 2.1 Confirmation eligibility and allowed SeasonPilgrim statuses

| Aspect | Content |
|---|---|
| **Already decided (provisional)** | Confirm only when context season is **Active** (`SeasonGuard::assertActive` — not `assertWritable` alone). Known **gender** required (unknown blocks every audience, including Family). Unit (+ optional facility) **audience** must match. Verb `accommodation.confirm` + facility or supervisor scope. No overlapping confirmed stay for that SeasonPilgrim. Atomic commit under lock order. |
| **Unresolved blocker** | **Allowed SeasonPilgrim statuses** for confirmation — explicit matrix after Foundation states (`ADR-0019` §11; reconciliation §8.1). Do **not** assume `registered`+`active` only, and do **not** assume `completed` is allowed. |
| **Foundation facts (code)** | `SeasonPilgrimStatus`: `registered`, `active`, `completed`, `cancelled`. Terminal = `completed` \| `cancelled`. Sprint 1 drafts may link **any** participation including terminal (`DATA_MODEL.md`) — that does **not** decide confirm eligibility. |
| **Smallest safe proposal** | Allow confirm only when status ∈ {`registered`, `active`}. Reject `completed` and `cancelled`. Still require known gender and Active season. Mark as **proposal** until product decides. |
| **Affected components** | DB: none for status (read Foundation). API: confirm action validation. FE: disable confirm when ineligible. Tests: matrix per status × gender × season. |

---

### 2.2 Capacity enforcement (numeric-capacity vs explicit-position)

| Aspect | Content |
|---|---|
| **Already decided (provisional)** | Modes: capacity-based and position-based (decision 1). Position-mode requires enabled season position; capacity-mode **prohibits** position. Peak concurrent **confirmed** stays on a season unit ≤ season `capacity` over the candidate interval (not a naive intersect count). Position: no two overlapping confirmed stays on same position; unit peak still applies. Half-open: `existing.starts_at < candidate.effective_ends_at AND existing.effective_ends_at > candidate.starts_at`. Drafts and cancelled do not count. Occupancy clock = `effective_ends_at`. |
| **Unresolved blocker** | Whether numeric `capacity` may be **lower** than the count of enabled season positions, or must equal it (`ADR-0019` §11; `BUSINESS_RULES.md` Modes TBD). |
| **Sprint 1 fact** | `capacity` stored, not enforced; no check tying capacity to position count (`DATA_MODEL.md`). |
| **Smallest safe proposal** | Enforce **both** unit peak ≤ `capacity` and position exclusivity. Do **not** auto-derive `capacity` from position count. If `capacity` is null/invalid for the mode, reject confirm. Whether `capacity < enabled_positions` is allowed stays a product decision; until decided, **reject confirm** when `allocation_mode=position` and `capacity` is set to a value that cannot be satisfied by available exclusive positions for the interval (fail closed on ambiguous configs). |
| **Affected components** | DB: indexes already partially present (`acc_asg_unit_start_idx`); no peak trigger. API: confirm validator + peak sweep service. FE: show capacity errors. Tests: staggered stays pass; simultaneous over-cap fail; position double-book fail. |

---

### 2.3 Cross-season physical inventory conflicts

| Aspect | Content |
|---|---|
| **Already decided (provisional)** | Stable unit identity across seasons (decision 10). Seasons may overlap on the calendar. Under stable-unit lock, load confirmed intervals for that `accommodation_units` row in **every** season. If another season has an overlapping confirmed stay → **reject** while the numeric formula is unresolved. Do not invent `physical_capacity`. If no other-season overlap, formula is not required for that candidate — still do not invent a number. |
| **Unresolved blocker** | Numeric cross-season capacity formula / `physical_capacity` (**NOT APPROVED**, `ADR-0019` §6 / §11). |
| **Smallest safe proposal** | Ship **detection + fail-closed reject** only (already mandatory in ADR §6). Defer any shared numeric column. Document that some multi-season calendars will reject confirmations until a formula is approved. |
| **Affected components** | DB: none new for detection. API: confirm path queries cross-season confirmed rows by `unit_id`. Tests: MySQL multi-process cross-season overlap rejected without asserting a physical cap. |

---

### 2.4 Overlapping confirmed assignments for the same SeasonPilgrim

| Aspect | Content |
|---|---|
| **Already decided (provisional)** | No overlapping confirmed effective intervals for one SeasonPilgrim across any units/facilities (decision 3; ADR §6.3). Gaps allowed. Never `lockForUpdate` on `season_pilgrims`; serialize via `accommodation_pilgrim_locks`. |
| **Unresolved** | None for the rule itself. |
| **Smallest safe proposal** | Adopt ADR rule as-is; create lock-anchor table in Sprint 2 migrations (engineering proposal already named). |
| **Affected components** | DB: new `accommodation_pilgrim_locks`. API: confirm. Tests: two concurrent confirms onto two units → exactly one succeeds. |

---

### 2.5 Family unit exclusivity and null family-group handling

| Aspect | Content |
|---|---|
| **Already decided (provisional)** | Different family groups cannot overlap in one Family unit (decision 12). Snapshot `family_group_id` at confirm from Foundation; Foundation remains owner of live membership. Unknown gender blocks confirm. |
| **Unresolved blocker** | Whether a SeasonPilgrim with **null** `season_family_group_id` may be confirmed onto a Family unit (`ADR-0019` §5 / §11). Null-vs-null and null-vs-id comparisons are **not** product decisions yet. Doc tension: reconciliation treats null-vs-id as a “difference”; ADR §6 parks null cases in §11 — **do not silently pick**. |
| **Smallest safe proposal** | For Sprint 2 individual confirm: (a) enforce exclusivity when **both** overlapping confirmed stays have **non-null distinct** family snapshots; (b) **reject** confirm onto `audience=family` when the pilgrim’s current family group is null (**candidate only** — still needs product sign-off). Leave null-vs-null concurrent occupancy unspecified until product decides; fail closed by rejecting the second null onto a Family unit that already has a confirmed null/group stay if product has not decided otherwise. |
| **Affected components** | DB: allow non-null `family_group_id` on confirmed rows (CHECK migration). API: exclusivity validator. FE: Family unit confirm errors. Tests: two different families overlap → fail; same family within capacity → pass; null cases gated by decision. |

---

### 2.6 Atomic family-group confirmation

| Aspect | Content |
|---|---|
| **Already decided (provisional)** | Family bulk creates one assignment per member (decision 5). Group confirmation is atomic all-or-nothing (decision 8). Lock full set, validate every member, commit all or roll back. |
| **Unresolved** | Blocked by the same §11 items as individual confirm (statuses, null family, position cap, cross-season formula posture). ADR schedules batch in slice **4**. |
| **Smallest safe proposal** | Sprint 2 **Phase A:** individual confirm only. **Phase B (optional, same sprint only if §3 blockers cleared):** `POST .../assignments/confirm-family` (or equivalent) that expands members, locks units+pilgrims deterministically, confirms all drafts or none. Do not partially confirm. |
| **Affected components** | API: batch endpoint. FE: family confirm action. Tests: atomic group failure (one ineligible member fails entire batch). |

---

### 2.7 MySQL/MariaDB transaction and locking strategy

| Aspect | Content |
|---|---|
| **Already decided (engineering proposal in ADR §6)** | One outermost `DB::transaction(..., 3)` (match Transportation attempts). Lock order, each group `orderBy` then `lockForUpdate()`: (1) stable `accommodation_units` (incl. cross-season unit); (2) `accommodation_pilgrim_locks` for affected pilgrims (ensure row exists, then lock) — **never before** units; (3) season-unit → season-position → assignment rows used by peak/overlap. Recheck facility/supervisor scope **inside** the transaction after locks. |
| **Unresolved** | Deadlock/multi-process behavior **NOT VERIFIED** on MySQL 8 / MariaDB for Accommodation (Sprint 1 residual). |
| **Smallest safe proposal** | Adopt ADR §6 order verbatim. Require MySQL/MariaDB feature + concurrency tests; SQLite does not prove locking. |
| **Affected components** | Actions/services: confirm (and later cancel-confirmed). Tests: `tests/Concurrency` style multi-process. |

---

### 2.8 Sprint 1 draft-only database constraints (safe migration)

| Aspect | Content |
|---|---|
| **Sprint 1 fact** | Constraint `acc_asg_draft_ck` on `accommodation_assignments`: `booking_status = 'draft' AND operational_status IS NULL AND family_group_id IS NULL AND checkout_source IS NULL AND effective_ends_at = original_ends_at AND original_ends_at > starts_at` (migration `2026_10_15_180008_create_accommodation_assignments_table.php`). Application forces draft-only writes. |
| **Already decided (provisional)** | Booking path includes `draft → confirmed` (and later cancelled/completed — those later statuses are **not** Sprint 2 operational targets beyond capacity-preserving cancel). Confirmed rows need family snapshot; operational_status may remain null until arrival/check-in slice. |
| **Unresolved** | Exact CHECK shape for confirmed rows; whether `operational_status` stays null on confirm (likely yes until slice 3). |
| **Smallest safe proposal** | See §5 Migration strategy: drop/replace `acc_asg_draft_ck` with a status-aware CHECK that (a) preserves all Sprint 1 draft invariants for `booking_status='draft'`, (b) allows `booking_status='confirmed'` with null operational/checkout, non-null optional family snapshot, `effective_ends_at = original_ends_at` at confirm time, interval validity. Do **not** allow `checked_in` / operational transitions in this sprint. Expand only what confirmation needs. |
| **Affected components** | DB migration (MySQL `ALTER … DROP CHECK` / add new; SQLite rebuild path if still used in CI). Models/actions/resources. Tests: draft rows still satisfy draft branch; confirmed insert allowed only via confirm action. |

---

### 2.9 Confirmation permissions and facility-scope authorization

| Aspect | Content |
|---|---|
| **Already decided (provisional)** | Seed verb **`accommodation.confirm`** (not in Sprint 1 catalog). Facility-scoped: live facility scope for the assignment’s facility **or** season supervisor scope. Supervisor widens facilities only — does not grant the verb. Same-tenant missing scope → **403**; cross-tenant/season → **404**. Recheck scope inside write txn after locks. No role-template grants. Active season only. |
| **Unresolved** | Which roles eventually receive `confirm` (**TBD** in `PERMISSIONS.md`) — does not block seeding the catalog verb with zero template grants (same pattern as Sprint 1). |
| **Smallest safe proposal** | Seed `accommodation.confirm` with **zero** role-template grants. Policy mirrors `draft` scope rules. Draft season: confirm rejected via `assertActive`. |
| **Affected components** | `PermissionCatalog`, policies, routes, audit event types, FE `can('accommodation.confirm')`. Tests: missing verb 403; out-of-scope 403; supervisor without verb still 403. |

---

### 2.10 Confirmed assignment cancellation rules (capacity integrity only)

| Aspect | Content |
|---|---|
| **Already decided (provisional)** | Pre-check-in confirmed stay may be cancelled (booking → `cancelled`); cancelled does not count toward capacity. Checked-in path uses administrative checkout / listener — **out of Sprint 2**. Preserve history (no hard delete of confirmed). |
| **Boundary** | Do **not** implement `SeasonPilgrimCancelled` listener or cancellation tasks in Sprint 2. Staff **`accommodation.cancel_assignment`** (or confirm-scoped cancel) for **confirmed + not checked-in** is in scope only insofar as capacity integrity requires a release path; if product defers all cancel verbs, confirmations cannot be undone without a later slice — that must be an explicit product choice. |
| **Unresolved** | Idempotency keys / listener reliability (`ADR-0019` §11) — blocks **listener**, not necessarily staff cancel of confirmed. Cancelled participation’s **draft** rows lifecycle (keep vs cancel) — separate TBD; drafts never count as capacity. |
| **Smallest safe proposal** | Sprint 2: allow staff cancel of `booking_status=confirmed` with `operational_status` null only → set `booking_status=cancelled`, keep row, do not count in peak. Require `accommodation.cancel_assignment` + scope + Active season. Reject cancel of drafts via this verb (drafts keep Sprint 1 delete). **No** automatic Foundation-cancel listener. Document that a cancelled SeasonPilgrim may still hold a confirmed stay until a later slice releases it (known integrity gap — closing readiness later). |
| **Affected components** | CHECK must allow `cancelled`. API cancel endpoint. Peak queries exclude cancelled. Tests: cancel frees capacity for a subsequent confirm. |

---

## 3. Unresolved blockers (GO criteria)

These **block** a GO verdict. Proposals above are not substitutes for decisions.

| # | Blocker | Blocks | Smallest decision needed |
|---|---|---|---|
| B1 | SeasonPilgrim status eligibility matrix | Confirm API | Explicit allow/deny per `registered` / `active` / `completed` / `cancelled` |
| B2 | Null family membership on Family unit | Family confirmation | Allow alone / reject / allow with exclusivity rules for null-vs-null |
| B3 | Position-mode `capacity` vs enabled position count | Validation rules | May capacity be lower / must equal / derived |
| B4 | Cross-season numeric formula | Shared multi-season occupancy beyond detection | Approve formula **or** formally accept detection-only fail-closed as the Sprint 2 product rule |
| B5 | Capacity-decrease conflict (module 18 vs decision 8) | Admin writes once confirmed stays exist | Supersede module 18: reject capacity/position/deallocation edits that invalidate confirmed intervals |
| B6 | ADR-0019 Acceptance (or a scoped Sprint 2 approval that explicitly lists adopted provisional decisions) | Any migration/seed/API | Separate Change Request / ADR status change — **this readiness is not that approval** |
| B7 | Stable API error codes for overlap / over-capacity / family exclusivity | FE + clients | Name codes before implementation (today **TBD** in `API.md`) |

Related items that **do not** block individual confirm if Sprint 2 excludes them: checkout edges, relocation edges, family-review gating, cancellation **listener** idempotency, post-close corrections, Hijri display.

---

## 4. Confirmed decisions usable as design inputs (still not Accepted)

Use these as the **default design baseline** once B6 grants scoped approval; until then they remain provisional:

1. Drafts never consume capacity; confirm revalidates everything.  
2. Active-only confirmation (`assertActive`).  
3. Known gender required.  
4. Peak concurrent occupancy + position exclusivity.  
5. Same-pilgrim confirmed non-overlap via pilgrim lock anchors.  
6. Cross-season **detection** fail-closed without inventing `physical_capacity`.  
7. Different family snapshots cannot overlap on Family units.  
8. Lock order: units → pilgrim locks → season rows → assignments.  
9. `accommodation.confirm` is facility-scoped (or supervisor-widened).  
10. Half-open Gregorian UTC intervals; `effective_ends_at` for occupancy.  
11. Do not write `SeasonPilgrim.status`, family membership, or Transportation rows.  
12. Reject capacity/position/deallocation changes that break confirmed intervals (decision 8) — **once B5 reconciles module 18**.

---

## 5. Migration strategy (proposal only)

Ordered steps for a **future** Sprint 2 migration set. Do not apply until GO.

1. **Preflight**  
   - Assert only `booking_status='draft'` rows exist (or migrate any unexpected status offline).  
   - Snapshot `acc_asg_draft_ck` definition on MariaDB/MySQL.

2. **New tables (engineering names from ADR §4)**  
   - `accommodation_pilgrim_locks` — unique `(tenant_id, season_id, season_pilgrim_id)`, FK to `season_pilgrims`, RESTRICT, no stay payload columns.  
   - `accommodation_events` — append-only confirm (and cancel-confirmed) timeline rows required for auditable confirmation (ADR slice 2 includes timeline).  
   - Do **not** create `accommodation_cancellation_tasks`, `accommodation_family_reviews` in Sprint 2 unless a later approval expands scope.

3. **Replace draft-only CHECK** (MySQL/MariaDB)  
   - `ALTER TABLE accommodation_assignments DROP CHECK acc_asg_draft_ck` (name must match live constraint).  
   - Add `acc_asg_booking_ck` (name TBD) roughly:  
     - **draft branch:** current Sprint 1 predicates unchanged.  
     - **confirmed branch:** `booking_status='confirmed' AND operational_status IS NULL AND checkout_source IS NULL AND effective_ends_at = original_ends_at AND original_ends_at > starts_at` (family_group_id nullable pending B2).  
     - **cancelled branch (if §2.10 adopted):** `booking_status='cancelled' AND …` interval validity; operational null.  
   - Forbid other `booking_status` values until later slices.

4. **Indexes**  
   - Support pilgrim confirmed-overlap scans: e.g. `(tenant_id, season_id, season_pilgrim_id, booking_status, starts_at)`.  
   - Support unit/position peak scans: existing unit start index + position-aware index if needed.

5. **Permissions**  
   - Seed `accommodation.confirm` (+ `accommodation.cancel_assignment` if §2.10 adopted) with **zero** role-template grants.

6. **Application cutover**  
   - Confirm action is the **only** writer of `confirmed`. Draft CRUD remains Sprint 1 behavior.  
   - Resources stop hard-coding “always draft” semantics for confirmed rows; keep `capacity_reserved` honest (true only while confirmed and counting).

7. **Rollback**  
   - Only safe if no confirmed/cancelled rows exist; otherwise forward-fix only.

SQLite CI: if still used, rebuild table with new CHECK (same pattern as Sprint 1 migration). Prefer proving CHECK + locking on MariaDB/MySQL (`phpunit.mysql.xml` / MySQL 8 CI).

---

## 6. Transaction and lock-order design (proposal)

```
DB::transaction(function () {
  // 1. Stable units (this confirm + any cross-season unit id), ORDER BY id, lockForUpdate
  // 2. Ensure accommodation_pilgrim_locks rows; ORDER BY season_pilgrim_id; lockForUpdate
  // 3. Season units / season positions / candidate+overlapping assignments, ORDER BY id; lockForUpdate
  // 4. Re-read live facility/supervisor scope — deny if revoked
  // 5. SeasonGuard::assertActive
  // 6. Eligibility (status matrix B1, gender, audience)
  // 7. Same-pilgrim confirmed overlap
  // 8. Cross-season confirmed overlap detection (fail closed)
  // 9. Peak capacity + position exclusivity (+ family exclusivity)
  // 10. Transition draft → confirmed; snapshot family_group_id; write event + audit
}, attempts: 3);
```

Deadlock retries must re-read committed state. Never lock `season_pilgrims` for occupancy serialization.

---

## 7. Proposed API / FE surface (engineering proposal)

| Surface | Proposal |
|---|---|
| `POST /api/v1/accommodation/assignments/{id}/confirm` | Confirm one draft; Active; `confirm` + scope |
| `POST /api/v1/accommodation/assignments/confirm-family` | Phase B only; atomic batch |
| `POST /api/v1/accommodation/assignments/{id}/cancel` | Confirmed + not checked-in only (§2.10); or defer with explicit product acceptance of no release path |
| FE | Confirm / cancel actions on drafts page; capacity and exclusivity error codes; no check-in UI |

Error codes: **TBD until B7** — placeholders must not ship as final without a contract edit to `API.md`.

---

## 8. Implementation phases (after GO only)

| Phase | Content | Gate |
|---|---|---|
| **0 — Decisions** | Resolve B1–B7; reconcile module 18 capacity-decrease wording; scoped Sprint 2 approval or ADR Accepted | **Required before code** |
| **1 — Schema** | Pilgrim locks, events, CHECK replacement, indexes, permission seed | Phase 0 |
| **2 — Confirm service** | Lock order, eligibility, peak, position, pilgrim overlap, cross-season detection, family exclusivity (non-null), audit + timeline | Phase 1 |
| **3 — Cancel-confirmed (capacity)** | Staff cancel confirmed pre-check-in; peak exclusion | Product accepts §2.10 |
| **4 — Family atomic batch** | All-or-nothing confirm | B2 cleared; optional vs later ADR slice 4 |
| **5 — Frontend** | Confirm/cancel UX after API contract frozen | Phases 2–3 |
| **6 — Hardening** | MySQL multi-process concurrency suite; MariaDB + MySQL 8 CI | Before production |

Do not start Phase 1 without Phase 0.

---

## 9. Test matrix (future)

| # | Case | DB target |
|---|---|---|
| T1 | Confirm success Active + eligible status + known gender + audience match | MySQL/MariaDB |
| T2 | Reject Draft/Closed season (`assertActive`) | MySQL/MariaDB |
| T3 | Reject unknown gender (all audiences) | MySQL/MariaDB |
| T4 | Reject ineligible SeasonPilgrim status (per B1 matrix) | MySQL/MariaDB |
| T5 | Peak: staggered confirmed stays within capacity → pass | MySQL/MariaDB |
| T6 | Peak: concurrent over-capacity → fail | MySQL/MariaDB |
| T7 | Position double-book overlapping → fail | MySQL/MariaDB |
| T8 | Capacity-mode confirm with position set → fail; position-mode without → fail | MySQL/MariaDB |
| T9 | Same pilgrim two units overlapping → one wins (multi-process) | MySQL multi-process |
| T10 | Cross-season overlapping confirmed on same unit → reject (no physical_capacity assert) | MySQL/MariaDB |
| T11 | Family exclusivity: different snapshots overlap → fail | MySQL/MariaDB |
| T12 | Null family on Family unit — per B2 decision | MySQL/MariaDB |
| T13 | Missing `confirm` / missing scope → 403; cross-tenant → 404 | MySQL/MariaDB |
| T14 | Scope revoked between read and confirm → deny inside txn | MySQL/MariaDB |
| T15 | Draft CHECK branch still holds for draft CRUD | MySQL/MariaDB |
| T16 | Cancel confirmed frees capacity for new confirm | MySQL/MariaDB |
| T17 | Family batch atomic failure | MySQL/MariaDB |
| T18 | Capacity decrease conflicting with confirmed → reject (after B5) | MySQL/MariaDB |
| T19 | FE contract tests for confirm/cancel payloads and error codes | Vitest |
| T20 | SQLite-only suites do **not** count as locking proof | — |

---

## 10. Risks carried from Sprint 1

| Risk | Sprint 2 impact |
|---|---|
| Scope revoke vs write concurrency residual | Confirm must recheck scope under locks — higher severity |
| Structure re-parent races | Prep-time; keep facility lock discipline on hierarchy edits |
| MySQL 8 CI not run | Confirm concurrency **must** run on real MySQL/MariaDB |
| Browser E2E for Sprint 1 unexecuted | Confirm UI will need explicit browser/manual QA |
| C: disk pressure | Protect CI/build environment before large suites |

---

## 11. Documentation updates required before/with Phase 0 (not done here)

When decisions land, a **separate** doc edit should:

- Supersede `BUSINESS_RULES.md` capacity-decrease wording (B5)  
- Extend `DATA_MODEL.md` / `API.md` / `PERMISSIONS.md` / `TEST_PLAN.md` / `ACCEPTANCE_CRITERIA.md` / `UI.md` for confirm  
- Record scoped Sprint 2 approval **or** mark ADR-0019 Accepted  
- **Not** silently treat this readiness file as acceptance  

---

## 12. GO / NO-GO checklist

| Criterion | Status |
|---|---|
| Sprint 1 accepted | Yes |
| ADR-0019 Accepted or equivalent scoped Sprint 2 approval | **No** |
| B1 status matrix decided | **No** |
| B2 null family on Family unit decided | **No** |
| B3 position capacity vs position count decided | **No** |
| B4 cross-season formula **or** formal detection-only product acceptance | **No** (engineering proposal exists; product acceptance missing) |
| B5 module 18 capacity-decrease reconciled | **No** |
| B7 error codes named | **No** |
| Module 18 docs updated for Sprint 2 | **No** |
| Implementation started | **No** (correct) |

### Final verdict: **NO-GO**

Do not write Sprint 2 migrations or application code until Phase 0 clears the blockers above. ADR-0019 remains **PROPOSED / NOT ACCEPTED**.

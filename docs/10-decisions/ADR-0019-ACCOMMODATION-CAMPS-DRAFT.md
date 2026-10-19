# ADR-0019 — Accommodation & Camps: Seasonal Occupancy and Facility Operations

**Status:** PROPOSED / REVIEW ONLY — **NOT ACCEPTED**  
**Date:** 2026-10-15  
**Scope:** ERP Hajj, tenant-scoped Accommodation & Camps bounded context  
**Basis:** `ACCOMMODATION_CAMPS_ARCHITECTURE_DRAFT.md` (13 provisional decision groups), `ACCOMMODATION_CAMPS_ARCHITECTURE_VERIFICATION.md`, `ACCOMMODATION_CAMPS_RECONCILIATION.md` (as reported and reviewed in this conversation).  
**Implementation authorization:** NONE. This document is not a migration specification or approval to edit code.  
**Correction pass (2026-10-15):** scope bootstrap, family-membership wording, pilgrim lock anchor, cross-season overlap detection without a capacity formula, season guards, and a narrower Backend Sprint 1. Status remains **PROPOSED / NOT ACCEPTED**. The 13 provisional decisions are unchanged.  
**Repository:** `develop` @ `736c776dd1d8ca6ddcb53239ab8cd2e87a9943fa` at verification time; recheck HEAD before implementation.

## 1. Context and problem

Accommodation must support hotels and camps, season-dependent inventory, gender/family-aware allocation, time-based occupancy, atomic group operations, staff facility scope, and a distinct immutable operational timeline. Season & Pilgrims Foundation and Transportation are already implemented and must remain untouched. Existing module 18 documents contain conceptual TBDs and a capacity-reduction rule conflicting with the provisional product decisions. The first verification proposed season-only places and omitted several subsequently specified rules; the reconciliation corrected this but did not approve a physical schema.

**Evidence classification:** Sections 2–3 preserve **PROVISIONAL PRODUCT DECISIONS**; sections 4–10 contain **ENGINEERING PROPOSALS**, unless explicitly called out; section 11 lists **NOT APPROVED / UNRESOLVED** items. No proposal is promoted to an approved product rule by appearing here.

## 2. Provisional product decisions preserved (13 groups)

1. **Inventory shape:** hotel rooms and camp tents support numeric-capacity or explicit bed/position mode; buildings and floors optional.
2. **Drafts:** draft assignments never consume capacity; no temporary holds.
3. **Time:** Gregorian UTC storage, optional Hijri display, half-open `[start_at, end_at)` intervals; gaps allowed; one SeasonPilgrim cannot have overlapping confirmed stays, even across facilities.
4. **Operations:** arrival optional; pre-check-in cancellation allowed even after arrival; checkout only after check-in; combined relocation + check-in is one atomic user action with separate events.
5. **Participation cancellation:** before check-in, cancel the accommodation assignment; after check-in, automatically check out and immediately release future capacity even without physical departure confirmation. Preserve history, administrative source and idempotency. Family bulk allocation creates one assignment per pilgrim. Unknown gender blocks confirmation, including Family units.
6. **Season lifecycle:** Draft allows facility preparation and assignment drafts, Active allows confirmation and operations, Closed is read-only except a separately controlled historical-correction exception whose **mechanism remains unapproved**. Open checked-in stays and unresolved critical operations block closure.
7. **Permissions:** separate draft/confirm, check-in/checkout/relocate and correction permissions. Staff operate only within assigned facilities; supervisors have season-wide scope. Corrections require independent permission, mandatory reason and audit.
8. **Capacity/integrity:** reject overbooking; atomic all-or-nothing group confirmation; reject reductions conflicting with any affected confirmed interval; append-only correction history.
9. **Audience:** Family units may house men and women from the same family; family may be split across units; unknown gender blocks confirmation; audience on unit, optional facility restriction.
10. **Persistent physical identity:** facilities, rooms and tents persist across seasons. Season allocation chooses subset and season-specific capacity/audience; previous seasons' configurations remain historical.
11. **Stay changes:** early checkout frees future occupancy and preserves planned end; relocation atomic; extension revalidates capacity/overlap/permission; historical changes use correction events.
12. **Family exclusivity:** different family groups cannot overlap in one Family unit; one member of a valid family group may stay alone; membership changes trigger compatibility review without automatic cancellation.
13. **Facility scope:** user grants are tenant + season + facility, supervisor grants tenant + season. Relocation needs source and destination scope or season supervisor scope. Revocation applies immediately to new operations, preserving history.

## 3. Hard boundaries and integration contracts

- Tenant identity from `TenantContext`, season identity from `SeasonContext` / `X-Season-Id`, never trusted from a request body. Cross-tenant and cross-season access uses existing 404 behavior; permissions additionally use 403 for lack of scope.
- Do **not** write `SeasonPilgrim.status`, family membership, or Transportation-owned rows. Do not add `current_room` or equivalent to Foundation.
- Reuse composite tenant/season foreign-key patterns and `RESTRICT` history retention. Read nullable pilgrim gender; enforce eligibility on accommodation confirmation.
- Register Accommodation-owned contributors to `season_pilgrims.closing_readiness`, operational-reference and deletion-reference checkers via a future provider. Do not register an activation requirement.
- Subscribe to after-commit `SeasonPilgrimCancelled`; restore tenant context with `runAsTenant`. A failure cannot roll back Foundation cancellation. Transportation arrival must not check in Accommodation.
- **Season guards (decision 6).** Context season comes from `SeasonContext`. `SeasonStatus::isWritable()` is true for Draft and Active and false for Closed (`SeasonGuard::assertWritable`). Do not use `assertWritable` alone to authorize confirmation.
  - **Draft and Active:** facility preparation (`manage_facilities`), season allocation (`manage_season_allocation`), and draft-assignment writes. These reject a Closed context season. They do **not** call `assertActive`.
  - **Active only:** confirmation, arrival, check-in, checkout, relocation, extension, and cancellation of an assignment. Call `SeasonGuard::assertActive`.
  - **Closed:** read-only. The separately controlled historical-correction exception in decision 6 stays **unapproved**. Do not implement it and do not bypass `SeasonGuard`.
- Keep Accommodation timeline separate from `Core/Audit`. No QR, offline sync, new notification channels, transport-point FK, or frontend in first backend slice.

## 4. Proposed domain model (NOT APPROVED schema)

All listed tables are **proposed names**, not permission to create migrations. Every table is tenant-owned; seasonal rows also carry `season_id`; use composite FKs and indexes to enforce identity consistency. The implementation must verify exact target keys and self-referential constraints in MySQL.

| Proposed table | Purpose and principal fields |
|---|---|
| `accommodation_facilities` | Persistent hotel/camp: `tenant_id`, `kind`, `code`, `name`, `is_active` |
| `accommodation_structures` | Optional building/floor hierarchy: `tenant_id`, `facility_id`, nullable `parent_id`, `kind`, `name` |
| `accommodation_units` | Persistent room/tent: `tenant_id`, `facility_id`, nullable `structure_id`, `unit_kind`, `code` |
| `accommodation_positions` | Persistent bed/tent position: `tenant_id`, `unit_id`, `code` |
| `accommodation_season_facilities` | Season's facility selection and optional audience restriction: `tenant_id`, `season_id`, `facility_id`, `audience`, `is_active` |
| `accommodation_season_units` | Season's unit selection: `tenant_id`, `season_id`, `season_facility_id`, `unit_id`, `allocation_mode`, `capacity`, `audience`, `is_active` |
| `accommodation_season_positions` | Enabled stable positions for season unit: `tenant_id`, `season_id`, `season_unit_id`, `position_id`, `is_active` |
| `accommodation_assignments` | One stay per SeasonPilgrim: season unit/optional position, stable unit reference, `booking_status`, `operational_status`, `starts_at`, `original_ends_at`, `effective_ends_at`, family-group snapshot, `checkout_source` |
| `accommodation_events` | Append-only timeline: assignment, event type, `occurred_at`, `recorded_at`, actor/source, reason, correlation/idempotency key, metadata |
| `accommodation_family_reviews` | Pending/resolved compatibility reviews when group membership diverges from confirmed snapshot |
| `accommodation_facility_scopes` | User + season + facility authorization scope |
| `accommodation_supervisor_scopes` | User + season-wide facility scope |
| `accommodation_cancellation_tasks` | Durable pending/succeeded/failed reconciliation of after-commit participation cancellations |
| `accommodation_pilgrim_locks` | Lock anchor only, not occupancy. One row per `(tenant_id, season_id, season_pilgrim_id)`. Unique on that triple. FK `(tenant_id, season_id, season_pilgrim_id)` → `season_pilgrims (tenant_id, season_id, id)` RESTRICT. Never updated with stay data |

**Relationship and constraint proposals:**

- Stable unit belongs to a stable facility. A season unit must reference a unit belonging to its season facility; a season position must reference a position belonging to the same stable unit. Enforce these with composite keys/constraints, not just application validation.
- Assignment references one `season_pilgrims` participation within the same tenant and season and the matching season-unit allocation. `unit_id` is a stable-unit reference for cross-season locking; verify it matches the season-unit's unit.
- Position-mode assignments require an enabled season position; capacity-mode assignments prohibit it. In either mode enforce season-unit capacity and, for position mode, per-position exclusivity.
- `family_group_id` on confirmed assignment is a snapshot. Foundation remains owner of current family membership. Confirmed family occupancy checks the snapshot under unit lock.
- Preserve both `original_ends_at` and `effective_ends_at` so early checkout does not erase the original plan. Never overwrite old timeline events; corrections append new events with an auditable projection update.
- Add necessary composite unique keys for FK targets and indexed overlap scans. MySQL `UNIQUE` cannot enforce arbitrary time-range non-overlap.

## 5. Booking and operational lifecycle

**Booking (proposed stored values):** `draft -> confirmed -> cancelled` only before check-in; `confirmed -> completed` after checkout.  
**Operational (proposed stored values):** `assigned -> arrived -> checked_in -> checked_out` or `assigned -> checked_in -> checked_out`. Arrival is not mandatory. Do not emit checkout for a stay that never checked in.

- Confirmation (**not Backend Sprint 1**): Active season; known gender; matching unit and optional facility audience; staff verb and facility or supervisor scope; no overlapping confirmed stay for that SeasonPilgrim; one atomic commit under the §6 lock order. Family exclusivity between two different family groups is provisional decision 12. **Whether a SeasonPilgrim with a null family group may be confirmed onto a Family unit is NOT DECIDED (§11).** A validation that rejects that null membership is a candidate only. It is not a product rule, and this bullet does not adopt it.
- Draft: can be edited without holding space. Revalidate on confirm.
- Family batch: resolve members to individual assignment rows; lock all affected resources deterministically; commit all or none.
- Early checkout: `effective_ends_at` becomes checkout time; original end preserved; future reservation freed; timeline records source and time.
- Relocation (**not Backend Sprint 1**): lock source and destination stable units in `id` order, then the pilgrim lock anchors (§6), shorten the previous effective interval and open the new interval in one transaction. A combined relocate and check-in writes two events.
- Extension: recheck capacity, time overlap, scope and eligibility; never silently rewrite historical events.
- Administrative participation cancellation: `assigned`/`arrived` confirmed stay -> cancelled; `checked_in` -> completed/checked_out with `checkout_source=automatic_cancellation`, immediate release of remaining interval; already terminal -> no-op. **Administrative checkout is NOT evidence of physical departure.**

## 6. Capacity, exclusivity and concurrency

**Proposed invariants:**

1. At every instant inside a confirmed effective interval, the peak number of confirmed stays on that season unit cannot exceed that season's capacity. Do not count non-overlapping stays inside a wider window as if they were simultaneous.
2. A position cannot have two overlapping confirmed effective stays; its unit's effective cap also applies.
3. No two confirmed effective intervals for one SeasonPilgrim overlap across any units.
4. For `audience=family`, overlapping confirmed stays whose snapshotted family groups differ are incompatible, even when numeric capacity remains (decision 12). A null snapshot compared with another null or with a family id is the unresolved case in §11, not this invariant.
5. Reject a season capacity decrease, position removal, or unit deallocation when it invalidates any affected confirmed stay.
6. A stable physical unit must not be overbooked across overlapping seasons. **The numeric cross-season formula is NOT APPROVED; see §11.**

Use the half-open overlap predicate `existing.starts_at < candidate.effective_ends_at AND existing.effective_ends_at > candidate.starts_at`. There is no `end` column. Occupancy uses `effective_ends_at`. Filter out drafts and cancelled bookings. For arbitrary time ranges, capacity checks must evaluate the **peak concurrent occupancy over the entire candidate interval**, not merely count all distinct assignments that intersect it (which would reject valid non-concurrent stays). Proposed technique: ordered interval boundary sweep, or an equivalent peak query, under the stable-unit lock.

**Cross-season overlap detection (mandatory) without a shared capacity formula.** Under the stable-unit lock, load confirmed effective intervals for that same `accommodation_units` row in **every** season, not only the context season. Detection is required before any confirmation. It does **not** approve a `physical_capacity` column or any numeric shared cap (§11). Fail closed: if detection finds an overlapping confirmed stay in another season, reject the confirmation while the formula is unresolved. Do not treat a season-local pass as permission to share the physical unit across overlapping seasons. If detection finds no overlapping confirmed stay in any other season, the cross-season formula is not required for that candidate. Do not invent a capacity number for that case either.

**Transaction/lock protocol (engineering proposal):** one outermost `DB::transaction(..., 3)`, matching `TransportTransaction::ATTEMPTS`. Never `lockForUpdate` on `season_pilgrims`. The pilgrim serialization row is `accommodation_pilgrim_locks` only.

Lock order, each group `orderBy('id')` or `orderBy('season_pilgrim_id')` then `lockForUpdate()`:

1. Every stable `accommodation_units` row the transaction will read or write, sorted by `id`. This includes the unit used for cross-season detection.
2. Every `accommodation_pilgrim_locks` row for an affected SeasonPilgrim, sorted by `season_pilgrim_id`. Ensure the anchor row exists first (unique insert, then lock). Take this lock only after step 1, never before, so a transaction that locks units and a transaction that locks the pilgrim cannot deadlock by inverted order.
3. Season-unit rows, then season-position rows, then assignment rows that the peak and overlap checks will use, each group sorted by `id`.

Recheck facility or supervisor scope at this write boundary for scoped verbs (§7). Deadlock retries re-read committed state. **Concurrency tests (confirmation slice, not Sprint 1), MySQL multi-process:** two confirms of one SeasonPilgrim onto two different units, exactly one commits; two confirms of different pilgrims onto one unit, ordered by the unit lock; a cross-season overlapping confirmed stay is detected and rejected while §11's formula is unresolved, without asserting a physical capacity.

**Cross-season recommendation, not decision:** give stable unit an explicit `physical_capacity` and enforce `season_occupancy <= season_capacity` plus `sum(overlapping all-season occupants) <= physical_capacity`; season capacity must not exceed physical capacity. This is a candidate resolution only. Do **not** add the column or ship the rule before design approval.

## 7. Staff authorization (engineering proposal)

Proposed permission strings (not yet seeded):

`accommodation.view`, `accommodation.manage_facilities`, `accommodation.manage_season_allocation`, `accommodation.draft`, `accommodation.confirm`, `accommodation.arrive`, `accommodation.check_in`, `accommodation.check_out`, `accommodation.relocate`, `accommodation.cancel_assignment`, `accommodation.extend`, `accommodation.correct_history`, `accommodation.manage_scopes`, `accommodation.resolve_family_review`.

Every request still checks the verb, tenant isolation (cross-tenant is 404), and, for seasonal rows, season isolation (cross-season is 404). Critical mutations are audited. No default role grants are approved.

**Tenant-level preparation, no facility-scope row required.** These verbs must not demand an existing `accommodation_facility_scopes` or `accommodation_supervisor_scopes` row. Requiring one would make the first facility, the first season allocation, and the first grant impossible.

- `accommodation.manage_facilities` — create and maintain stable facilities, optional structure, units, and positions.
- `accommodation.manage_season_allocation` — choose which of those units operate in the context season and store that season's mode, capacity, and audience. Capacity numbers are stored only. Sprint 1 does not enforce them against stays.
- `accommodation.manage_scopes` — grant and revoke facility scope and season supervisor scope. Revocation is itself audited.

**Facility-scoped operational and read verbs.** `view`, `draft`, `confirm`, `arrive`, `check_in`, `check_out`, `relocate`, `cancel_assignment`, `extend`, and `resolve_family_review` require a matching facility-scope row for that tenant, season, and facility, or a supervisor-scope row for that tenant and season. Supervisor scope widens facilities only. It never grants a verb. Relocation must authorize both source and destination, unless the actor has supervisor scope. Staff read APIs filter by the same scope. Hiding a button is not authorization. Same-tenant lack of scope is 403, not 404.

Scope for those verbs is checked again inside the write transaction after the §6 locks, so a revocation that has committed is visible. `manage_facilities`, `manage_season_allocation`, and `manage_scopes` do not take a facility-scope lock. They still reject a Closed context season (§3).

## 8. Cancellation reliability and family changes

**After-commit cancellation gap:** event dispatch occurs after Foundation commits, so the listener cannot make Foundation cancellation and accommodation release one transaction. Proposed `accommodation_cancellation_tasks` tracks retries and failure. Listener restores tenant context, locks the stable unit and assignment, applies transition idempotently, writes one timeline event per unique administrative operation, and marks task succeeded. Reconciliation/readiness checks must detect a cancelled participation with a still-open accommodation even if the listener never ran or failed before task creation. Closing must block on unresolved critical operations.

**Family change gap:** existing `SetSeasonPilgrimFamilyGroup` updates Foundation and audit but emits no domain event. Do not modify Foundation. Compare confirmed assignment family snapshot with current group on relevant accommodation actions and via an explicitly designed reconciliation mechanism; create a deduplicated pending review without cancelling accommodation. This approach is **not guaranteed to detect changes immediately** unless a scheduled/reliable comparison mechanism is implemented. Whether a pending review blocks specific operations is unresolved.

## 9. Season closure and historical corrections

**Preserved provisional blockers:** (a) open checked-in stays; (b) unresolved critical operations, including failed/pending cancellation reconciliation or cancelled participations with unreleased stays.

**NOT APPROVED:** drafts as closure warnings; family reviews or projection inconsistency as extra blockers; any precise post-Closed write mechanism. In Active seasons, corrections require separate permission, mandatory reason, immutable correction event and audit, with capacity revalidation. Closed seasons remain read-only in implementation until the exceptional write path is separately specified and approved; do not silently interpret the provisional exception as permission to bypass `SeasonGuard`.

## 10. Delivery slices and test gates (proposal, not authorization)

**Documentation gate first:** approve this ADR design and align module 18 `BUSINESS_RULES.md`, `DATA_MODEL.md`, `API.md`, `PERMISSIONS.md`, `TEST_PLAN.md`, `ACCEPTANCE_CRITERIA.md` before any migration. Explicitly supersede module 18's permissive capacity-reduction wording. Keep ADR-0017 and ADR-0018 untouched.

**Backend Sprint 1 (only this slice is in view).** Master data, season allocation, non-reserving drafts, and scope administration. Verbs: `view`, `manage_facilities`, `manage_season_allocation`, `draft`, `manage_scopes`.

In Sprint 1: stable facilities, optional hierarchy, units, positions, season allocations (capacity and audience stored, not enforced against stays), draft assignments that never consume capacity, facility and supervisor scope rows, scoped read of drafts and inventory, audit of those writes.

**Excluded from Sprint 1:** confirmation; peak capacity enforcement; position exclusivity; family exclusivity enforcement; check-in, checkout, and arrival; relocation; extension; automatic administrative checkout; the cancellation listener; closing-readiness contributors; correction events; `physical_capacity`.

Later slices, still not authorized, and each still blocked by the matching §11 rows:

2. Confirmed individual stays, only after the §11 blockers for confirmation are decided. Transactional peak capacity, position exclusivity, family exclusivity between different family groups, pilgrim overlap via `accommodation_pilgrim_locks`, append-only timeline, and mandatory cross-season **detection** that fail-closes while the formula is unresolved.
3. Arrival, check-in, checkout, early checkout, participation-cancellation handling, closing readiness, operational and deletion reference checkers.
4. Family atomic batch, relocation, extension, compatibility review, Active-season corrections.
5. Frontend only after backend contracts. QR, offline, notifications, and a transport-point link stay deferred.

**Sprint 1 tests:** cross-tenant 404; cross-season 404; missing verb 403; same-tenant out-of-scope 403 on `view` and `draft`; `manage_facilities` can create a facility with no scope row; `manage_scopes` can create the first grant with no scope row; a supervisor scope does not grant `manage_facilities`; scope revocation is visible to the next draft write; Draft and Active allow preparation and drafts; Closed rejects them; a confirm path is absent or rejects without inserting `booking_status=confirmed`; two drafts on one unit and interval both persist and do not reserve capacity; RESTRICT on a referenced unit; a season unit cannot reference a unit from another facility.

**Later-slice tests (not Sprint 1):** half-open boundaries; peak occupancy of staggered stays; same-position double-book; MySQL multi-process pilgrim double-book on two units; cross-season overlap **detected and rejected** with no `physical_capacity` assertion; family exclusivity for two different family groups; null family membership left unspecified until §11 is decided; atomic group failure; relocation scope; administrative checkout idempotency; correction immutability; closure blockers. SQLite-only tests do not prove MySQL locking.

## 11. Open items — NOT APPROVED / UNRESOLVED

These are documented for **one consolidated design review**, not a new sequence of user questionnaires:

| Item | Proposed safe direction (NOT DECIDED) | Impact |
|---|---|---|
| Cross-season numeric capacity | Physical cap on stable unit + per-season caps + combined peak occupancy. **Not adopted.** §6 requires overlap detection and fail-closed rejection until a formula is approved. Do not add `physical_capacity` | **Blocks confirmation and any shared capacity column.** Does not block Sprint 1 |
| Position-mode cap vs enabled position count | Enforce both; specify whether numeric cap may be lower than number of enabled positions | Blocks validation rules |
| Allowed SeasonPilgrim statuses for confirmation | Explicit eligibility matrix after checking Foundation states | Blocks confirm API |
| Family unit with null family-group membership | Candidate only: reject confirmation. **Not a product decision.** Decision 12 still forbids two different families from overlapping. §5 does not require a family group at confirmation | Blocks Family confirmation. Does not block Sprint 1 |
| Checkout before start / after planned end | Reject impossible times; require explicit exceptional path for retroactive cases | Blocks edge transitions |
| Same-pilgrim relocation into own occupied interval | Treat source shortening + destination booking as one atomic validated operation | Blocks relocation edge case |
| Position inventory edits / capacity history | Disallow edits conflicting with confirmed intervals; preserve historical configuration/version evidence | Blocks some admin writes |
| Family review gating | Preserve stay; avoid auto-cancel; define which future writes need review | Operational ambiguity |
| Cancelled participation's draft rows | Keep historical draft or cancel with distinct event; never confirm later | Lifecycle detail |
| Cancellation listener reliability | Durable retry/reconciliation beyond synchronous listener, idempotency key convention | Critical integration reliability |
| Post-close corrections / extra closing warnings | Defer; not approved in decision group 14 | Do not implement |
| UTC-to-local / Hijri presentation, API error codes | Document after contract review | API/UI detail |

**Design-level finding:** peak concurrent occupancy is the capacity check for a later confirmation slice. Same-pilgrim serialization uses `accommodation_pilgrim_locks` after stable-unit locks (§6). A naive count of intersecting rows, or unit locks alone, is not sufficient. Cross-season numeric capacity remains unresolved; detection fail-closes and does not authorize those confirmations.

## 12. Consequences and acceptance gate

**Benefits:** preserves Foundation boundaries; stable inventory with season-specific configuration; explicit family and staff restrictions; auditable administrative checkout; deterministic occupancy and historical traceability.

**Costs/risks:** more tables and policy checks; cross-season lock complexity; after-commit cancellation gap; physical occupancy may differ from administratively released capacity; no family-change event; closed-season correction policy incomplete.

**Approval conditions:** decide the §11 items that still block confirmation (cross-season formula, position-mode cap, eligible SeasonPilgrim statuses, null family membership, checkout edges, same-pilgrim relocation, family-review gating, cancellation idempotency). Validate the composite FK list against the repository. Reconcile module 18, including its permissive capacity-reduction wording, in a separate edit. Then mark this ADR `Accepted` in a separate change. **Until then: no migrations, no application code, no permission seeding, no API changes, no edits to locked modules.** Sprint 1 in §10 is a proposed limit, not authorization to build.

# Accommodation & Camps — ADR-0019 Final Review

> **Type:** Review only. ADR-0019 was not modified. No module document, application code, or migration was changed.
> **Date:** 2026-10-15
> **Subject:** `docs/10-decisions/ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md` (21,248 bytes; status line: PROPOSED / REVIEW ONLY — NOT ACCEPTED)
> **Compared with:** architecture draft (13 decisions), verification report, reconciliation, module 18, Season & Pilgrims code, Transportation integration patterns.
> **This review does not accept the ADR.**

## Executive verdictc   

**REQUIRES CORRECTIONS**

ADR-0019 preserves all 13 provisional product decisions and correctly refuses to treat its tables, verbs, and cross-season formula as approved. It also corrects two unsafe ideas in the reconciliation: capacity must be peak occupancy over an interval, not a count of every intersecting row, and two confirms for one SeasonPilgrim on different units are not serialized by unit locks alone.

It is not ready to mark Accepted. Section 7 applies facility scope to every request, which makes the first facility and the first scope grant impossible. Section 5 states a Family-unit membership rule that section 11 still calls undecided. Section 6’s overlap predicate names `candidate.end`, which is not a column. Section 10 requires a cross-season overlap test while section 11 says that formula is not decided and blocks the capacity schema. Those are corrections to the ADR text. Until they are made, and until the section 11 items that the ADR itself marks as blocking are decided, Backend Sprint 1 must not include confirmation or capacity enforcement.

---

## 1. The 13 provisional product decisions

Section 2 matches `docs/reviews/ACCOMMODATION_CAMPS_ARCHITECTURE_DRAFT.md` lines 13–25. None is dropped or reversed. Classification: **provisional product decision**, not an accepted ADR rule. ADR lines 14–15 say appearing in sections 4–10 does not promote a proposal.

| # | Preserved in ADR §2 | Drift inside later sections |
|---|---|---|
| 1 | Hotels/camps, capacity or explicit positions, buildings and floors optional. Lines 18, 48–51. | None. |
| 2 | Drafts do not consume capacity. No holds. Lines 19, 77, 95. | None. |
| 3 | Gregorian UTC, optional Hijri display, half-open intervals, gaps allowed, no overlapping confirmed stays for one SeasonPilgrim. Line 20. | Predicate on line 95 uses `candidate.end` instead of `effective_ends_at`. See C1. |
| 4 | Arrival optional; cancel before check-in even after arrival; checkout only after check-in; relocate+check-in is one action and two events. Lines 21, 73–80. | None. |
| 5 | Cancel before check-in; administrative checkout after check-in; history and source kept; one row per pilgrim in a family batch; unknown gender blocks confirmation. Lines 22, 82. | Draft rows of a cancelled participation stay unresolved (§11). That does not contradict decision 5, which names confirmed assignments. |
| 6 | Draft / Active / Closed split. Closure blocked by open checked-in stays and unresolved critical operations. Closed-season correction mechanism stays unapproved. Lines 23, 39, 117–119. | None. §9 correctly forbids implementing the exception. |
| 7 | Separate draft, confirm, check-in, checkout, relocate, and correction permissions. Facility scope vs season supervisor scope. Lines 24, 103–107. | §7 applies scope to every verb, including facility creation. See C2. |
| 8 | Reject overbooking; atomic group confirm; reject capacity reductions that hit a confirmed stay; append-only corrections. Lines 25, 92, 119. | Peak-occupancy wording on line 95 is the right reading of “reject overbooking.” Module 18 still says the opposite about reductions. Documentation conflict D1. |
| 9 | Family units may mix genders of one family; a family may span units; unknown gender blocks confirmation; audience on the unit; optional facility restriction. Line 26. | §6 invariant 4 does not say Family audience allows both genders. See C3. |
| 10 | Stable facilities, rooms, and tents; season-specific capacity and audience; history kept. Lines 27, 48–54. | Cross-season numeric rule is explicitly not decided (§6 line 93, §11). Consistent with not inventing a formula. |
| 11 | Early checkout keeps the planned end and frees the future; relocation atomic; extension rechecks capacity, overlap, and permission; corrections append events. Lines 28, 79–81. | Edge times and relocation onto the same pilgrim’s own interval stay unresolved (§11). |
| 12 | Different families cannot overlap on a Family unit; one member may stay alone; membership change raises a review and does not cancel. Lines 29, 91, 113. | §5 line 76 says “valid family group” at confirmation. §11 still says a null family group is not decided. See C4. |
| 13 | Scope is tenant + season + facility, or tenant + season for a supervisor. Relocation needs both ends or supervisor scope. Revocation hits the next operation. Lines 30, 107. | Recheck inside the transaction is stated (line 97). The scope bootstrap problem is C2. |

---

## 2. Database model, isolation, foreign keys, indexes

**Engineering proposal, not an approved schema.** ADR lines 42–44 say the names are not permission to migrate.

What is sound:

- Stable rows (`accommodation_facilities`, `accommodation_units`, `accommodation_positions`) have no `season_id`. Season rows allocate them. That implements decision 10 and rejects the verification report’s season-only place table.
- `structure_id` is nullable. Buildings and floors are not mandatory. Decision 1.
- Assignments point at `season_pilgrims` in the same tenant and season, not at a current-room column. ADR line 34. Matches ADR-0017 and `SeasonPilgrimsSchemaTest` forbidding `current_room_id`, `current_bed_id`, and `current_accommodation_id`.
- `original_ends_at` and `effective_ends_at` are both kept. Decision 11.
- `family_group_id` is a snapshot. Foundation stays the owner of membership. ADR lines 67, 113. `SetSeasonPilgrimFamilyGroup.php` lines 55–77 writes the foundation column and an audit record only.
- History uses RESTRICT, matching `2026_10_05_100006_create_season_pilgrims_table.php` lines 56–75.
- Tenant from `TenantContext`, season from `X-Season-Id` / `SeasonContext`. Cross-tenant and cross-season access is 404. Lack of scope is 403. ADR line 34. `ResolveSeasonContext.php` lines 36–39 already 404s a bound model from another season.
- MySQL UNIQUE cannot express range exclusion. ADR line 69. Same limit as ADR-0018 T7.

What is not specified, so a migration written from this ADR would still guess:

- The exact unique targets. Foundation children use `(tenant_id, id)` and `(tenant_id, season_id, id)` (`season_pilgrims` migration lines 78–79; `seasons` migration line 44, `seasons_tenant_id_id_unique`). The ADR says “add necessary composite unique keys” (line 69) and “verify exact target keys” (line 44) but never lists them.
- The composite FK that forces a season unit’s `unit_id` to belong to the season facility’s facility, and a season position’s `position_id` to belong to that unit (line 64 states the rule, not the key columns).
- Index column order for the peak query and the pilgrim-overlap query.
- A cycle check on `accommodation_structures.parent_id`.

These are required corrections before any migration (C5). They do not contradict the 13 decisions.

Seasons do not forbid overlapping calendars. `UNIQUE (tenant_id, hajj_year)` and `UNIQUE (tenant_id, active_flag)` (`2026_10_05_100000_create_seasons_table.php` lines 46–47) allow a Draft season’s dates to overlap a Closed season. Decision 10 plus that schema is why a season-local capacity check is not enough. The ADR states this (line 93) and refuses a formula (line 99). That refusal is correct. It also means confirmation cannot ship (section 6 below).

---

## 3. Cross-season capacity, half-open intervals, concurrent confirmation

**Provisional:** half-open `[start_at, end_at)`, Gregorian UTC, gaps allowed, one SeasonPilgrim cannot overlap themselves (decision 3).

**Engineering proposal:** peak concurrent occupancy, not a count of every row that intersects the candidate (ADR lines 95–96). That proposal is right. Two confirmed stays on one unit with capacity 1, one ending at the instant the next starts, must both fit. A stay from day 1–2 and a stay from day 3–4 must not block a candidate that merely spans both if capacity is 1 and they never coincide. The reconciliation’s intersecting-row count would reject the second case. ADR-0019 corrects that. Do not treat the correction as a new product decision; it is how decision 8 has to be evaluated.

**Not decided, and the ADR says it blocks the capacity schema:** the number used when two seasons put different capacities on one physical unit (lines 93, 99, 139). The `physical_capacity` sentence on line 99 is explicitly a candidate, not a decision. This review does not adopt it.

**Concurrency, engineering proposal:** `DB::transaction(..., 3)` matches `TransportTransaction::ATTEMPTS` (`TransportTransaction.php` lines 17–28). Lock stable units by id first (line 97). ADR-0018 H4 is the precedent: a plain read under REPEATABLE READ can miss a committed row, so `lockForUpdate` is required.

**Gap the ADR names but does not close:** unit locks do not serialize two confirms of the same SeasonPilgrim onto two different units (lines 97, 152). The “shared stable lock anchor” is not a table or a column. Locking `season_pilgrims` with `lockForUpdate` would couple accommodation to `CancelSeasonPilgrim`, which already locks that row (`CancelSeasonPilgrim.php` lines 36–37). The anchor should be an accommodation-owned row, taken after stable-unit locks, never before them. Otherwise a transaction that locks the pilgrim first and a transaction that locks units first can deadlock. See C6.

**Internal inconsistency:** section 10 line 131 requires a cross-season overlap test. Section 11 line 139 says the formula is not decided and blocks the capacity schema. A test cannot define the formula. See C7.

---

## 4. Family exclusivity and membership review

Decision 12 is preserved: different family snapshots cannot overlap on a Family unit even when numeric capacity remains (line 91); a review does not cancel the stay (line 113).

`SetSeasonPilgrimFamilyGroup` emits no domain event (file lines 60–77; the only foundation domain event is `SeasonPilgrimCancelled`). The ADR forbids adding one (line 113). That is the right boundary. Comparison of the snapshot with the current `season_family_group_id` on later accommodation actions, plus a deduplicated `accommodation_family_reviews` row, is an engineering proposal. The ADR admits it is not immediate unless a separate comparison job exists (line 113). Whether a pending review blocks check-in, checkout, extension, or relocation is unresolved (line 146). That gating is not one of the 13 decisions. Do not implement a block or a non-block as if it were.

Decision 9 (both genders on a Family unit) is in section 2 and missing from the section 6 invariants. See C3.

---

## 5. Lifecycle, relocation, early checkout, administrative checkout

Stored values in section 5 are an engineering proposal. They match decisions 4, 5, and 11:

- Arrival is skippable.
- Cancel is only before check-in, including from `arrived`.
- Checkout is refused unless `checked_in`.
- Early checkout moves `effective_ends_at` only.
- Relocation locks both stable units in id order, shortens the old effective interval, opens the new one, and writes two events when check-in is combined.
- `checked_in` plus `SeasonPilgrimCancelled` becomes `checked_out` / `completed` with `checkout_source=automatic_cancellation`. Line 82 states this is not physical departure. That keeps decision 5 and the accepted risk in the architecture draft section 9.
- Already cancelled or checked out is a no-op. Idempotency is required. The key format is still unresolved (line 148).

`SeasonPilgrimCancelled` implements `ShouldDispatchAfterCommit` (`SeasonPilgrimCancelled.php` lines 14–15). The listener cannot share the foundation transaction. ADR lines 38 and 111 say this. `accommodation_cancellation_tasks` plus a readiness query that finds a cancelled participation with a still-open stay, even when no task row exists (line 111), is the right engineering response. Transportation’s listener is synchronous and has the same gap (`HandleSeasonPilgrimCancelled.php`); accommodation must not copy only the happy path.

`SeasonGuard::assertWritable` is true for Draft and Active (`SeasonGuard.php` lines 17–21; `SeasonStatus::isWritable` is everything except Closed). `assertActive` is the correct call for confirmation and movements (ADR line 39). The ADR does not say which guard applies to facility master data and season allocation. Those are Draft-season work under decision 6. See C8.

Unresolved edges that block those operations, already listed in §11, and not decided here:

- Which `SeasonPilgrim.status` values may be confirmed.
- Checkout before `starts_at` or after `original_ends_at`.
- Relocation into an interval the same pilgrim already occupies.
- What happens to draft rows when the participation is cancelled.

---

## 6. Facility-scoped authorization and revocation

`TenantPermissionPolicy` checks the verb and the same `tenant_id` only (lines 15–27). No module grants by facility. The two scope tables are required by decisions 7 and 13. They do not exist in code. Proposed verb strings (lines 105–106) follow the `transportation.*` underscore style and are not seeded. `PermissionCatalog.php` has no `accommodation.*` entries. ADR line 107: no role grants. That matches ADR-0017’s note that role templates beyond the owner are a separate decision.

Revocation rechecked inside the write transaction (line 97) matches decision 13. Reads filtered by scope (line 107) match the draft section 6. UI `can()` is not authorization.

**C2 is a text defect:** line 107 says each request checks facility or supervisor scope. `accommodation.manage_facilities` creates a facility that has no scope row yet. `accommodation.manage_scopes` creates the first grant. If both verbs require an existing scope row, Sprint 1 cannot create inventory or authorize anyone. Supervisor scope must not invent verbs (line 107 is correct on that point). Scope applies to view, draft, confirm, arrive, check-in, checkout, relocate, cancel, and extend. It must not be required to create the facility or to grant scope.

Same-tenant, out-of-scope access as 403, and cross-tenant as 404, matches the security baseline. Do not return 404 for a same-tenant facility the user is simply not scoped to; that would hide a permission failure behind a missing-object code.

---

## 7. Foundation, closing readiness, and audit

| Contract | ADR | Code | Result |
|---|---|---|---|
| Do not write `SeasonPilgrim.status`, family membership, or transportation rows | Lines 35, 38 | `CancelSeasonPilgrim` is the only writer of cancellation; transportation listener does not write foundation rows | Preserved |
| No current-room column | Line 35 | Schema tests forbid those columns | Preserved |
| Closing readiness tag `season_pilgrims.closing_readiness` | Line 37 | `SeasonClosingReadinessContributor::evaluate(Season): list<ReadinessResult>` | Signature fits. Blocker **codes** are not named. See C9. |
| Operational-reference and deletion-reference checkers | Line 37 | Transportation registers both from its provider and does not get imported by the foundation | Pattern fits. Whether a draft row blocks hard delete is unstated. |
| Do not register an activation requirement | Line 37 | `season_pilgrims.activation_requirements` exists and is unused by transportation | Correct. Not one of the 13 decisions. |
| `SeasonPilgrimCancelled` after commit, `runAsTenant` | Lines 38, 111 | Event file lines 8–28; transportation listener lines 42–47 | Fits. Idempotency key still open. |
| Transport arrival does not check in | Line 38 | ADR-0018 and module 18 line 102 | Preserved |
| Timeline separate from `Core/Audit` | Line 40 | ADR-0015; transportation keeps both | Preserved |
| No QR, offline, new notification channel, transport-point FK, or UI in the first backend slice | Line 40 | ADR-0018 I14: facility link is additive and later | Preserved |

Decision 6 blockers that are provisional and should be implemented only after the ADR is corrected and accepted: open checked-in stays; unresolved critical operations, including a pending or failed cancellation task and a cancelled participation whose stay is still open (lines 117).

Still **not approved**, and the ADR does not sneak them in: draft assignments as closure warnings (decision group 14); family reviews as closure blockers; projection inconsistency as a closure blocker; any Closed-season write (lines 119).

---

## 8. Module 18 conflicts

These are documentation conflicts. They become implementation blockers only if code is written while module 18 still states the old rule. ADR section 10 lines 123–124 already require a documentation gate first and leave ADR-0017 and ADR-0018 untouched. This review does not edit module 18.

| ID | Module 18 | ADR-0019 | Kind |
|---|---|---|---|
| D1 | `BUSINESS_RULES.md` lines 84–86: a capacity decrease may stand, and only a further over-capacity insert is rejected | Decision 8 and line 92: reject the reduction when it conflicts with a confirmed stay | Direct rule conflict. ADR must win after module 18 is superseded. Not a reason to change decision 8. |
| D2 | Line 41: calendar, inclusive end, and gaps are TBD | Decision 3 | Draft supersedes. Module 18 is stale. |
| D3 | Line 60: audience location TBD | Decision 9 | Draft supersedes. |
| D4 | Line 106: whether arrival is required is TBD | Decision 4: arrival is optional | Draft supersedes. |
| D5 | Line 87: position-mode capacity formula TBD | §11: still unresolved whether the numeric cap may be lower than the enabled position count | Still open in both places. Blocks position-mode validation, not the decision to support both modes. |
| D6 | `PERMISSIONS.md` lines 10–21: do not seed verbs | §7 lists verbs and says they are not seeded | No conflict if the catalog is not edited before module 18 is updated. |
| D7 | `DATA_MODEL.md` line 51: overlap mechanism TBD | Locks plus a peak check; UNIQUE rejected | ADR chooses the mechanism as an engineering proposal. |
| D8 | `ACCEPTANCE_CRITERIA.md` line 25: implementation blocked on TBD schemas and verbs | §12: no code until this ADR is accepted and module 18 is aligned | The block still stands. |

---

## 9. Findings

### Critical

None that contradict a preserved product decision. The document does not authorize implementation (lines 6 and 160).

### High — required corrections in the ADR

| ID | Where | Finding |
|---|---|---|
| C2 | §7 line 107 | Facility or supervisor scope on every request makes `manage_facilities` and `manage_scopes` impossible for the first rows. Scope must apply to operational and read verbs only. Creating a facility and granting scope are tenant-level verbs. |
| C4 | §5 line 76 vs §11 line 142 | Confirmation text requires a “valid family group” where exclusivity applies. Section 11 still says rejecting a null family group is not decided. An implementer can treat §5 as the rule. One of those sentences has to be rewritten so they agree. |
| C6 | §6 lines 97 and 152 | The pilgrim lock anchor is unnamed. Specify an accommodation-owned row keyed by `(tenant_id, season_id, season_pilgrim_id)`, locked after stable units, in a global id order. Do not take `lockForUpdate` on `season_pilgrims` as the design. |
| C7 | §10 line 131 vs §11 line 139 | A required cross-season test cannot exist before the formula is decided. Remove that test from the gate, or decide the formula. Do not let the test invent `physical_capacity`. |
| C8 | §3 line 39 vs decision 6 | State the guard for master data and season allocation: allowed in Draft and Active, rejected in Closed, and not forced through `assertActive`. Confirmation, arrival, check-in, checkout, relocation, and extension use `assertActive`. |

### Medium — required corrections in the ADR

| ID | Where | Finding |
|---|---|---|
| C1 | §6 line 95 | The predicate uses `candidate.end`. The columns are `starts_at`, `original_ends_at`, and `effective_ends_at`. The occupying end is `effective_ends_at` on both sides. |
| C3 | §6 lines 88–91 | Invariant 4 does not say `audience=family` accepts both `male` and `female` from that family, while `male` and `female` audiences reject the other known gender. Unknown gender fails every audience, including Family (decision 5). Without this sentence, a gender check will reject a mixed family. |
| C5 | §4 lines 44, 64, 69 | Name the unique targets and the composite FKs, including `(tenant_id, season_id, season_pilgrim_id)` → `season_pilgrims (tenant_id, season_id, id)`, `(tenant_id, season_id)` → `seasons (tenant_id, id)`, and the facility/unit/position identity FKs on line 64. Name the overlap indexes. State that `parent_id` cannot cycle. |
| C9 | §11 line 150 | Readiness `code` values are a backend contract (`ReadinessResult` has `code`, `level`, `message`). They are grouped with Hijri display and API error codes. Split them. Do not implement closure until the two decision-6 codes are named. |

### Low

- Hijri rendering and the tenant timezone used only for display remain open (decision 3 stores UTC). Not a Sprint 1 backend blocker.
- No default role bundle. Correct. Do not copy transportation grants onto `tenant_owner` inside implementation.
- Same-tenant scope denial as 403 reveals that the facility id exists. That matches current same-tenant 403 behavior. It is not cross-tenant leakage.

---

## 10. Remaining unapproved decisions

Do not implement these. Do not treat the “proposed safe direction” column in §11 as accepted.

| Item | ADR | Effect |
|---|---|---|
| Cross-season numeric capacity | §11 line 139. `physical_capacity` is a candidate only (line 99). | Blocks confirmation and any capacity column that implies the formula. |
| Position-mode numeric cap versus enabled position count | Line 140 | Blocks position-mode validation. |
| SeasonPilgrim statuses allowed to confirm | Line 141 | Blocks the confirm action. |
| Null `season_family_group_id` on a Family unit | Line 142 | Blocks Family confirmation. Must be aligned with C4. |
| Checkout before start or after planned end | Line 143 | Blocks those edge transitions. |
| Relocation onto the same pilgrim’s overlapping interval | Line 144 | Blocks that edge. Ordinary relocation to a free unit is specified. |
| Capacity and position edits versus historical evidence | Line 145 | Blocks some allocation updates. Decision 8 already rejects a reduction that hits a confirmed stay. |
| What a pending family review blocks | Line 146 | Do not block or allow later writes by assumption. Do not auto-cancel. |
| Draft rows after participation cancellation | Line 147 | Do not confirm them. Whether they are cancelled is open. |
| Idempotency key format and retry mechanism | Line 148 | Blocks the cancellation listener. |
| Post-close corrections and decision-group-14 warnings | Line 149 | Do not implement. |
| Hijri display and API error-code catalog | Line 150 | Does not block master data. Readiness codes are C9, not this row. |

---

## 11. Backend Sprint 1 scope

Sprint 1 is not authorized by this review or by ADR-0019 §12. After C2, C5, and C8 are written into the ADR, and after module 18 is aligned for the verbs and the master-data rules actually used, the only safe first backend slice is ADR §10 item 1. Confirmation (item 2) stays out until the blocking rows in section 10 of this review are decided.

**In Sprint 1**

- `accommodation_facilities`, optional `accommodation_structures`, `accommodation_units`, `accommodation_positions`.
- `accommodation_season_facilities`, `accommodation_season_units`, `accommodation_season_positions` with season-specific audience, mode, and capacity stored but not yet enforced against stays.
- Draft assignment rows only. They must not be counted by any capacity or overlap query. No confirm, check-in, checkout, relocate, extend, or cancel-assignment transition.
- `accommodation_facility_scopes` and `accommodation_supervisor_scopes`.
- Verbs used by this slice only: `view`, `manage_facilities`, `manage_season_allocation`, `draft`, `manage_scopes`.
- Policies: verb, tenant 404, season 404, and facility scope on view and draft. `manage_facilities` and `manage_scopes` do not require a pre-existing scope row (C2).
- Draft season and Active season may write master data, allocations, and drafts. Closed season rejects those writes (C8).
- Audit on create, update, deactivate, and scope grant/revoke. No timeline events yet, because operational transitions are not in this slice.
- No listener, no closing-readiness contributor, no permission seed until `PERMISSIONS.md` lists these verbs, no UI, no QR, no offline, no notifications, no transport-point column, no `physical_capacity`.

**Acceptance tests for that slice**

- Cross-tenant facility, unit, allocation, draft, and scope ids return 404.
- A draft or allocation bound to another season than `X-Season-Id` returns 404.
- Missing verb returns 403. Same-tenant facility outside the user’s scope returns 403 on view and draft.
- Revoke a scope in a committed transaction, then a second transaction’s draft write fails.
- A user with `manage_facilities` and no scope row can create a facility. A user with only `draft` cannot.
- A supervisor scope does not allow `manage_facilities` or `confirm`.
- Closed season: master-data and draft writes fail. Draft season: a confirm endpoint is absent or returns 422 without creating a confirmed row.
- Creating a draft does not change any capacity figure and does not block a second draft on the same unit and interval.
- Deleting or deactivating a unit that a season allocation references is RESTRICT, not cascade.
- A structure parent cycle is rejected.
- A season unit whose unit belongs to a different facility than the season facility is rejected by the database.
- Unknown gender is not evaluated in this slice because confirmation is absent. Do not add a partial gender check that later contradicts decision 9.

**Not in Sprint 1, even after the ADR text fixes**

Peak capacity, position exclusivity, family exclusivity, pilgrim double-book, cross-season occupancy, group confirm, relocation, early checkout, administrative checkout, closing readiness, and correction events. Each is blocked by at least one row in section 10.

---

## 12. Files

**Updated:** `docs/reviews/ACCOMMODATION_CAMPS_ADR0019_FINAL_REVIEW.md`

**Not modified:** `docs/10-decisions/ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md`, module 18, the architecture draft, the verification report, the reconciliation, application code, migrations, Foundation, and Transportation.

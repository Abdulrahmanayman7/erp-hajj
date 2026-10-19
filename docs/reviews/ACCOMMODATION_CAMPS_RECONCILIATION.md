# Accommodation & Camps — Architecture Reconciliation

> **Type:** Documentation only. No application code, migrations, module documents, or ADRs were changed.
> **Date:** 2026-10-15
> **Branch / HEAD:** `develop` @ `736c776dd1d8ca6ddcb53239ab8cd2e87a9943fa`
> **Draft:** `docs/reviews/ACCOMMODATION_CAMPS_ARCHITECTURE_DRAFT.md` (PROPOSED, not an ADR).
> **Compared with:** `docs/reviews/ACCOMMODATION_CAMPS_ARCHITECTURE_VERIFICATION.md`, `docs/09-modules/18-accommodation-and-camps/`, and the Season & Pilgrims and Transportation code.

This file does not approve the draft. It keeps the draft’s 13 provisional product decisions as written. Where the verification report or module 18 recommends something else, the conflict is listed. The older text is not used to replace a draft decision.

Labels:

- **PROVISIONAL PRODUCT DECISION** — one of the 13 groups in the draft, section 2. Not an ADR.
- **NOT APPROVED** — the draft itself says this is not agreed.
- **ENGINEERING PROPOSAL** — a schema or mechanism that can implement a provisional decision. Not a product decision.
- **UNRESOLVED** — the draft asks for this and does not answer it.
- **IMPLEMENTED** — present in code today.

---

## 1. Verdict

The 13 decisions can sit on the current foundation without editing Season & Pilgrims or Transportation: composite foreign keys, `season.context`, after-commit `SeasonPilgrimCancelled`, closing-readiness and reference-checker tags, and InnoDB row locks with deadlock retry.

They cannot sit on the verification report’s recommended tables. That report scoped every place to one season, left family exclusivity and facility grants out, and treated automatic checkout as the wrong default. Those recommendations contradict decisions 5, 6, 7, 10, 12, and 13. This reconciliation follows the draft on those points.

Module 18 still marks the same topics TBD, and one capacity rule in module 18 contradicts decision 8. Module 18 was not edited. An ADR has to replace that text later; this file does not.

Implementation is still not authorized. The draft’s approval gate (section 12) stands. Several engineering details below are unresolved, including cross-season capacity when two seasons give the same unit different numbers.

---

## 2. The 13 provisional product decisions (preserved)

Quoted in substance from the draft, section 2. None of these is silently narrowed by the verification report.

| # | Provisional product decision | Must not be replaced by |
|---|---|---|
| 1 | Hotels: rooms with numeric capacity or explicit beds. Camps: tents with numeric capacity or explicit positions. Buildings and floors are optional. | Verification: do not add buildings or floors. |
| 2 | Draft assignments do not consume capacity. No temporary holds. | Verification: omit draft rows from the first table. |
| 3 | Store Gregorian date-times. Hijri is display only. No overlapping confirmed stay for one pilgrim, including across facilities. Gaps are allowed. Interval is half-open `[start_at, end_at)`. | Module 18: calendar, inclusive end, and own-stay overlap are TBD. |
| 4 | Facility arrival is optional before check-in. An assignment may be cancelled before check-in, including after arrival. Checkout requires a prior check-in. Relocation and check-in may be one user action and must be two events. | Module 18: whether arrival is required is TBD. |
| 5 | SeasonPilgrim cancellation: before check-in, cancel the confirmed assignment; after check-in, check out and release capacity immediately, without a physical-departure confirmation. Keep history and record the administrative source. Family bulk assignment creates individual assignments linked to the family group. Unknown gender blocks confirmation, including Family units. | Verification section 7.3: do not auto-checkout a checked-in stay; raise a review instead. |
| 6 | Draft season: facility preparation and assignment drafts only. Active season: confirmation and movements. Closed season: read-only except separately controlled historical corrections. Open checked-in stays and unresolved critical operations block season closure. | Using `SeasonGuard::assertWritable` alone, which treats Draft and Active as the same. The “except corrections” mechanism is **NOT APPROVED** (section 4 below). |
| 7 | Separate permissions for draft and confirm; separate permissions for check-in, checkout, and relocation. Staff are limited to assigned facilities or to a season-wide supervisor scope. Historical correction needs its own permission, a mandatory reason, and audit. | Verification: tenant-wide verbs only; no facility grant. |
| 8 | Reject capacity conflicts. Group confirmation is atomic. Disallow a capacity reduction that conflicts with any affected confirmed stay. Historical correction is append-only. | Module 18: a capacity decrease may reveal over-capacity and is itself allowed. |
| 9 | A Family unit may hold male and female pilgrims of the same family. A family may span multiple units. Unknown gender cannot confirm. Audience is defined on the room or tent, with optional facility restrictions. | Module 18: audience location is TBD. |
| 10 | Facility and room/tent identities persist across seasons. Each season allocates a subset of units and has its own capacity and audience. Historical seasons keep their configuration. | Verification: `season_id` required on the place row, so a room cannot exist outside one season. |
| 11 | Early checkout releases the future reservation immediately and keeps the original end. Relocation is atomic. Extension rechecks capacity, overlap, and authorization. A historical period correction is an append-only correction event. | — |
| 12 | A Family unit is not shared by different families during overlapping stays. One family member may occupy a Family unit alone. A later family-membership change raises a compatibility review and does not cancel the assignment. | Verification: do not enforce family exclusivity in the first schema. |
| 13 | Staff scope is tenant + season + facility. Supervisor scope is tenant + season. Relocation needs source and destination scope, or supervisor scope. Revocation applies to new operations immediately. | `TenantPermissionPolicy`, which only checks permission plus same `tenant_id`. |

Decision 5’s administrative checkout without physical departure is an accepted product risk in the draft, section 9. The reconciled model records the source and does not call it a verified departure.

---

## 3. What the draft does not approve

These stay **NOT APPROVED**. They are not promoted because they would make closure or closed-season writes easier.

| Item | Draft evidence | Treatment here |
|---|---|---|
| Decision group 14: draft assignments are warnings, not closure blockers | Section 8, section 11 | **NOT APPROVED.** Do not implement that warning as a decided rule. |
| Post-close correction mechanism, its permission impact, and its effect on readiness reports | Section 8: “proposed default; NOT approved.” Section 11. | **NOT APPROVED** as a mechanism, even though decision 6 names “separately controlled historical corrections” as the exception to read-only. The sentence in decision 6 is preserved. The write path on a Closed season is not specified and must not be built from this file. |
| Extra closure blockers beyond decision 6 | Section 8 adds unresolved family-compatibility reviews and inconsistent occupancy projections. Decision 6 only names open checked-in stays and unresolved critical operations. | The two decision-6 blockers are provisional product decisions. Family-review and projection blockers are **NOT APPROVED** extras. |
| Precise cross-season capacity formula when two seasons set different capacities on one unit | Section 11, first checklist item | **UNRESOLVED.** Section 7 does not invent the formula. |
| Gating of operations after a family-membership change | Section 9: “exact gating … is a proposed default needing documentation.” | **UNRESOLVED.** Decision 12 only requires a review and forbids automatic cancellation. |
| Early checkout before the planned start, late checkout, retroactive correction, relocation into a unit the same pilgrim already occupies | Section 11 | **UNRESOLVED.** |
| Whether position inventory can change during a season, and how capacity changes are versioned | Section 11 | **UNRESOLVED.** |

---

## 4. Conflicts

### 4.1 Draft versus the verification report

The verification report is an audit of module 18 before this draft existed. Where it says “do not do X until product decides,” and the draft now decides X, the draft wins as the provisional decision. The verification’s evidence about the code remains valid.

| Verification position | Draft decision | Reconciliation |
|---|---|---|
| Places are season-scoped only (verification section 5.1, 6.7) | 10 | Stable facility, unit, and position rows have no `season_id`. Season rows allocate them. |
| Do not store drafts in the first schema (section 5.3) | 2 | Draft rows exist and are excluded from capacity and overlap counts. |
| Do not auto-checkout; review a checked-in stay (section 7.3) | 5 | Checked-in participation cancellation writes an administrative checkout and releases the effective interval. |
| Do not enforce family exclusivity (section 6.6) | 12 | Family-audience units reject overlapping confirmed stays from different family groups. |
| Facility grants are out of scope (section 8.3) | 7, 13 | A scope table is required. `TenantPermissionPolicy` is not sufficient. |
| Half-open intervals need later approval (section 6.3) | 3 | Half-open `[start_at, end_at)` is the provisional rule. |
| Position capacity = 1 and place capacity ignored (section 6.4) | 1 and draft section 4 | Both apply: the unit has an effective capacity, and a position cannot be used twice at once. |
| Buildings and floors omitted (section 5.5) | 1 | Optional structure only. No mandatory foreign key on the room or tent. |

### 4.2 Draft versus module 18

Module 18 remains the last text under `docs/09-modules/`. It was not updated. These are documentation conflicts, not permission to ignore the draft.

| Module 18 | Draft | Conflict |
|---|---|---|
| `BUSINESS_RULES.md` lines 84–86: lowering capacity may reveal over-capacity and must not by itself be rejected; further over-capacity inserts are rejected | Decision 8: disallow a reduction that conflicts with any affected confirmed stay | Direct contradiction. Reconciled behavior follows decision 8. Module 18 still says the opposite until an ADR edits it. |
| `BUSINESS_RULES.md` line 41: Hijri vs Gregorian, inclusive end, gaps TBD | Decision 3 | Draft decides. Module 18 still says TBD. |
| `BUSINESS_RULES.md` line 60: audience location TBD | Decision 9 | Draft places audience on the room/tent, optionally on the facility. |
| `BUSINESS_RULES.md` line 87: position-mode capacity formula TBD | Draft section 4 | Still partly open: both a unit cap and position exclusivity are required; whether the unit cap must equal the position count is **UNRESOLVED**. |
| `BUSINESS_RULES.md` line 106: arrival required TBD; stored enum names TBD | Decision 4 | Arrival is optional. Stored strings below are an engineering proposal. |
| `BUSINESS_RULES.md` lines 163–167: keep history after cancellation; effect on an open stay not stated | Decision 5 | Draft specifies cancel versus administrative checkout. History is still kept. |
| `DATA_MODEL.md` line 51: overlap mechanism TBD | Draft sections 4 and 9 | Application locks, not a UNIQUE on a time range. Matches transportation ADR-0018 T7. |
| `PERMISSIONS.md` lines 10–21: verbs not decided; do not seed the catalog | Decisions 7 and 13, draft section 6 | Verbs are now proposed by the draft. They are still not seeded. Catalog edit is not authorized by this file. |
| `BUSINESS_RULES.md` line 80: do not add planning tables until columns are named | Decisions 2, 10, 12 | Draft assignments, season allocations, and family reviews are named by the draft. Demand, queue, and auto-allocation are still unnamed and stay out. |
| `ACCEPTANCE_CRITERIA.md` line 25: implementation blocked on TBD schemas and verbs | Draft section 12 | Still blocked. This reconciliation is not the ADR. |

Module 18 rules that agree with the draft and stay in force: accommodation does not write `SeasonPilgrim.status` or transportation rows; no `current_room_id` / `current_bed_id` / `current_accommodation_id`; transport arrival does not check in; relocation and check-in are distinct events; timeline is not `audit_logs`; cross-tenant access is 404; offline, QR, and new notification channels are not in the first slice.

### 4.3 Draft versus the code

| Need from the draft | What the code does | Gap |
|---|---|---|
| Closed season read-only; Draft vs Active split (decision 6) | `SeasonStatus::isWritable()` is true for Draft and Active (`SeasonStatus.php` lines 11–13). `SeasonGuard::assertWritable` does not distinguish them (`SeasonGuard.php` lines 17–21). `assertActive` exists (lines 24–30). | Accommodation needs its own guard. Calling only `assertWritable` would allow confirmation during Draft. |
| One Active season; seasons may still overlap on the calendar (decision 10, draft section 4) | `UNIQUE (tenant_id, active_flag)` and `UNIQUE (tenant_id, hajj_year)` (`2026_10_05_100000_create_seasons_table.php` lines 44–47). No constraint that `operational_starts_on` / `operational_ends_on` of two seasons are disjoint. | Cross-season overlap of the same physical unit is possible. Season-local capacity is not enough (draft section 4). |
| Family-membership change raises a review (decision 12) | `SetSeasonPilgrimFamilyGroup` updates the column and writes an audit record only (`SetSeasonPilgrimFamilyGroup.php` lines 43–77). The only domain event in the module is `SeasonPilgrimCancelled`. | No family-changed event. Do not add one inside the foundation as part of accommodation. Detection strategy is in section 8. |
| Unknown gender blocks confirmation (decisions 5 and 9) | `pilgrims.gender` is nullable `male` \| `female` (`PilgrimGender.php` lines 5–8). | The check belongs in accommodation confirmation. The foundation will not grow a NOT NULL gender for this. |
| Reliable handling of cancellation (draft section 7) | Event implements `ShouldDispatchAfterCommit` and carries scalar ids (`SeasonPilgrimCancelled.php` lines 8–28). Transportation’s listener is synchronous, uses `runAsTenant`, and is not `ShouldQueue` (`HandleSeasonPilgrimCancelled.php` lines 40–47). | After commit, a crash before the listener leaves occupancy unchanged. There is no outbox. |
| Facility scope (decisions 7 and 13) | `TenantPermissionPolicy` allows the permission and same `tenant_id` only (`TenantPermissionPolicy.php` lines 15–27). | A new grant table and policy are required. No existing module does this. |
| Closing readiness (decision 6) | `SeasonClosingReadinessContributor::evaluate(Season): list<ReadinessResult>` with levels `ok`, `warning`, `blocking` (`SeasonClosingReadinessContributor.php` lines 14–19; `ReadinessLevel.php` lines 10–13). Tag `season_pilgrims.closing_readiness`. | Contract matches. Blocker codes are not defined. Extras in draft section 8 stay **NOT APPROVED**. |
| Operational and deletion references | Checkers exist. Transportation treats any passenger row as a reference. Deletion checkers are not used for deactivation. | Accommodation should report any assignment or timeline row. Register from the accommodation provider only. |
| Activation “must have accommodation” | `SeasonPilgrimActivationRequirement` exists. | Not in the 13 decisions. Do not register a contributor. |

---

## 5. Reconciled physical model (engineering proposal)

**ENGINEERING PROPOSAL.** Table names are not approved schema (draft sections 3 and 11). They exist so the 13 decisions have a place to live. Every business table is tenant-owned. `tenant_id` comes from `TenantContext`. ON DELETE is RESTRICT. Composite unique keys `(tenant_id, id)` and, when season-scoped, `(tenant_id, season_id, id)` follow `season_pilgrims` (migration lines 77–79).

### 5.1 Stable identity (no `season_id`) — decision 10

| Table | Role | Required columns |
|---|---|---|
| `accommodation_facilities` | Hotel or camp. Persists across seasons. | `tenant_id`, `kind` (`hotel` \| `camp`), `code`, `name`, `is_active`. Unique `(tenant_id, code)`. |
| `accommodation_structures` | Optional building or floor. | `tenant_id`, `facility_id`, nullable `parent_id`, `kind` (`building` \| `floor`), `name`. Self-FK on `(tenant_id, facility_id, id)`. **No unit is required to point here** (decision 1, draft section 9). |
| `accommodation_units` | Stable room or tent. | `tenant_id`, `facility_id`, nullable `structure_id`, `unit_kind` (`room` \| `tent`), `code`. Unique `(tenant_id, facility_id, code)`. |
| `accommodation_positions` | Stable bed or tent position. | `tenant_id`, `unit_id`, `code`. Unique `(tenant_id, unit_id, code)`. |

`structure_id` is nullable. A room does not need a building.

**UNRESOLVED, not a column yet:** a single physical capacity on `accommodation_units` used when two seasons overlap. See section 7. Adding it now would decide the formula the draft left open.

### 5.2 Season allocation — decision 10

| Table | Role | Required columns |
|---|---|---|
| `accommodation_season_facilities` | This facility operates in this season. Optional facility audience restriction (decision 9). | `tenant_id`, `season_id`, `facility_id`, nullable `audience` (`male` \| `female` \| `family`), `is_active`. Unique `(tenant_id, season_id, facility_id)`. FK `(tenant_id, season_id)` → `seasons`. FK `(tenant_id, facility_id)` → facilities. |
| `accommodation_season_units` | Season-specific mode, capacity, and audience. Historical seasons keep their own row. | `tenant_id`, `season_id`, `season_facility_id`, `unit_id`, `allocation_mode` (`capacity` \| `position`), `capacity` (NOT NULL when mode is `capacity`; see position note), `audience` (`male` \| `female` \| `family`), `is_active`. Unique `(tenant_id, season_id, unit_id)`. |
| `accommodation_season_positions` | Which stable positions are available on that season unit. | `tenant_id`, `season_id`, `season_unit_id`, `position_id`, `is_active`. Unique `(tenant_id, season_id, position_id)`. |

Changing `capacity`, `audience`, or `allocation_mode` on a season unit is an update to that season’s row only. It must not rewrite another season’s row (decision 10). A reduction uses the rule in section 7 (decision 8).

Position mode: assignments target one season position, and that position cannot have two confirmed effective intervals (draft section 4). The season unit still has an effective capacity check. Whether `capacity` must equal the number of active season positions is **UNRESOLVED** (module 18 line 87, still open after the draft).

### 5.3 Assignments — decisions 2, 3, 5, 11, 12

`accommodation_assignments`. One row per SeasonPilgrim per stay. Bulk family assignment inserts one row per pilgrim with the same family-group reference (decision 5). No current-room column on `season_pilgrims`.

| Column | Rule |
|---|---|
| `tenant_id`, `season_id`, `season_pilgrim_id` | FK `(tenant_id, season_id, season_pilgrim_id)` → `season_pilgrims (tenant_id, season_id, id)` |
| `season_unit_id` | The season allocation, not the bare unit, so audience and capacity are the season’s |
| `season_position_id` | NULL in capacity mode. Required in position mode |
| `unit_id` | Denormalized stable unit, same tenant, matching the season unit. Used as the cross-season lock target |
| `booking_status` | `draft` \| `confirmed` \| `cancelled` \| `completed` |
| `operational_status` | `assigned` \| `arrived` \| `checked_in` \| `checked_out` |
| `starts_at` | Gregorian UTC. Original start. Half-open. |
| `original_ends_at` | Never moved by early checkout (decision 11) |
| `effective_ends_at` | Occupancy end. Early checkout and administrative checkout move this and leave `original_ends_at` |
| `family_group_id` | Nullable snapshot copied at confirmation from `season_pilgrims.season_family_group_id`. Not a live relation the module updates when the foundation moves the pilgrim |
| `checkout_source` | NULL, or `staff`, or `automatic_cancellation` (decision 5) |

Booking and operational status stay separate (draft section 5).

Capacity, place overlap, pilgrim overlap, and family exclusivity count only `booking_status = confirmed` rows that are not operationally released. A draft never counts (decision 2). A `cancelled` booking does not count. A `completed` booking counts only up to `effective_ends_at`.

Half-open overlap:

```text
existing.starts_at < candidate.effective_ends_at
AND existing.effective_ends_at > candidate.starts_at
```

Confirmed stays of one `season_pilgrim_id` use the same predicate across units (decision 3). Gaps are allowed because the predicate does not require the next start to equal the previous end.

### 5.4 Timeline — decisions 8 and 11

`accommodation_events`. Insert only.

`event_type`: `drafted`, `confirmed`, `arrived`, `checked_in`, `checked_out`, `relocated`, `cancelled`, `extended`, `corrected`.

Also: `occurred_at`, `recorded_at`, `actor_id` nullable, `source` (`staff` \| `automatic_cancellation` \| `correction`), `reason` nullable, `correlation_id` nullable, `idempotency_key` nullable, `metadata` JSON.

Unique `(tenant_id, idempotency_key)` where the key is not null, so a replayed administrative checkout does not insert a second checkout event (draft section 5).

A correction event does not UPDATE a previous event (decision 8). How the projection applies `corrected` is **UNRESOLVED** for closed seasons (section 3). For an Active season, decision 11 allows the append-only correction event; applying it must recheck capacity and must not rewrite the old row.

### 5.5 Family review — decision 12

`accommodation_family_reviews`: `tenant_id`, `season_id`, `assignment_id`, `season_pilgrim_id`, `previous_family_group_id`, `current_family_group_id`, `status` (`pending` \| `resolved`), `resolved_at`, `resolved_by`.

Creating a review does not change `booking_status` or `operational_status`.

Whether a pending review blocks check-in, checkout, or extension is **UNRESOLVED** (draft section 9). It is not a decision-6 closure blocker unless a later approval says so (section 3).

### 5.6 Staff scope — decisions 7 and 13

| Table | Meaning |
|---|---|
| `accommodation_facility_scopes` | One user, one season, one facility. Unique `(tenant_id, season_id, user_id, facility_id)`. |
| `accommodation_supervisor_scopes` | One user, one season, all facilities in that season. Unique `(tenant_id, season_id, user_id)`. |

Supervisor scope does not grant extra verbs (draft section 6). It widens the facility set. Revocation is deletion or `revoked_at` checked inside the write transaction, not only at request start (decision 13).

### 5.7 Cancellation processing — decision 5 and draft section 7

`accommodation_cancellation_tasks`: `tenant_id`, `season_id`, `season_pilgrim_id`, `status` (`pending` \| `succeeded` \| `failed`), `attempts`, `last_error`, `correlation_id`. Unique `(tenant_id, season_pilgrim_id)`.

This is the visible “unresolved critical operation” decision 6 can block on. It is an engineering proposal for the after-commit gap, not a new product rule.

---

## 6. Permissions (engineering proposal)

Namespace `accommodation.*`, matching `transportation.*` underscore verbs in `PermissionCatalog.php` lines 180–189. **Do not seed** until an ADR copies these strings into module 18 `PERMISSIONS.md`. The draft said final strings must follow repository conventions; these do.

| Verb | Decision |
|---|---|
| `accommodation.view` | Draft section 6. Reads still require facility or supervisor scope (decision 13). |
| `accommodation.manage_facilities` | Stable facilities, optional structure, units, positions. |
| `accommodation.manage_season_allocation` | Season facility and season unit configuration, including capacity and audience. |
| `accommodation.draft` | Create and edit drafts. Separate from confirm (decision 7). |
| `accommodation.confirm` | Confirm one stay or an atomic group (decisions 7 and 8). |
| `accommodation.arrive` | Optional arrival (decision 4). |
| `accommodation.check_in` | Requires prior confirmation. Does not require arrival (decision 4). |
| `accommodation.check_out` | Requires `checked_in` (decision 4). |
| `accommodation.relocate` | Source and destination scope, or supervisor scope (decision 13). |
| `accommodation.cancel_assignment` | Before check-in only, including after arrival (decision 4). |
| `accommodation.extend` | Extension rechecks capacity, overlap, and this verb (decision 11). |
| `accommodation.correct_history` | Append-only correction, mandatory reason, audit (decisions 7, 8, 11). Not a license to write a Closed season until that mechanism is approved (section 3). |
| `accommodation.manage_scopes` | Grant and revoke facility and supervisor scopes. |
| `accommodation.resolve_family_review` | Resolve a compatibility review without cancelling the stay (decision 12). |

No `camps.*` split (module 18, and the draft does not ask for one).

Enforcement, in order, on every read and write:

1. Verb via the policy.
2. Same `tenant_id` or 404.
3. Season context matches the row or 404 (`ResolveSeasonContext.php` lines 36–39).
4. Facility scope: the user has a row in `accommodation_facility_scopes` for that season and facility, or a row in `accommodation_supervisor_scopes` for that season. Otherwise 403.
5. Repeat step 4 inside the transaction after locks, so a revocation committed by another request is visible (decision 13).

The cancellation listener does not pass through `Gate`. It runs inside `TenantContext::runAsTenant`, as transportation does, and writes `source = automatic_cancellation`.

Role grants are not in the 13 decisions. Do not attach these verbs to `tenant_owner` or any other template in this file.

---

## 7. Capacity, locks, and cross-season overlap

**ENGINEERING PROPOSAL** for decisions 2, 3, 8, 10, 11, and 12. The cross-season numeric formula is **UNRESOLVED**.

### 7.1 What counts

Inside one season unit, at any instant inside a half-open confirmed effective interval:

- Drafts do not count (decision 2).
- Confirmed occupants must be ≤ that season unit’s `capacity`.
- A position-based unit also rejects two confirmed stays on the same season position.
- A season unit with `audience = family` rejects two confirmed stays whose snapshotted `family_group_id` values differ, even when capacity remains (decision 12). Null versus a family id is a difference. A pilgrim with no family group confirming onto a Family unit is **UNRESOLVED**: decision 12 allows one member alone, which implies membership, but it does not say the confirmation is forbidden when `season_family_group_id` is null.
- Male or female audience rejects a known gender that does not match. Unknown gender fails confirmation for every audience, including Family (decisions 5 and 9).
- Optional facility audience, when set, is an additional restriction (decision 9).

One SeasonPilgrim cannot have two confirmed effective intervals that overlap, on any unit (decision 3).

### 7.2 Capacity reduction — decision 8, not module 18

A change that lowers season-unit capacity, deactivates a season position, or removes a unit from the season is rejected when any confirmed effective interval still needs the removed capacity. Module 18 lines 84–86 allow the decrease and only block the next insert. That sentence is a conflict (section 4.2). This model follows decision 8.

### 7.3 Lock order and atomic group confirm — decision 8

One `DB::transaction($callback, 3)`, same retry count as `TransportTransaction::ATTEMPTS`.

Lock order, always `orderBy('id')` then `lockForUpdate()`:

1. Stable `accommodation_units` rows for every unit in the group, sorted by id. This is the cross-season resource.
2. `accommodation_season_units` rows for those units in the context season, sorted by id.
3. Season position rows, if any, sorted by id.
4. Existing confirmed assignment rows on those units whose effective intervals intersect, sorted by id.
5. Existing confirmed assignment rows for the candidate `season_pilgrim_id` values, sorted by pilgrim id.

Group confirmation locks the full set, then validates every member, then writes all rows or rolls all of them back (decision 8). Relocation locks the source unit and the destination unit in id order in the same transaction, closes the old effective interval, opens the new one, and inserts two timeline events when check-in is combined (decisions 4 and 11).

### 7.4 Cross-season formula — UNRESOLVED

Decision 10 gives each season its own capacity. Draft section 4 says the same physical unit must not be overbooked across overlapping seasons, and section 11 says the formula is not resolved.

This file does not choose among:

- the physical cap is the minimum of the overlapping seasons’ capacities;
- the stable unit has its own cap, and each season allocation must be ≤ that cap, and overlapping occupants from all seasons count against it;
- seasons are assumed not to overlap in time, which the schema does not enforce.

Until that choice is approved, a migration must not add a silent `physical_capacity` and treat it as decided. The lock on the stable unit is still required so that whatever formula is approved can run under the same lock.

---

## 8. Lifecycle

**PROVISIONAL PRODUCT DECISION** unless marked otherwise.

### 8.1 Season phase — decision 6

| Season status | Allowed accommodation writes |
|---|---|
| Draft | Facility preparation, season allocation, assignment drafts. Not confirmation, arrival, check-in, checkout, relocation, or extension. |
| Active | Confirmation and operational transitions, plus drafts. Decision 6 does not forbid drafts during Active. |
| Closed | Reads. Historical correction writes are named in decision 6 and are **NOT APPROVED** as a mechanism (section 3). No other writes. |

`SeasonGuard::assertActive` is the right foundation call for confirmation and movements. Draft-only actions use `assertWritable` and then reject Active-only operations themselves when the status is Draft. Closed always fails `assertWritable`.

Which SeasonPilgrim statuses may be confirmed is **UNRESOLVED**. Decision 5 talks about cancellation of a participation. The draft section 4 says “valid SeasonPilgrim status” and does not list the values. Do not assume `registered` and `active` only, and do not assume `completed` is allowed.

### 8.2 Assignment

```text
booking:     draft → confirmed → cancelled     (cancel only before check-in)
             confirmed → completed             (after checkout)

operational: assigned → arrived → checked_in → checked_out
             assigned → checked_in → checked_out
```

Arrival is optional (decision 4). Checkout from `assigned` or `arrived` is rejected. Cancel from `checked_in` is rejected; that path is checkout.

Early checkout sets `effective_ends_at` to the checkout occurrence time, sets `operational_status = checked_out`, sets `booking_status = completed`, and leaves `original_ends_at` (decision 11). Capacity after that instant is free.

**UNRESOLVED:** checkout time before `starts_at`, checkout after `original_ends_at`, and relocation onto an interval the same pilgrim already holds (draft section 11).

### 8.3 SeasonPilgrim cancellation — decision 5

Listener registered only in a future accommodation provider: `Event::listen(SeasonPilgrimCancelled::class, ...)`.

1. `runAsTenant` for `$event->tenantId`.
2. Insert or reuse `accommodation_cancellation_tasks` for that `season_pilgrim_id` (unique key).
3. For each assignment, lock the stable unit, then the assignment.
4. Draft: does not hold capacity. The draft does not say to delete it. **UNRESOLVED** whether the draft is cancelled or left. It must not start counting as capacity.
5. Confirmed and not checked in (`assigned` or `arrived`): set `booking_status = cancelled`, append one `cancelled` event with `source = automatic_cancellation`, copy `reason` and `correlation_id` from the event. Row kept.
6. `checked_in`: set `operational_status = checked_out`, `booking_status = completed`, `checkout_source = automatic_cancellation`, `effective_ends_at` to `$event->cancelledAt` when that instant falls inside the interval. Append one `checked_out` event with that source. Do not describe it as physical departure (draft section 9). If a checkout event with the same idempotency key exists, do nothing.
7. Already `checked_out` or `cancelled`: no-op.
8. Mark the task `succeeded`. On failure, mark `failed` and allow retry. A `pending` or `failed` task is an unresolved critical operation under decision 6.

The foundation cancel is already committed. This handler cannot share that transaction (draft section 7). Closing readiness must also treat “SeasonPilgrim `cancelled` and a confirmed or checked-in assignment still open” as blocking, so a listener that never ran is still visible. That query is an engineering consequence of decision 6 plus `ShouldDispatchAfterCommit`. It does not add a product blocker beyond “unresolved critical operations.”

### 8.4 Family membership — decision 12

There is no domain event to subscribe to (`SetSeasonPilgrimFamilyGroup.php` writes audit only).

**ENGINEERING PROPOSAL, not a new foundation contract:** when accommodation confirms, checks in, checks out, extends, or relocates, it reads the current `season_family_group_id`. If it differs from the assignment snapshot, it inserts a pending review if one is not already open, and it does not cancel the stay. A periodic comparison is not available from the foundation. Gender changes on `pilgrims` have no domain event either; the 13 decisions do not require a gender-change review. That gap is **UNRESOLVED** if product later wants the same treatment.

What a pending review blocks, other than “do not auto-cancel,” is **UNRESOLVED**.

---

## 9. Integration contracts

| Contract | Use | Do not |
|---|---|---|
| `season.context` / `X-Season-Id` | Every seasonal route | Trust `season_id` from JSON |
| `SeasonGuard::assertWritable` / `assertActive` | As split in section 8.1 | Treat Draft as operational |
| `SeasonClosingReadinessContributor` | Report decision-6 blockers: open checked-in stays; pending or failed cancellation tasks; cancelled participations whose stays are not yet cancelled or administratively checked out | Add family-review or projection blockers until approved (section 3). Do not register draft-assignment warnings as if decision 14 passed |
| `SeasonPilgrimOperationalReferenceChecker` | True when any assignment or event exists | |
| `SeasonResourceDeletionChecker` | True when any accommodation row references the season, program, pilgrim group, or family group | Block deactivation |
| `SeasonPilgrimActivationRequirement` | Do not register | |
| `SeasonPilgrimCancelled` | Section 8.3 | Write `season_pilgrims` or delete history |
| Transportation | No writes. Arrival does not check in | Add `transport_points` facility FK in the first slice (ADR-0018 I14) |
| `Core/Audit` | Critical mutations, including scope grant/revoke and corrections. Secrets never in values | Replace the timeline |
| Documents, notifications, QR, offline | Out of the first slice | New `NotificationType` |

---

## 10. Closure and corrections (kept provisional)

| Rule | Status |
|---|---|
| Open checked-in stays block Active → Closed | **PROVISIONAL PRODUCT DECISION** (decision 6) |
| Unresolved critical operations block closure. Engineering meaning: cancellation task `pending` or `failed`, or a cancelled participation with a stay not yet cancelled or administratively checked out | **PROVISIONAL PRODUCT DECISION** for the words in decision 6. The task table is an **ENGINEERING PROPOSAL** |
| Draft assignments warn and do not block | **NOT APPROVED** (decision group 14) |
| Unresolved family reviews block closure | **NOT APPROVED** (draft section 8 extra) |
| Inconsistent projection blocks closure | **NOT APPROVED** (draft section 8 extra) |
| Append-only correction while the season is Active, with `accommodation.correct_history`, a reason, and audit, rechecking capacity | **PROVISIONAL PRODUCT DECISION** (decisions 7, 8, 11) |
| The same correction after the season is Closed | Named in decision 6, mechanism **NOT APPROVED** (section 3). No closed-season write is authorized by this file |

---

## 11. Remaining engineering gaps

These are not answered by the 13 decisions or by the code.

1. Cross-season capacity formula when season capacities differ (section 7.4).
2. Checkout before `starts_at`, checkout after `original_ends_at`, and relocation onto the same pilgrim’s existing interval.
3. Whether a capacity-mode number on a position-mode unit must equal the position count.
4. Whether position availability may change mid-season, and whether past capacity values are versioned or only current.
5. Which `SeasonPilgrim.status` values may be confirmed.
6. Whether a pilgrim with null `season_family_group_id` may confirm onto a Family unit.
7. What a pending family review blocks besides automatic cancellation.
8. Whether drafts of a cancelled SeasonPilgrim are cancelled, left as drafts, or rejected later.
9. Idempotency key format for staff retries versus the cancellation listener.
10. Deadlock test plan: MySQL multi-process, two groups, two seasons overlapping one unit. Not run. **NOT VERIFIED.**
11. Hijri display formatting and the tenant timezone used to render UTC instants. Storage is Gregorian UTC (decision 3). Display is unspecified.
12. Error codes for overlap, family exclusivity, scope denial, and illegal transition. Module 18 `API.md` lines 33 still say TBD. The draft does not assign codes.
13. No outbox table exists. Retry depends on the listener plus the readiness query in section 8.3. A queue worker is an engineering choice, not a product decision.

---

## 12. Risks

| ID | Risk | Severity | Why |
|---|---|---|---|
| R1 | Building the verification’s season-only place table | **Critical** | Breaks decision 10 and makes cross-season identity impossible without a later migration. |
| R2 | Following module 18’s “allow capacity decrease” sentence | **Critical** | Contradicts decision 8. |
| R3 | Leaving a checked-in pilgrim in place after participation cancellation | **Critical** | Contradicts decision 5. Capacity stays held. |
| R4 | Auto-checkout recorded as physical departure | **High** | Draft section 9 accepts the capacity release and forbids that label. |
| R5 | Facility checks only in the UI | **High** | Decision 13 requires the server, including inside the transaction. Current policies do not do this. |
| R6 | Confirming during Draft because `assertWritable` is true | **High** | Decision 6. |
| R7 | Counting drafts toward capacity | **High** | Decision 2. |
| R8 | UNIQUE index used as the overlap constraint | **High** | MySQL cannot express it (ADR-0018 T7). Two concurrent confirms can both pass a plain read (ADR-0018 H4). |
| R9 | Listener-only cancellation handling with no readiness query | **High** | After-commit delivery can be missed. Decision 6 then fails open. |
| R10 | Inventing a foundation family-changed event | **High** | Modifies the locked module. Section 8.4 avoids that. |
| R11 | Implementing post-close correction or decision-14 warnings as if approved | **High** | Section 3. |
| R12 | Picking a cross-season capacity number inside a migration | **High** | Section 7.4 is unresolved. |
| R13 | Scope revocation checked only at the start of the request | **Medium** | Decision 13. |
| R14 | N+1 occupancy reads before the read-model query is specified | **Medium** | Draft leaves the projection versus query choice open for corrections. |

---

## 13. Smallest sequence that still respects the 13 decisions

Not authorization to code. The draft section 12 gate remains: one design approval after this review.

1. ADR that copies the 13 decisions into module 18 and explicitly replaces `BUSINESS_RULES.md` lines 84–86. Leave section 3 of this file marked not approved inside that ADR.
2. Decide section 11 items 1–8. Item 1 blocks any uniqueness or capacity column that implies a cross-season formula.
3. First backend slice from the draft section 10, adjusted so drafts, stable units, season allocations, family exclusivity, facility scope, and the cancellation checkout are included. They are decisions, not later extras. UI, QR, offline, notifications, and a transport-point foreign key stay out.
4. MySQL multi-process tests for unit capacity, pilgrim overlap, family exclusivity, and two concurrent group confirms.
5. Family batch confirmation, relocation, extension, and Active-season correction events as the draft’s later slices. Closed-season correction stays out until section 3 is approved.

---

## 14. Files read

- `docs/reviews/ACCOMMODATION_CAMPS_ARCHITECTURE_DRAFT.md` (the 13 decisions and sections 3–12)
- `docs/reviews/ACCOMMODATION_CAMPS_ARCHITECTURE_VERIFICATION.md`
- `docs/reviews/ACCOMMODATION_CAMPS_PRE_IMPLEMENTATION_AUDIT.md` (background only; not treated as the product decisions)
- `docs/09-modules/18-accommodation-and-camps/` — `BUSINESS_RULES.md`, `DATA_MODEL.md`, `PERMISSIONS.md`, `API.md`, `ACCEPTANCE_CRITERIA.md`, `README.md`
- `backend/app/Modules/SeasonPilgrims/Events/SeasonPilgrimCancelled.php`
- `backend/app/Modules/SeasonPilgrims/Actions/SeasonPilgrims/CancelSeasonPilgrim.php`
- `backend/app/Modules/SeasonPilgrims/Actions/SeasonPilgrims/SetSeasonPilgrimFamilyGroup.php`
- `backend/app/Modules/SeasonPilgrims/Support/SeasonGuard.php`
- `backend/app/Modules/SeasonPilgrims/Enums/SeasonStatus.php`
- `backend/app/Modules/SeasonPilgrims/Enums/PilgrimGender.php`
- `backend/app/Modules/SeasonPilgrims/Enums/ReadinessLevel.php`
- `backend/app/Modules/SeasonPilgrims/Readiness/SeasonClosingReadiness.php`
- `backend/app/Modules/SeasonPilgrims/Contracts/SeasonClosingReadinessContributor.php`
- `backend/app/Modules/SeasonPilgrims/Contracts/SeasonPilgrimActivationRequirement.php`
- `backend/app/Modules/SeasonPilgrims/Policies/TenantPermissionPolicy.php`
- `backend/app/Modules/SeasonPilgrims/Context/ResolveSeasonContext.php`
- `backend/database/migrations/2026_10_05_100000_create_seasons_table.php`
- `backend/database/migrations/2026_10_05_100006_create_season_pilgrims_table.php`
- `backend/app/Modules/Transportation/Listeners/HandleSeasonPilgrimCancelled.php`
- `backend/app/Modules/Transportation/Support/TransportTransaction.php`
- `backend/app/Core/Authorization/PermissionCatalog.php` (verb style)
- `docs/10-decisions/ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md` T7, H4, I14

## 15. File created

`docs/reviews/ACCOMMODATION_CAMPS_RECONCILIATION.md`

No other file was created or modified.

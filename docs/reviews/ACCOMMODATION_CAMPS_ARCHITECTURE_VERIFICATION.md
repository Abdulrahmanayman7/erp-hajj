# Accommodation & Camps — Architecture Verification

> **Type:** Verification only. No application code, migrations, ADRs, or other documentation were changed to produce this file.
> **Date:** 2026-10-15
> **Repository:** `erp-hajj` (`d:\erp-hajj-1`)
> **Branch / HEAD:** `develop` @ `736c776dd1d8ca6ddcb53239ab8cd2e87a9943fa`
> **Architecture draft:** **MISSING.** `ACCOMMODATION_CAMPS_ARCHITECTURE_DRAFT.md` is not in the repository (searched by filename and by content). This report does **not** verify that draft.
> **What was verified:** the proposed model in `docs/reviews/ACCOMMODATION_CAMPS_PRE_IMPLEMENTATION_AUDIT.md` (sections 8–19) and the approved module text in `docs/09-modules/18-accommodation-and-camps/`, checked against the Season & Pilgrims and Transportation implementations.

Classification: **IMPLEMENTED**, **DOCUMENTED**, **PROPOSED** (recommended here, not approved), **UNKNOWN**, **NOT VERIFIED**.

---

## 1. Verdict

The extension points, tenancy, season context, composite foreign keys, policy base, audit, and InnoDB lock style in the current backend can host Accommodation without editing Season & Pilgrims or Transportation.

The physical schema, permission verb strings, calendar, transition graph, family-exclusivity rule, and the effect of `SeasonPilgrimCancelled` are still unspecified. Automatic checkout is **not** a documented accommodation rule. Facility-scoped authorization does **not** exist in the current RBAC model.

**Implementation cannot safely begin.** The tables and verbs in this report are a recommended shape for a later product decision. They are not an approved schema.

---

## 2. Draft status

| Item | Result | Class |
|---|---|---|
| `ACCOMMODATION_CAMPS_ARCHITECTURE_DRAFT.md` | Not found anywhere in the repository | **MISSING** |
| Pre-implementation audit | Present at `docs/reviews/ACCOMMODATION_CAMPS_PRE_IMPLEMENTATION_AUDIT.md` (597 lines). Its domain model is explicitly conceptual and labeled “REQUIRES APPROVAL” | DOCUMENTED as an audit, not an approved schema |
| Module 18 | Eight files, status “Specified” / conceptual, last updated 2026-10-04. `DATA_MODEL.md` lines 3–4 and 14–16: no migration; concept names are not physical tables | DOCUMENTED |
| Accommodation code | `backend/bootstrap/providers.php` lines 8–12 register Season and Transportation only. No `app/Modules/Accommodation/` | IMPLEMENTED (absence) |

---

## 3. Compatibility of the proposed components

The audit’s proposed components (audit sections 8–9) were checked against code. “Compatible” means the existing system can accept the component later without a foundation or transportation edit. It does not mean the component is approved to build.

| Proposed component | Compatible with current code? | Evidence | Class |
|---|---|---|---|
| Independent occupancy context; no `current_room` / `current_bed` / `current_accommodation` on foundation rows | Yes. Schema tests already forbid those columns and accommodation-like table names | `SeasonPilgrimsSchemaTest.php` lines 84–96; ADR-0017 lines 72, 80, 139 | IMPLEMENTED constraint |
| Season-scoped stays via `X-Season-Id`, not a client `season_id` | Yes. Middleware `season.context` resolves the header inside the tenant and returns 404 for a foreign season or a bound model from another season | `ResolveSeasonContext.php` lines 12–41 | IMPLEMENTED |
| Composite FKs `(tenant_id, season_id, id)` onto `season_pilgrims` | Yes. Target unique key already exists | `2026_10_05_100006_create_season_pilgrims_table.php` lines 77–79 | IMPLEMENTED |
| Register closing readiness, operational-reference, and deletion checkers from a new provider | Yes. Transportation already does this. The foundation does not import the caller | `TransportationServiceProvider.php` lines 44–48; contracts under `SeasonPilgrims/Contracts/` | IMPLEMENTED |
| Listen to `SeasonPilgrimCancelled` without editing the foundation | Yes. The event is after-commit and carries scalar ids. The foundation registers no listener | `SeasonPilgrimCancelled.php` lines 8–28; `CancelSeasonPilgrim.php` lines 17–20, 61–71 | IMPLEMENTED |
| `SeasonGuard` on accommodation writes | Yes, as a call from accommodation actions. Closed seasons are not writable | `SeasonGuard.php` lines 17–21; `SeasonStatus.php` lines 11–13 (`isWritable` is everything except `closed`) | IMPLEMENTED |
| Append-only timeline plus `Core/Audit` | Yes. Transportation already separates them (ADR-0018 T6). Accommodation docs require the same split | `BUSINESS_RULES.md` lines 119–130 | DOCUMENTED + pattern IMPLEMENTED |
| Overlap via `lockForUpdate` and a 3-attempt transaction, not a UNIQUE on a time range | Yes as a technique. Accommodation has not chosen it | ADR-0018 T7 line 53 and H4 line 103; `TransportTransaction.php` lines 15–28; `DATA_MODEL.md` line 51 still says TBD | IMPLEMENTED technique; accommodation choice UNKNOWN |
| Tenant-wide `module.action` policies | Yes. `TenantPermissionPolicy` checks the permission and same `tenant_id` only | `TenantPermissionPolicy.php` lines 13–27; `TransportPointPolicy.php` lines 11–18 | IMPLEMENTED |
| Facility-scoped grants (a user may operate only facility X) | **No existing mechanism.** Adding it is a new authorization model | Same policy base has no facility id | NOT IMPLEMENTED; PROPOSED only if product asks |
| Buildings, floors, site enums, program entitlements | Not compatible with “do not invent”. They are absent from module 18 | Audit section 6.3; `DATA_MODEL.md` lines 14–19 | NOT DOCUMENTED |
| Planning tables (demand, queue, draft, reserved family capacity) | Do not add until columns are named | `BUSINESS_RULES.md` lines 62–80; `DATA_MODEL.md` line 23 | DOCUMENTED deferral |
| Transport Point → Facility FK | Compatible only as a later additive Transportation change. Forbidden in the first accommodation delivery | ADR-0018 I14 line 94; `transport_points` has no facility column | DOCUMENTED future |
| Automatic checkout on cancel | The event can drive a listener. The checkout effect itself is not specified, and checking out a stay that was never checked in contradicts the documented states | `BUSINESS_RULES.md` lines 91–98, 163–167 | See section 7 |
| Copying Transportation I2 (planning in Draft, execution only in Active) | Technically callable (`SeasonGuard::assertActive`, lines 24–30). It is a transportation decision, not an accommodation rule | ADR-0018 I2 line 82 | DOCUMENTED for transport only |

---

## 4. Integration constraints

These are hard constraints. A later implementation that breaks one of them conflicts with the locked modules.

1. **Accommodation never writes** `SeasonPilgrim.status`, Family Groups, or any `transport_*` row (`BUSINESS_RULES.md` lines 163–166).
2. **Transport arrival does not check a pilgrim in** (`BUSINESS_RULES.md` line 102).
3. **No current-accommodation column** on `pilgrims` or `season_pilgrims`.
4. **`tenant_id` comes from `TenantContext`.** Seasonal routes use `SeasonContext`. The JSON body is not an authorization or tenancy input.
5. **Cross-tenant and cross-season references are database-impossible** only if accommodation copies the composite FK style in `2026_10_05_100006_create_season_pilgrims_table.php` lines 10–15 and 56–79. A single-column FK to `id` is not sufficient.
6. **Cancellation of a SeasonPilgrim already commits before listeners run.** `SeasonPilgrimCancelled` implements `ShouldDispatchAfterCommit` (event file lines 14–15). A listener failure cannot roll back the foundation cancel. The handler must be idempotent and must not delete history (event docblock lines 8–12; F7; `BUSINESS_RULES.md` line 167).
7. **The listener must re-enter tenancy with `TenantContext::runAsTenant`**, as `HandleSeasonPilgrimCancelled.php` lines 42–47 does. The event is not guaranteed to run inside an open tenant context if it is later queued. The transportation listener is synchronous today (it does not implement `ShouldQueue`).
8. **Cancel is refused on a Closed season** before the event is emitted (`CancelSeasonPilgrim.php` line 37 calls `SeasonGuard::assertWritable`). An accommodation listener therefore does not have to write a Closed season under current foundation rules.
9. **Hard delete of a SeasonPilgrim is already blocked once any checker returns true.** Transportation treats any passenger row as a reference (`TransportationOperationalReferenceChecker.php` lines 10–19). Accommodation should do the same for any assignment or timeline row, including cancelled and checked-out rows.
10. **Deactivation is never blocked** by `SeasonResourceDeletionChecker` (contract lines 11–14). History blocks hard delete only.
11. **`SeasonPilgrimActivationRequirement` must stay unused** unless a later decision says activation requires accommodation. Module 18 does not say that (audit section 4.4).
12. **Do not add `NotificationType` cases, offline queues, or QR** in the first slice (`BUSINESS_RULES.md` lines 132–160).
13. **Contracts category `hotel` is a legal category**, not room inventory (module README).
14. **Nullable `pilgrims.gender`** (`male` \| `female`). Male / Female / Family audience is documented (`BUSINESS_RULES.md` lines 54–60) but is not a column on the pilgrim, and gender may be null. A facility check that assumes gender is always present will mis-allocate.

---

## 5. Recommended physical tables

**PROPOSED. Not a migration. Not approved.** This is the smallest set that can store the documented occupancy rules without planning, QR, offline, notifications, buildings, floors, or a transport link.

`DATA_MODEL.md` lines 14–16 forbid treating concept names as tables until a decision names columns. Product must accept or replace this section before any migration is written.

### 5.1 `accommodation_places`

Season-scoped bookable space (the room, tent, camp unit, or capacity place). One row is the thing an assignment occupies. A parent pointer is optional grouping only (hotel → room, camp → tent) and stays nullable so the taxonomy TBD does not force buildings or floors.

| Column | Constraint | Why |
|---|---|---|
| `id` | PK | |
| `tenant_id` | NOT NULL, FK `tenants` RESTRICT, never updated | Tenancy rule |
| `season_id` | NOT NULL | Stays are seasonal. A place in season A cannot be booked by season B |
| `parent_id` | NULL | Optional grouping. **PROPOSED.** Omit if product rejects hierarchy |
| `code` | string, unique per tenant+season | Operational label |
| `name` | string | |
| `allocation_mode` | `capacity` \| `position` | `BUSINESS_RULES.md` lines 43–50 |
| `capacity` | unsigned NULL. Required and ≥ 1 when mode is `capacity`. NULL when mode is `position` | Capacity mode does not need bed rows |
| `is_active` | boolean | Deactivation must not delete history |

Unique keys: `(tenant_id, id)`, `(tenant_id, season_id, id)`, `(tenant_id, season_id, code)`.

Foreign keys:

- `(tenant_id, season_id)` → `seasons (tenant_id, id)` RESTRICT.
- `(tenant_id, season_id, parent_id)` → `accommodation_places (tenant_id, season_id, id)` RESTRICT.

Index: `(tenant_id, season_id, parent_id)`.

**Not in this table until approved:** audience, Hijri labels, Makkah/Mina/Arafat/Muzdalifah enum, responsible employee, contract id.

### 5.2 `accommodation_positions`

Exists only for a position-based place. One row is one bed or named place. Capacity of a position is **1** in this proposal (see section 6). That formula is the open TBD at `BUSINESS_RULES.md` line 87 and needs an explicit yes.

| Column | Constraint |
|---|---|
| `id` | PK |
| `tenant_id`, `season_id`, `place_id` | NOT NULL |
| `code` | unique per place |
| `is_active` | boolean |

Unique: `(tenant_id, id)`, `(tenant_id, season_id, id)`, `(tenant_id, season_id, place_id, code)`.

FK: `(tenant_id, season_id, place_id)` → `accommodation_places (tenant_id, season_id, id)` RESTRICT.

### 5.3 `accommodation_assignments`

One SeasonPilgrim on one place, or one position, for one time range. This is the occupancy row. It is not a column on `season_pilgrims`.

| Column | Constraint | Why |
|---|---|---|
| `id` | PK | |
| `tenant_id`, `season_id` | NOT NULL | |
| `season_pilgrim_id` | NOT NULL | Foundation participation, not `pilgrims.id` alone |
| `place_id` | NOT NULL | |
| `position_id` | NULL. Required when the place mode is `position`. Forbidden when mode is `capacity` | `BUSINESS_RULES.md` lines 47–48 |
| `starts_at`, `ends_at` | datetime, `ends_at` > `starts_at` | Interval, not a current-room FK. **Calendar is still TBD** (section 9) |
| `status` | `assigned` \| `arrived` \| `checked_in` \| `checked_out` \| `cancelled` | Operational states in `BUSINESS_RULES.md` lines 93–98, plus documented cancel-before-check-in (line 78). Stored spellings are **PROPOSED** |
| `season_family_group_id` | NULL | Copied reference only. No FK that would let accommodation dissolve the group. Composite FK to `season_family_groups` is allowed as read-only RESTRICT, matching foundation style |
| `cancelled_at`, `cancellation_reason` | NULL | Used when status becomes `cancelled` before check-in |

Unique: `(tenant_id, id)`, `(tenant_id, season_id, id)`.

Foreign keys, all RESTRICT:

- `(tenant_id, season_id, season_pilgrim_id)` → `season_pilgrims (tenant_id, season_id, id)`.
- `(tenant_id, season_id, place_id)` → `accommodation_places (tenant_id, season_id, id)`.
- `(tenant_id, season_id, position_id)` → `accommodation_positions (tenant_id, season_id, id)`.
- `(season_id, season_family_group_id)` → `season_family_groups (season_id, id)` when the column is not null.

Indexes for the overlap and read-model queries:

- `(tenant_id, season_id, place_id, starts_at)`
- `(tenant_id, season_id, position_id, starts_at)`
- `(tenant_id, season_id, season_pilgrim_id, starts_at)`
- `(tenant_id, season_id, status)`

**No UNIQUE on `(place_id, starts_at, ends_at)`.** MySQL cannot express range exclusion that way (ADR-0018 T7 line 53).

**Out of the first table:** `draft` vs `confirmed`. Whether a draft consumes capacity is TBD (`BUSINESS_RULES.md` lines 69, 80). The first table stores only rows that consume capacity. A draft flag added early would make the lock rules guess.

### 5.4 `accommodation_timeline_events`

Append-only. Inserts only in the first slice. No UPDATE, no DELETE for normal users.

| Column | Constraint |
|---|---|
| `id` | PK |
| `tenant_id`, `season_id`, `assignment_id`, `season_pilgrim_id` | NOT NULL, composite FKs RESTRICT |
| `event_type` | `assigned` \| `arrived` \| `checked_in` \| `checked_out` \| `relocated` \| `cancelled` \| `extended` | Relocation and check-in stay distinct (`BUSINESS_RULES.md` line 104) |
| `occurred_at` | datetime, the field clock | lines 108–117 |
| `recorded_at` | datetime, server clock, default now | |
| `actor_id` | NULL FK `users` RESTRICT | System listener may pass the cancelling user id from the event |
| `reason` | NULL string | |
| `correlation_id` | NULL string | Copied from the request or from `SeasonPilgrimCancelled` |
| `metadata` | JSON NULL | Scalar context only. No secrets |

Index: `(tenant_id, season_id, assignment_id, id)`, `(tenant_id, season_id, season_pilgrim_id, occurred_at)`.

Correction-by-supersede (transportation I8) is **not** included. Accommodation correction policy is TBD (`BUSINESS_RULES.md` line 128).

### 5.5 Explicitly not recommended yet

| Omitted | Reason |
|---|---|
| Building, floor | Not in module 18 |
| Site enum | Illustration only (`BUSINESS_RULES.md` lines 31–37) |
| Demand, phase, queue, allocation batch, reserved family capacity | `BUSINESS_RULES.md` line 80 |
| Notification configuration | Concept only; line 160 |
| Offline queue, QR | Later slices |
| Entitlement per program | Transportation has this (ADR-0018 T9). Accommodation does not |
| `transport_points.accommodation_place_id` | Would edit Transportation |
| Read-model table | Query vs projection is TBD (`BUSINESS_RULES.md` line 25). First slice should query assignments |
| Facility grant table | See section 8 |

### 5.6 Relationship summary

```text
tenants
  └── seasons                          LOCKED
        ├── season_pilgrims            LOCKED
        │     └── accommodation_assignments
        │           └── accommodation_timeline_events
        ├── season_family_groups       LOCKED, referenced, never written
        └── accommodation_places
              └── accommodation_positions
```

Every accommodation row includes `tenant_id`. Every stay row includes `season_id`. ON DELETE is RESTRICT so history cannot cascade away (F7).

---

## 6. Concurrency strategy

**PROPOSED**, using the transportation mechanism that is already implemented. Accommodation `DATA_MODEL.md` line 51 still marks the mechanism TBD, so this section is a recommendation, not an approved rule.

### 6.1 Transaction

One outermost `DB::transaction($callback, 3)`, same as `TransportTransaction::ATTEMPTS` (`TransportTransaction.php` lines 17–28). Retry only when this is the outermost transaction, so a deadlock re-reads committed rows. InnoDB REPEATABLE READ can miss a row committed after the snapshot; a plain `SELECT` is not enough (ADR-0018 H4 line 103).

### 6.2 Lock order

Always lock in this order, each query `orderBy('id')` then `lockForUpdate()`, so two requests cannot deadlock by taking the same rows in opposite orders:

1. The **place** row (the capacity parent).
2. The **position** row, when the assignment targets one.
3. Existing **assignment** rows for that place (or that position) whose status is not `cancelled`, overlapping the candidate interval.
4. Existing **assignment** rows for that `season_pilgrim_id` in the same season (pilgrim-interval check below).

Lock the parent before the assignment scan. Transportation does the same: lock the resource, then the history (`ADR-0018` H4).

### 6.3 Overlapping stays

Documented rule: two assignments must not overlap on the same place or the same position (`DATA_MODEL.md` line 51). Sequential stays for one SeasonPilgrim are allowed (`BUSINESS_RULES.md` lines 29–39).

Recommended predicate, **half-open** `[starts_at, ends_at)`:

```text
existing.starts_at < candidate.ends_at
AND existing.ends_at > candidate.starts_at
AND existing.status <> cancelled
```

Half-open intervals let the illustration’s “day 4 ends, day 5 starts” touch without counting as overlap. **Inclusive end is still TBD** (`BUSINESS_RULES.md` line 41). If product later says the end instant is inclusive, this predicate is wrong and must change before coding.

Rows that still occupy the interval: `assigned`, `arrived`, `checked_in`, `checked_out`. Checked-out history still occupied that range. `cancelled` (before check-in) releases it. That reading follows “cancellation before check-in” (line 78) and “do not delete history” (line 167). It is **PROPOSED** because the docs do not list which statuses consume the interval.

Checked-out rows are not deleted when a later stay is created. The overlap query simply ignores ranges that do not intersect.

### 6.4 Capacity

Under the place lock, after the overlap check:

- **Capacity mode:** count non-cancelled assignments on that place whose intervals intersect the candidate. Reject when `count + 1 > place.capacity`. No position id is stored.
- **Position mode (proposed formula):** each position has capacity 1, enforced by the position overlap check. Do not also apply `place.capacity`. Creating a position-mode assignment without `position_id` is rejected. This is the TBD at `BUSINESS_RULES.md` line 87 and must be confirmed.
- **Capacity decrease:** `UPDATE` the place capacity inside the same lock. If existing intersections already exceed the new number, keep the rows and reject only a further insert (`BUSINESS_RULES.md` lines 84–86). Do not auto-cancel occupants.

### 6.5 One pilgrim, two places

**Not an explicit rule.** The docs forbid overlap on a place or position. They allow sequential stays. They do not say a SeasonPilgrim cannot hold two places in the same interval.

**Safest proposal, requiring approval:** apply the same half-open predicate to that `season_pilgrim_id` inside the same transaction, and reject a second intersecting stay. Without it, one person can consume two capacities at once. Do not treat this as already decided.

### 6.6 Family exclusivity

**No exclusivity rule is documented.** “Family-aware allocation” and “reserved family capacity” are concepts whose columns are forbidden until specified (`BUSINESS_RULES.md` lines 73–80).

The foundation already guarantees at most one primary member per family group (`season_pilgrims` generated column and unique key, migration lines 17–20 and 82). That is not a room lock.

**Do not add a family-exclusivity unique index or lock in the first schema.** If product later defines “this place is reserved for one family group for an interval, and other families or individuals cannot share it”:

- Store `season_family_group_id` on the assignment (already in section 5.3 as an optional reference).
- Under the **place** lock, reject an intersecting assignment whose family id differs, including null versus a family id.
- Still write one assignment row per SeasonPilgrim. Transportation resolves groups to individual rows (ADR-0018 T5). Accommodation has not chosen that grain; individual rows match “sequential assignments per SeasonPilgrim” and avoid a group row that the foundation can dissolve underneath.

Mixing families is therefore **allowed by current text**. Enforcing exclusivity now would invent a rule.

### 6.7 Cross-season allocation

Prevented by construction if every place, position, assignment, and timeline row has `season_id` and the composite FKs in section 5:

- An assignment cannot point at another tenant’s pilgrim or another season’s pilgrim.
- An assignment in season 1448 cannot reference a place row whose `season_id` is 1447.
- The API resolves the season from `X-Season-Id` and rejects a bound model from another season with 404 (`ResolveSeasonContext.php` lines 36–39).

A tenant-level “physical room” reused across Hajj years is **not** in this model. Adding it later needs a separate identity and a cross-season overlap lock. Putting `season_id` nullable on the place to “save typing” would reopen cross-season double booking. Do not do that in the first tables.

Closed-season writes stay on `SeasonGuard::assertWritable`. Draft versus Active execution is **not** decided for accommodation (section 3). Until it is, the only safe statement is: Closed rejects writes; Draft and Active behavior must be written down before the first action class.

---

## 7. `SeasonPilgrimCancelled` and checkout

### 7.1 What the code actually does

| Fact | Evidence | Class |
|---|---|---|
| Cancel of Registered or Active, not terminal, season writable | `CancelSeasonPilgrim.php` lines 17–20, 36–38 | IMPLEMENTED |
| Event after commit, scalar ids only, no foundation listener | `SeasonPilgrimCancelled.php` lines 8–28 | IMPLEMENTED |
| Transportation does **not** auto-complete a boarded passenger. Not-yet-boarded on an open run → passenger `cancelled`, row kept. Boarded or already departed → review item. Already arrived → history kept | `HandleSeasonPilgrimCancelled.php` lines 75–107; ADR-0018 T8 lines 55–61 | IMPLEMENTED |
| Accommodation listener | None | IMPLEMENTED (absence) |
| Accommodation rule | Retain timeline. Do not erase history. Do not write SeasonPilgrim status | `BUSINESS_RULES.md` lines 163–167 | DOCUMENTED |

**Automatic checkout is NOT VERIFIED as a product requirement.** It does not appear in module 18, ADR-0017 §5, or ADR-0018. Checking out a pilgrim who was only `assigned` or `arrived` would record a check-out that never happened, which conflicts with the four operational meanings in `BUSINESS_RULES.md` lines 93–98.

### 7.2 How a listener can run without editing the foundation

Register it only in a future `AccommodationServiceProvider`, copying `TransportationServiceProvider.php` lines 51–53:

```text
Event::listen(SeasonPilgrimCancelled::class, HandleSeasonPilgrimCancelled::class);
```

The handler:

1. Loads `Tenant` by `$event->tenantId`. Missing tenant → return (transportation lines 42–45).
2. `TenantContext::runAsTenant` for the rest.
3. Selects this module’s assignment rows for `$event->seasonId` and `$event->seasonPilgrimId` only.
4. For each assignment id, opens its own retried transaction, locks the place, then the assignment (`lockForUpdate`).
5. Never updates `season_pilgrims`. Never deletes assignment or timeline rows.
6. Is safe to run twice (idempotent on status).

Because the event is after commit, this work is **not** atomic with the foundation cancel. The same gap already exists for transportation. If the listener throws, the pilgrim stays cancelled and occupancy can lag. The handler must leave each assignment in a state a second delivery can finish.

### 7.3 Recommended effect (PROPOSED — needs a product yes)

Align the effect with the documented states and with transportation’s split, instead of checking everyone out:

| Current assignment status | Effect | History |
|---|---|---|
| `assigned` or `arrived` | Set `cancelled`, append a `cancelled` timeline event, reason and correlation id copied from the event. Interval no longer counts for overlap or capacity | Row kept |
| `checked_in` | Do **not** auto check-out. Append nothing that pretends the guest left. Raise an accommodation review row (same idea as transportation `CancelledOnBoard`) for a person with `accommodation.resolve_reviews` | Stay remains checked in until a person records check-out |
| `checked_out` or `cancelled` | No-op | Kept |

Automatic checkout of a `checked_in` stay is the wrong default: it hides an in-house pilgrim at the moment the participation is cancelled. If product explicitly wants that checkout, the listener may set `checked_out` and append `checked_out` with `metadata.cause = season_pilgrim_cancelled`, still without deleting rows. That choice must be written into `BUSINESS_RULES.md` first. This verification does not approve it.

`occurred_at` for the system transition should be `$event->cancelledAt`. `recorded_at` is the listener clock. `actor_id` is `$event->cancelledBy`.

---

## 8. Permissions and facility scope

### 8.1 What exists

Authorization is deny-by-default `module.action`. Record policies add “same tenant” and nothing finer (`TenantPermissionPolicy.php` lines 8–27). Transportation splits verbs by power (`PermissionCatalog.php` lines 180–189: `view`, `manage_registry`, `manage_season_fleet`, `manage_entitlements`, `manage_movements`, `operate_runs`, `manage_manifest`, `override_entitlement`, `correct_history`, `resolve_reviews`). There is no per-point or per-vehicle grant.

`docs/09-modules/18-accommodation-and-camps/PERMISSIONS.md` lines 6–25: namespace `accommodation.*`, verbs TBD, do not seed `PermissionCatalog.php` yet, no `camps.*`, role grants TBD, policies stay authoritative.

### 8.2 Proposed verbs (PROPOSED — do not seed until written into PERMISSIONS.md)

Mapped one-to-one onto the powers already listed in `PERMISSIONS.md` lines 12–19. Bulk relocation stays on the same verb until that TBD is closed.

| Verb | Power already required by the doc | First slice |
|---|---|---|
| `accommodation.view` | View plans and operational state | Yes |
| `accommodation.manage_places` | Maintain facilities / places / positions | Yes |
| `accommodation.allocate` | Allocate and confirm | Yes. Confirm-only until draft exists |
| `accommodation.relocate` | Relocate, including bulk if product says it is the same verb | Yes |
| `accommodation.operate_stay` | Record arrival, check-in, and check-out | Yes |
| `accommodation.cancel_assignment` | Cancel before check-in | Yes |
| `accommodation.resolve_reviews` | Needed only if section 7.3’s review row is accepted | With that decision |
| `accommodation.configure_notifications` | Configure business notification rules | Later |
| `accommodation.resolve_sync_conflicts` | Offline conflicts | Later |

Policies extend `TenantPermissionPolicy`. Example: `operate_stay` on an assignment checks `accommodation.operate_stay` and `actor.tenant_id === assignment.tenant_id`. UI `can()` hides buttons only.

Role grants stay empty except a later, separate decision. ADR-0017 addendum item 10 already notes that only `tenant_owner` received season permissions and other templates are a pending business decision. Do not copy transportation’s owner bundle onto accommodation by assumption.

### 8.3 Facility-scoped authorization

**Not compatible with the current model, and not documented.**

A facility-scoped scheme (this user may check in only at Mina camp 206) would need a new tenant-owned grant table and a policy check beyond `TenantPermissionPolicy`. That is a product and security design, not a reuse. The first slice should stay tenant-wide, matching every module that exists today, including Transportation’s point policy (`TransportPointPolicy.php` lines 11–18).

If facility scope is required later:

- Keep the verb. Add an allow-list of `place_id` (or parent place id) per user or role.
- Absence of a grant means **no access**, not “all facilities”, once the feature is on. Mixing “no rows means all facilities” with a partial rollout leaks sites.
- Cross-tenant place ids still 404 before the grant check.
- The system listener for `SeasonPilgrimCancelled` does not pass through a user policy. It runs as trusted module code inside `runAsTenant`, the same way transportation’s listener records audit with `$event->cancelledBy` and does not call `Gate`.

---

## 9. Unresolved assumptions and risks

| ID | Assumption or risk | Severity | Evidence | Class |
|---|---|---|---|---|
| V1 | Coding from this recommended schema before it is accepted in module 18 | **Critical** | ADR-0017 line 109; `ACCEPTANCE_CRITERIA.md` line 25; `DATA_MODEL.md` lines 14–16 | DOCUMENTED blocker |
| V2 | Half-open Gregorian datetimes. Hijri storage, inclusive end, and gaps are TBD | **Critical** for date rules | `BUSINESS_RULES.md` line 41 | UNKNOWN calendar |
| V3 | Position capacity = 1 and place capacity ignored in position mode | **High** | `BUSINESS_RULES.md` line 87 | PROPOSED |
| V4 | Statuses that consume an interval, and pilgrim-level double-book | **High** | Overlap text is place/position only (`DATA_MODEL.md` line 51) | PROPOSED |
| V5 | Automatic checkout of `checked_in` stays | **High** if built as the default | Section 7. Not in the docs. Falsifies operational state | NOT DOCUMENTED |
| V6 | Listener runs after commit, so occupancy can lag a successful cancel | **High** | `ShouldDispatchAfterCommit`; transportation accepts the same gap | IMPLEMENTED behavior |
| V7 | Family exclusivity enforced in v1 | **High** if invented | `BUSINESS_RULES.md` lines 73–80 | NOT DOCUMENTED |
| V8 | Draft assignments added without a capacity rule | **High** | lines 69, 80 | UNKNOWN |
| V9 | Arrival required before check-in; cancel after arrival; extend by rewrite vs new interval | **High** | line 106; line 78 | UNKNOWN |
| V10 | Which season statuses allow planning vs execution | **Medium** | ADR-0018 I2 is transport-only. `SeasonGuard::assertWritable` allows Draft and Active | UNKNOWN for accommodation |
| V11 | Closing-readiness BLOCKING codes not chosen. Contract can report them; no accommodation contributor exists | **Medium** | `SeasonClosingReadinessContributor.php` lines 8–19 | IMPLEMENTED contract, UNKNOWN rules |
| V12 | Nullable gender vs Male/Female audience | **Medium** | `pilgrims.gender` nullable; audience location TBD line 60 | UNKNOWN |
| V13 | Facility-scoped RBAC in v1 | **Medium** | Section 8.3 | NOT DOCUMENTED |
| V14 | Editing Transportation to add a facility FK in the first PR | **Medium** | ADR-0018 I14 | DOCUMENTED deferral |
| V15 | N+1 if pilgrim lists embed occupancy before a query shape exists | **Medium** | Read model TBD line 25 | UNKNOWN |
| V16 | Mode change after assignments exist | **Medium** | `BUSINESS_RULES.md` line 52 | UNKNOWN |
| V17 | Stale docs saying transportation is not built | **Low** | `MVP_KNOWN_LIMITATIONS.md` lines 35–36 | DOCUMENTED drift |

Transportation’s MySQL concurrency suite was **not re-run** for this verification (**NOT VERIFIED**). Accommodation has no concurrency tests (**NOT VERIFIED**).

---

## 10. Smallest safe implementation sequence

Each step waits for the previous one. No step edits Season & Pilgrims or Transportation.

1. **Product decision, still not code.** Accept or replace sections 5–8 of this report inside `DATA_MODEL.md`, `PERMISSIONS.md`, `BUSINESS_RULES.md`, and `API.md`: columns, verb strings, half-open vs inclusive intervals, position capacity, pilgrim double-book, cancel effect (section 7.3), and Draft vs Active writes. Reject buildings, floors, entitlements, facility grants, QR, and offline unless the decision explicitly adds them.
2. **Backend slice, only after step 1 is written in those module files.**
   - Migrations for the four tables, composite FKs, indexes in section 5.
   - Places, positions, confirmed assignments, overlap and capacity locks (section 6), append-only timeline.
   - `SeasonGuard` on writes. `season.context` on routes.
   - Policies for the approved verbs. Seed `PermissionCatalog.php` only from the updated `PERMISSIONS.md`.
   - Register the three foundation tags and the cancel listener with the approved effect.
   - `Core/Audit` on critical mutations. Timeline remains a second history.
   - Pest: 401, 403, 404 cross-tenant, 404 cross-season, overlap 422, capacity 422, sequential stays allowed, transport arrival does not check in, relocate+check-in writes two timeline events, cancel listener idempotent and does not delete rows, `SeasonPilgrim.status` unchanged.
   - A MySQL multi-process test for two concurrent assignments on one place, copied in style from transportation, after the lock order is the approved one.
3. **Read model as a query** over assignments (the four states in `BUSINESS_RULES.md` lines 17–21). No projection table until that TBD is closed.
4. **Frontend** on the existing RTL shell, `can()` for visibility only.
5. **Later, separate decisions:** drafts and planning, notification configuration on the in-app channel, closing-readiness code list if not settled in step 1, facility grants, Transport Point link inside Transportation, offline, QR.

---

## 11. Files reviewed

- `docs/reviews/ACCOMMODATION_CAMPS_PRE_IMPLEMENTATION_AUDIT.md`
- `docs/09-modules/18-accommodation-and-camps/` — `README.md`, `BUSINESS_RULES.md`, `DATA_MODEL.md`, `API.md`, `PERMISSIONS.md`, `UI.md`, `TEST_PLAN.md`, `ACCEPTANCE_CRITERIA.md`
- `docs/10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md` (occupancy and blocker; cited via the audit and re-checked where this report names a line)
- `docs/10-decisions/ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md` lines 51–72, 81–82, 91–94, 101–103, 122
- `backend/bootstrap/providers.php`
- `backend/app/Modules/SeasonPilgrims/Events/SeasonPilgrimCancelled.php`
- `backend/app/Modules/SeasonPilgrims/Actions/SeasonPilgrims/CancelSeasonPilgrim.php`
- `backend/app/Modules/SeasonPilgrims/Support/SeasonGuard.php`
- `backend/app/Modules/SeasonPilgrims/Enums/SeasonStatus.php`
- `backend/app/Modules/SeasonPilgrims/Context/ResolveSeasonContext.php`
- `backend/app/Modules/SeasonPilgrims/Context/SeasonAware.php`
- `backend/app/Modules/SeasonPilgrims/Contracts/SeasonClosingReadinessContributor.php`
- `backend/app/Modules/SeasonPilgrims/Contracts/SeasonPilgrimOperationalReferenceChecker.php`
- `backend/app/Modules/SeasonPilgrims/Contracts/SeasonResourceDeletionChecker.php`
- `backend/app/Modules/SeasonPilgrims/Readiness/ReadinessResult.php`
- `backend/app/Modules/SeasonPilgrims/Policies/TenantPermissionPolicy.php`
- `backend/database/migrations/2026_10_05_100006_create_season_pilgrims_table.php`
- `backend/app/Modules/Transportation/TransportationServiceProvider.php`
- `backend/app/Modules/Transportation/Listeners/HandleSeasonPilgrimCancelled.php`
- `backend/app/Modules/Transportation/Integration/TransportationOperationalReferenceChecker.php`
- `backend/app/Modules/Transportation/Support/TransportTransaction.php`
- `backend/app/Modules/Transportation/Policies/TransportPointPolicy.php`
- `backend/app/Core/Authorization/PermissionCatalog.php` (module list and transportation verbs)
- Repository search for `ACCOMMODATION_CAMPS_ARCHITECTURE_DRAFT` — no matches

Git status at verification time: branch `develop`, HEAD `736c776dd1d8ca6ddcb53239ab8cd2e87a9943fa`. Untracked: `docs/reviews/` (the prior audit plus this file) and `تقرير النظام/`. No application files were modified.

---

## 12. File created

`docs/reviews/ACCOMMODATION_CAMPS_ARCHITECTURE_VERIFICATION.md`

No other file was created or changed.

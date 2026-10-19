# Accommodation & Camps — Backend Sprint 1 Readiness

> **Type:** Documentation review only. No ADR, module document, application code, migration, or test was changed.
> **Date:** 2026-10-15
> **Scope:** Stable facilities, optional structures, units, and positions; season allocations; non-reserving drafts; facility and supervisor scope.
> **Sources:** ADR-0019 (corrected, still PROPOSED / NOT ACCEPTED), the ADR-0019 final review, the reconciliation, module 18, and the Season / user foreign-key patterns in the existing migrations.
> **This file does not accept ADR-0019 and does not authorize implementation.**

## Verdict

**READY FOR SCOPED APPROVAL**

A separate Sprint 1 approval can cover only the slice in ADR-0019 §10 while the rest of the ADR stays PROPOSED. The unresolved confirmation, capacity, family, checkout, and cancellation items in ADR-0019 §11 do not block this slice, provided the slice does not confirm a stay or enforce occupancy.

Scoped approval is safe only if it adopts the schema and guards in this file, updates module 18 for this slice before any migration, and does not seed role grants. Those are conditions of the approval, not permission to start coding from this review alone.

---

## 1. What is in Sprint 1, and what is not

ADR-0019 §10. In scope:

| Table | Role |
|---|---|
| `accommodation_facilities` | Stable hotel or camp. No `season_id`. |
| `accommodation_structures` | Optional building or floor. Nullable parent. |
| `accommodation_units` | Stable room or tent. |
| `accommodation_positions` | Stable bed or tent position. |
| `accommodation_season_facilities` | This facility is selected for this season. Optional audience. |
| `accommodation_season_units` | This unit is selected. Stores mode, capacity, and audience. Does not enforce them against stays. |
| `accommodation_season_positions` | Which stable positions are enabled on that season unit. |
| `accommodation_assignments` | Draft rows only. |
| `accommodation_facility_scopes` | User + season + facility. |
| `accommodation_supervisor_scopes` | User + season, all facilities. |

Out of scope, and not created in this slice: `accommodation_events`, `accommodation_family_reviews`, `accommodation_cancellation_tasks`, `accommodation_pilgrim_locks`, `physical_capacity`, confirmation, peak checks, family exclusivity, arrival, check-in, checkout, relocation, extension, administrative checkout, closing readiness, and reference checkers.

Column lists below are an **engineering enumeration** so a later migration does not invent keys. ADR-0019 names the business columns and requires composite foreign keys (ADR §4). Columns marked **FK target** exist so those keys match `seasons` and `season_pilgrims` (`2026_10_05_100000_create_seasons_table.php` line 44; `2026_10_05_100006_create_season_pilgrims_table.php` lines 78–79). They are not new product fields.

Every business table: `id` bigint PK; `tenant_id` NOT NULL, never updated, FK `tenants` ON DELETE RESTRICT; `timestamps`. Client never sends `tenant_id`. `UsesTenantScope` / `TenantOwned` as on `Season`.

`users` has nullable `tenant_id` and no unique `(tenant_id, id)` (`2026_08_06_100001_add_tenant_id_to_users_table.php` lines 16–23). Do not alter `users` in this slice. Actor and grantee references are a single-column FK to `users.id` plus an application check that the user’s `tenant_id` equals the context tenant. A platform user (`tenant_id` null) cannot receive a scope.

---

## 2. Tables, keys, and checks

ON DELETE is RESTRICT on every foreign key. MySQL identifier names must stay within 64 characters.

### 2.1 `accommodation_facilities`

| Column | Rule | Source |
|---|---|---|
| `tenant_id` | FK target part | Pattern |
| `kind` | `hotel` or `camp` | ADR §2.1, §4 |
| `code` | NOT NULL | ADR §4 |
| `name` | NOT NULL | ADR §4 |
| `is_active` | boolean, default true | ADR §4 |
| `created_by` | nullable FK `users` RESTRICT | Audit actor, same pattern as seasons |

Unique: `(tenant_id, id)` name `accommodation_facilities_tenant_id_uq`; `(tenant_id, code)`.

No `season_id`. No `physical_capacity`.

Application rule, not a SQL check: `kind` is only those two values. The existing codebase stores status as a string and validates it in PHP (see `seasons.status`). Do not add a product-level site enum.

### 2.2 `accommodation_structures`

| Column | Rule |
|---|---|
| `tenant_id`, `facility_id` | Facility of this node |
| `parent_id` | NULL, or another structure of the **same** facility |
| `kind` | `building` or `floor` |
| `name` | NOT NULL |
| `created_by` | nullable FK `users` |

Unique: `(tenant_id, id)`; `(tenant_id, facility_id, id)` so the parent FK can include the facility.

Foreign keys:

- `(tenant_id, facility_id)` → `accommodation_facilities (tenant_id, id)`.
- `(tenant_id, facility_id, parent_id)` → `accommodation_structures (tenant_id, facility_id, id)`.

Application rule: reject a parent cycle by walking parents before insert or update. MySQL will not express that as a check. A structure is optional. No unit is required to reference one (ADR §2.1).

### 2.3 `accommodation_units`

| Column | Rule |
|---|---|
| `tenant_id`, `facility_id` | Owning facility |
| `structure_id` | NULL, or a structure of that same facility |
| `unit_kind` | `room` or `tent` |
| `code` | NOT NULL, unique per facility |
| `created_by` | nullable FK `users` |

Unique: `(tenant_id, id)`; `(tenant_id, facility_id, id)`; `(tenant_id, facility_id, code)`.

Foreign keys:

- `(tenant_id, facility_id)` → `accommodation_facilities (tenant_id, id)`.
- `(tenant_id, facility_id, structure_id)` → `accommodation_structures (tenant_id, facility_id, id)`.

### 2.4 `accommodation_positions`

| Column | Rule |
|---|---|
| `tenant_id`, `unit_id` | Owning stable unit |
| `code` | NOT NULL, unique per unit |
| `created_by` | nullable FK `users` |

Unique: `(tenant_id, id)`; `(tenant_id, unit_id, id)`; `(tenant_id, unit_id, code)`.

Foreign key: `(tenant_id, unit_id)` → `accommodation_units (tenant_id, id)`.

### 2.5 `accommodation_season_facilities`

| Column | Rule |
|---|---|
| `tenant_id`, `season_id` | Context season |
| `facility_id` | Stable facility in the same tenant |
| `audience` | NULL, or `male`, `female`, `family`. NULL means no facility-level restriction (decision 9, optional). |
| `is_active` | boolean, default true |
| `created_by` | nullable FK `users` |

Unique: `(tenant_id, id)`; `(tenant_id, season_id, id)`; `(tenant_id, season_id, id, facility_id)` as the FK target for season units; `(tenant_id, season_id, facility_id)` so a facility is selected at most once per season.

Foreign keys:

- `(tenant_id, season_id)` → `seasons (tenant_id, id)` using `seasons_tenant_id_id_unique`.
- `(tenant_id, facility_id)` → `accommodation_facilities (tenant_id, id)`.

### 2.6 `accommodation_season_units`

| Column | Rule |
|---|---|
| `tenant_id`, `season_id` | Same season as the season facility |
| `season_facility_id` | Season facility row |
| `facility_id` | **FK target.** Must be the facility of that season-facility row and of `unit_id`. Not a second business choice. |
| `unit_id` | Stable unit |
| `allocation_mode` | `capacity` or `position` |
| `capacity` | Unsigned integer, nullable. **Stored only.** Sprint 1 does not compare it to stays. Do not add a check that sets it from the position count. That comparison is ADR §11 and is not decided. |
| `audience` | `male`, `female`, or `family`. NOT NULL. Decision 9 places audience on the room or tent. |
| `is_active` | boolean, default true |
| `created_by` | nullable FK `users` |

Unique: `(tenant_id, id)`; `(tenant_id, season_id, id)`; `(tenant_id, season_id, id, unit_id)` as the assignment FK target; `(tenant_id, season_id, unit_id)` so a unit is allocated at most once per season.

Foreign keys:

- `(tenant_id, season_id)` → `seasons (tenant_id, id)`.
- `(tenant_id, season_id, season_facility_id, facility_id)` → `accommodation_season_facilities (tenant_id, season_id, id, facility_id)`.
- `(tenant_id, facility_id, unit_id)` → `accommodation_units (tenant_id, facility_id, id)`.

The second and third keys together reject a unit that belongs to a different facility than the season facility (ADR §10 test).

### 2.7 `accommodation_season_positions`

| Column | Rule |
|---|---|
| `tenant_id`, `season_id` | Context season |
| `season_unit_id` | Season unit |
| `unit_id` | **FK target.** The stable unit of that season unit. |
| `position_id` | Stable position of that same unit |
| `is_active` | boolean, default true |
| `created_by` | nullable FK `users` |

Unique: `(tenant_id, id)`; `(tenant_id, season_id, id)`; `(tenant_id, season_id, position_id)` so a position is enabled at most once per season.

Foreign keys:

- `(tenant_id, season_id, season_unit_id, unit_id)` → `accommodation_season_units (tenant_id, season_id, id, unit_id)`.
- `(tenant_id, unit_id, position_id)` → `accommodation_positions (tenant_id, unit_id, id)`.

### 2.8 `accommodation_assignments` (drafts only)

| Column | Sprint 1 rule |
|---|---|
| `tenant_id`, `season_id` | Context season |
| `season_pilgrim_id` | Participation in that same season. Any foundation status is allowed on a **draft**. The confirmation eligibility matrix stays unresolved (ADR §11) and is not applied. |
| `season_unit_id`, `unit_id` | The pair must match the season unit’s own `unit_id`. |
| `season_position_id` | NULL allowed. If set, it must be an enabled season position of that season unit. Not required on a draft, including when mode is `position`. Revalidation is a confirmation concern (ADR §5). |
| `booking_status` | Database check: `booking_status = 'draft'`. Widening this check is a later migration, after confirmation is approved. The API has no confirm action. |
| `operational_status` | NULL. A draft is not `assigned`, `arrived`, `checked_in`, or `checked_out`. |
| `starts_at`, `original_ends_at`, `effective_ends_at` | Gregorian UTC. Check: `effective_ends_at = original_ends_at` and `original_ends_at > starts_at` (half-open, decision 3). No checkout in this slice, so the effective end cannot move. |
| `family_group_id` | NULL. Not a confirmation snapshot yet. No family-exclusivity check. |
| `checkout_source` | NULL. |
| `created_by` | nullable FK `users` |

Unique: `(tenant_id, id)`; `(tenant_id, season_id, id)`. No unique key on `(unit, starts_at, ends_at)`. Two drafts on the same unit and interval must both persist (ADR §10). Overlap is not reserved (decision 2).

Foreign keys:

- `(tenant_id, season_id)` → `seasons (tenant_id, id)`.
- `(tenant_id, season_id, season_pilgrim_id)` → `season_pilgrims (tenant_id, season_id, id)` (`season_pilgrims_tenant_season_id_unique`).
- `(tenant_id, season_id, season_unit_id, unit_id)` → `accommodation_season_units (tenant_id, season_id, id, unit_id)`.
- `(tenant_id, season_id, season_position_id)` → `accommodation_season_positions (tenant_id, season_id, id)`, nullable.

Index for scoped lists, not for exclusion: `(tenant_id, season_id, season_unit_id, starts_at)`.

There is no capacity trigger, no peak query, and no lock on `accommodation_pilgrim_locks`. That table is not in this slice.

### 2.9 `accommodation_facility_scopes`

| Column | Rule |
|---|---|
| `tenant_id`, `season_id`, `facility_id` | One facility in one season |
| `user_id` | FK `users.id` RESTRICT. Application: `users.tenant_id` equals context tenant. |
| `granted_by` | nullable FK `users` |
| `revoked_at` | NULL while the grant is live |
| `active_grant` | Generated: `1` when `revoked_at` is null, else NULL. Same pattern as `seasons.active_flag`. |

Unique: `(tenant_id, season_id, user_id, facility_id, active_grant)`. A revoked row may remain. A second live grant for the same user, season, and facility cannot.

Foreign keys: `(tenant_id, season_id)` → `seasons`; `(tenant_id, facility_id)` → `accommodation_facilities (tenant_id, id)`.

Revocation sets `revoked_at`. It does not delete the row (decision 13: history preserved). The next draft write must see the revocation (ADR §7, §10).

### 2.10 `accommodation_supervisor_scopes`

Same shape without `facility_id`. Unique `(tenant_id, season_id, user_id, active_grant)`. FK `(tenant_id, season_id)` → `seasons`. Supervisor scope does not grant `manage_facilities` or any verb the user does not already have (ADR §7).

---

## 3. Tenant and season isolation

| Rule | Evidence | Sprint 1 behavior |
|---|---|---|
| `tenant_id` from `TenantContext` only | ADR §3; `TenantOwned` on `Season` | 404 for another tenant’s id. Never 403 for a cross-tenant id. |
| Season from `X-Season-Id` / `SeasonContext` | `ResolveSeasonContext.php` lines 15–39 | A season allocation, draft, or scope whose `season_id` is not the context season is 404. |
| Stable facility, structure, unit, and position have no `season_id` | ADR §2.10, §4 | They are tenant-wide. A write still uses the context season only for the guard in §4 below. The row is not stored under a season. |
| Composite FKs | §2 of this file | A draft cannot point at another tenant’s pilgrim or another season’s unit. |
| `users` composite FK | Not available without altering `users` | Do not add it. Match `tenant_id` in the action. |

---

## 4. Bootstrap authorization

Verbs for this slice only, not seeded until module 18 lists them (see §7): `accommodation.view`, `accommodation.manage_facilities`, `accommodation.manage_season_allocation`, `accommodation.draft`, `accommodation.manage_scopes`.

No role template receives them (ADR §7: no default grants). Tests grant them on the fixture role. Do not attach them to `tenant_owner` inside the migration.

| Verb | Facility-scope row required? | Why |
|---|---|---|
| `manage_facilities` | No | Creates the facility that a scope would point at. ADR §7. |
| `manage_season_allocation` | No | First allocation of a new facility would otherwise deadlock. ADR §7. |
| `manage_scopes` | No | Creates the first grant. ADR §7. |
| `view`, `draft` | Yes: facility scope for that season and facility, or supervisor scope for that season | ADR §7. Same-tenant miss is 403. |

`manage_facilities` and `manage_season_allocation` still require the verb, the tenant, and a Draft or Active context season. They are audited. They do not take a facility-scope lock.

A user who can create a facility but has no scope cannot `view` or `draft` it until `manage_scopes` grants them. That is the intended bootstrap, not a defect.

Revocation: set `revoked_at` inside a transaction. The next `draft` write loads the scope grant with `lockForUpdate` on that scope row (or re-reads it inside its own transaction) and returns 403 if `revoked_at` is set. Draft writes do not use the confirmation lock order in ADR §6.

---

## 5. Season guards

From ADR §3, matching `SeasonGuard::assertWritable` / `assertActive` (`SeasonGuard.php` lines 17–30). `isWritable()` is Draft and Active only.

| Action | Draft | Active | Closed |
|---|---|---|---|
| Create or update facility, structure, unit, position | Allowed | Allowed | 422 season closed. The row has no `season_id`, but the context season still gates the write. |
| Season facility, unit, or position allocation | Allowed | Allowed | 422 |
| Create, update, or delete a draft | Allowed | Allowed | 422 |
| `view` | Allowed if scoped | Allowed if scoped | Allowed if scoped. Closed is read-only, not invisible. |
| Grant or revoke scope | Allowed | Allowed | 422 |
| Confirm or any movement | Not implemented | Not implemented | Not implemented |

Do not call `assertActive` for preparation or drafts. Do not implement the unapproved Closed-season correction path.

Deactivating `is_active` is an update, not a delete. Hard delete of a facility, unit, or position that is still referenced fails by RESTRICT. Historical season rows are not cascaded.

---

## 6. Draft behavior

A draft does not reserve capacity, a position, or a pilgrim (decision 2, ADR §10).

| Operation | Behavior |
|---|---|
| Create | Verb `draft`, scope as in §4, guard as in §5. Forces `booking_status=draft`, null operational status, null family, null checkout source, `effective_ends_at = original_ends_at`, end after start. Overlap with another draft is allowed. Capacity is not read. Gender is not read. Family exclusivity is not applied. |
| Update | Same checks. Only a row with `booking_status=draft`. May change interval, season unit, and optional position. Still no reservation. |
| Delete | Hard delete allowed only for a draft. No timeline row exists to preserve. |
| Read | `view` or `draft`, plus scope. Supervisor scope lists every facility in the season. A facility outside scope is omitted from lists and 403 on direct read. |
| Confirm | No route. An insert with any other `booking_status` fails the database check. |

Linking a draft to a terminal SeasonPilgrim is allowed here and decides nothing about who may later be confirmed.

---

## 7. Module 18 changes required before implementation

These are a documentation prerequisite. This review does not make them. ADR §10 still says to align module 18 before a migration. ADR-0017 and ADR-0018 stay untouched.

| Document | Change limited to Sprint 1 |
|---|---|
| `DATA_MODEL.md` | Replace “columns TBD / no migration” for this slice with the ten tables in §2. State that capacity is stored and not enforced against drafts. Leave confirmation, events, reviews, locks, and `physical_capacity` unbuilt. Keep the ban on `current_room_id`, `current_bed_id`, and `current_accommodation_id`. |
| `PERMISSIONS.md` | Seed source for the five verbs in §4 only. State which three do not need a scope row. State that no role template is granted. Leave confirm, arrive, check-in, checkout, relocate, extend, correct, and family-review verbs unseeded. |
| `BUSINESS_RULES.md` | Record decision 2 for drafts (no capacity hold; overlapping drafts allowed) and decision 6 for Draft/Active preparation versus Closed reads. Do **not** rewrite the capacity-decrease sentence (lines 84–86) as if Sprint 1 resolved it. That sentence conflicts with decision 8 and applies only once confirmed stays exist. Add a line that Sprint 1 does not implement it. |
| `API.md` | Paths for facilities, structures, units, positions, season allocations, drafts, and scopes. No confirm, arrive, check-in, checkout, or relocate path. Keep error-code TBD for overlap and over-capacity; those codes are not used in this slice. |
| `TEST_PLAN.md` | Add the Sprint 1 cases in §8. Mark overlap, capacity, check-in, and timeline rows as later. |
| `ACCEPTANCE_CRITERIA.md` | Replace the blanket “blocked on all TBD schemas and verbs” with: Sprint 1 may proceed after this scoped approval and the edits above; confirmation remains blocked on ADR §11. |
| `UI.md` | No Sprint 1 UI. Say so. |

`PermissionCatalog.php` is not edited until `PERMISSIONS.md` lists the five verbs. That edit is implementation, not part of this review.

---

## 8. What blocks Sprint 1, and what does not

### Does not block this slice

| ADR §11 item | Why it waits |
|---|---|
| Cross-season numeric capacity and `physical_capacity` | No confirmation. Detection is specified for a later slice. |
| Position-mode cap versus enabled position count | Capacity is stored, not compared to stays or to position rows. |
| Which SeasonPilgrim statuses may be confirmed | Drafts do not apply that matrix. |
| Null family group on a Family unit | Drafts do not check family or gender. |
| Checkout edges, relocation onto the same pilgrim, family-review gating, cancellation idempotency, post-close corrections, Hijri display | Not in this slice. |
| Module 18 lines 84–86 (capacity decrease may stand) | No confirmed stay exists, and this slice does not decrease capacity against occupancy. The sentence must not be implemented. It also must not be treated as settled. |

### Conditions before code, not open product rules

1. A scoped approval that explicitly limits itself to §2–§6 of this file and leaves ADR-0019 PROPOSED.
2. The module 18 edits in §7, so the catalog seed has a written source.
3. No role grants, no confirmation endpoint, no occupancy trigger.

No unresolved product rule inside ADR §11 has to be decided before that scoped approval.

---

## 9. Sprint 1 acceptance tests

From ADR §10, plus the keys in §2. Pest, tenant fixtures, no production data.

- Another tenant’s facility, unit, allocation, draft, or scope id returns 404.
- An allocation, draft, or scope from another season than `X-Season-Id` returns 404.
- Missing verb returns 403. Unauthenticated returns 401.
- Same tenant, `view` or `draft` without scope, returns 403. The list hides that facility.
- `manage_facilities` creates a facility when the actor has no scope row.
- `manage_scopes` creates the first facility grant and the first supervisor grant with no prior scope row.
- A platform user (`tenant_id` null) cannot be granted scope.
- Supervisor scope does not allow `manage_facilities` or a confirm call.
- After `revoked_at` is committed, the grantee’s next draft write returns 403.
- Draft context season: create facility, allocation, and draft succeed. Active: the same. Closed: those writes return 422 and reads still succeed for a scoped user.
- No confirm route. Inserting `booking_status` other than `draft` fails the database check.
- Two drafts, same unit, overlapping `[starts_at, effective_ends_at)`, both return 201. Capacity on the season unit is unchanged.
- A draft may omit `season_position_id` on a position-mode unit.
- `effective_ends_at` must equal `original_ends_at`, and the end must be after the start.
- Deleting a draft succeeds. Deleting a unit that a season unit still references returns a constraint error (RESTRICT).
- A season unit whose unit belongs to another facility is rejected by the database.
- A structure parent in another facility is rejected by the database.
- A parent cycle is rejected.
- Creating a draft does not change `season_pilgrims.status` and does not write a transportation row or a current-room column.
- Each create, update, deactivate, delete, grant, and revoke writes an audit row. The draft body is not an operational timeline row, because that table does not exist yet.

Do not require MySQL peak, family, or cross-season confirmation tests in this slice.

---

## 10. Scoped approval

Recommend a **separate** approval with this sentence: Backend Sprint 1 may implement only the tables, guards, verbs, and tests in `docs/reviews/ACCOMMODATION_CAMPS_SPRINT1_READINESS.md`, after the module 18 edits listed there, while ADR-0019 remains PROPOSED / NOT ACCEPTED.

That approval does not decide cross-season capacity, confirmation, family exclusivity enforcement, or administrative checkout.

Until that separate approval and the module 18 edits exist, no migration and no catalog seed.

## File created

`docs/reviews/ACCOMMODATION_CAMPS_SPRINT1_READINESS.md`

## Files modified

None.

# Season and Pilgrim Foundation — Business Rules

> **Status:** Approved invariants (F1–F10). Unspecified details are **TBD**.
> **Last updated:** 2026-10-04

Binding: [ADR-0017](../../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md).

`tenant_id` comes only from `TenantContext`. Clients never send it.

---

## F1 — Tenant and Season

- Tenant = permanent company / campaign organization.
- One Tenant has many Seasons (conceptually 1448H, 1449H, 1450H — illustration only, not a column spec).
- Do not create a Tenant per season.
- Season-specific operational data belongs to a Season.
- Permanent organization data (MVP org units, employees, users, roles, settings) stays tenant-level.

**TBD:** season business key (Hijri year, name, code, date range), whether seasons may overlap in calendar time, numbering format.

## F2 — Season lifecycle

```text
Draft → Active → Closed
```

| Status | Meaning |
|---|---|
| Draft | Preparation / setup |
| Active | Operational season |
| Closed | Completed history |

- Many Closed seasons may remain.
- **At most one Active Season** per tenant.
- Closed seasons are not deleted because a new season starts.

**TBD:** who may transition; whether Draft → Closed (skip Active) is allowed; whether Closed or Active can return to Draft; delete/archive of a Draft season that was never activated; clock used for “current” (tenant timezone vs Hijri calendar).

## F3 — Pilgrim vs SeasonPilgrim

| Concept | Meaning |
|---|---|
| Pilgrim | Stable identity inside the Tenant |
| SeasonPilgrim | That Pilgrim’s participation in one Season |

```text
Tenant → Pilgrim → SeasonPilgrim
```

Seasonal operations in Transportation and Accommodation reference **SeasonPilgrim**.

## F4 — Ownership boundaries (not a column list)

**Pilgrim** owns stable/current identity and personal information. Examples of the *kind* of data: identity information, name, gender, nationality, contact information.

**SeasonPilgrim** owns season-specific participation. Examples of the *kind* of data: season, seasonal/reference number, participation status, package/entitlement, family/group membership, supervisor/group assignment, other season-specific operational information.

These examples are **not** an approved schema. Every concrete field is **TBD** until listed in [DATA_MODEL.md](DATA_MODEL.md).

## F5 — One participation per season

A Pilgrim has at most one SeasonPilgrim in the same Season.

```text
UNIQUE (tenant, season, pilgrim)
```

Do not create a second registration for another package or group. Changes of package or group update the same SeasonPilgrim.

## F6 — Historical snapshot

- Pilgrim = current information.
- SeasonPilgrim must be able to keep a historical snapshot of identity values that must remain as they were during that season.
- Do **not** copy every Pilgrim field by default.
- The snapshot field list is **TBD**.
- When a snapshot is refreshed from current Pilgrim data, if ever, is **TBD**.

## F7 — Deletion and cancellation

| Situation | Rule |
|---|---|
| SeasonPilgrim created by mistake and never used in any operational workflow, reference, or history | Deletion may be allowed with the appropriate permission |
| SeasonPilgrim already operationally referenced | Hard delete forbidden. Cancel instead. Record **reason**, **actor**, and **timestamp** |

- Cancelling participation does not delete the Pilgrim.
- Cancelling participation does not erase Transportation or Accommodation history.

**TBD:** definition of “operationally referenced”; permission name; whether Pilgrim hard-delete exists at all; cancellation from which statuses.

## F8 — Family Group ownership

Family Group is **season-specific** and owned here, not by Accommodation.

```text
Season → Family Groups → SeasonPilgrims
```

Transportation and Accommodation store a reference only.

## F9 — Family Group membership

- Membership is optional.
- Do not create a fake one-person Family Group for a single pilgrim.
- A SeasonPilgrim belongs to at most one Family Group in the same Season.
- A Family Group supports a Primary Member.
- Moving a member between groups is auditable (audit trail).
- Removing or cancelling one member does not automatically delete the Family Group.

**TBD:** minimum size. (Primary leaving and empty-group retention are decided in the implementation decisions below.)

## F10 — SeasonPilgrim lifecycle

```text
Registered → Active → Completed

Cancelled   (side path)
```

| Status | Meaning |
|---|---|
| Registered | Added to the season; still in preparation |
| Active | Participating operationally in the season |
| Completed | Participation finished; history remains |
| Cancelled | Participation will not continue (see F7) |

`SeasonPilgrim.status` must **not** include visa, finance, accommodation, transportation, document, or payment states.

Transportation and Accommodation may read this status. They must not write it.

**TBD:** allowed edges into Cancelled; whether Completed is terminal; who may transition; whether Active requires an Active Season.

## Cross-domain

This module does not mutate Transportation or Accommodation records. It does not store current room, bed, or accommodation id.

## Notifications

No notification types are approved for this module. **TBD.**

## Implementation decisions (foundation sprint, 2026-10-05)

Applied under F1–F35. Items marked *assumption* need business confirmation.

- **Operationally referenced** (F7): a SeasonPilgrim that is not `registered`, or was ever activated, or is reported by any tagged `SeasonPilgrimOperationalReferenceChecker`. Future modules register checkers; no fake transport/accommodation checks exist.
- **Cancellation edges** (F10): from `registered` or `active`. `completed` and `cancelled` are terminal. Activation requires an **Active** season.
- **Activation requirements**: pilgrim group assigned and identity snapshot present. **Decision (hardening pass):** a program is **not** required for activation. If the business later requires it, it is added as a `SeasonPilgrimActivationRequirement` via tag `season_pilgrims.activation_requirements` without changing the activation action.
- **Snapshot**: captured at registration (full name, gender, nationality, and the selected identity document); refreshable only while `registered`; locked at activation; afterwards only an explicit, reasoned, audited correction changes it. Pilgrim edits never rewrite it.
- **Pilgrim group**: may be set/changed only while `registered`; locked from activation onward.
- **Program**: exactly one current program per participation (nullable while `registered`); every change appends history; inactive programs cannot be assigned; used programs cannot be deleted.
- **Family** (F9, revised in hardening pass): the primary member is **explicit only**: set through `PUT /season-family-groups/{id}/primary-member` (`season_family_groups.manage`), must be a current member of the same group, and every change is audited (`SEASON_FAMILY_PRIMARY_CHANGED`, before/after). Joining never makes anyone primary. A group may temporarily have **no** primary: when the primary leaves, moves, or the participation is deleted, the flag is cleared (recorded in the audit metadata) and no one is promoted automatically. Empty groups are retained until explicitly deleted; membership changes are allowed on `active` participations (independent of the pilgrim group lock) but not on terminal ones.
- **Supervisors**: Employees only (active status); one current primary, any number of assistants; an assistant requires a current primary; a new primary ends the previous one; assignment grants no permissions.
- **Closed season**: readable; every write is rejected (`SEASON_CLOSED`) with **one exception**: the historical identity snapshot correction (`POST /season-pilgrims/{id}/snapshot/correct`). It requires `season_pilgrims.correct_snapshot`, a non-empty reason, tenant isolation and policy authorization, and writes an immutable audit record (`SEASON_PILGRIM_SNAPSHOT_CORRECTED`: actor, reason, before/after, season id and status, participation status, correlation id). It does not reopen the season, does not enable any other write, and never re-reads current Pilgrim data. Reopen requires `seasons.reopen` + reason and no other Active season.
- **Season deletion**: only Draft, never activated, without participations.
- **External deletion references** (ADR-0018 T10): Season, Program, Pilgrim Group, and Family Group hard deletes also consult tagged `SeasonResourceDeletionChecker`s and fail with `SEASON_RESOURCE_REFERENCED` when another context holds history. Deactivation is never blocked.
- **Pilgrim deletion** (approved in hardening pass): hard delete only with **zero** participations; no cascade (`PILGRIM_IN_USE`, DB `RESTRICT`).
- **Identity document types**: `passport`, `national_id`, `residence_permit` are the currently supported values. No generic/speculative types. Date of birth and document expiry are out of scope.
- **Closing readiness**: contributors return OK / WARNING / BLOCKING; BLOCKING prevents close. The foundation contributor only warns about open (registered/active) participations.

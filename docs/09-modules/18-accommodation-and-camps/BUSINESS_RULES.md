# Accommodation and Camps — Business Rules

> **Status:** Approved discovery boundaries (decisions 31–100 as provided). Field-level schemas **TBD**.
> **Last updated:** 2026-10-04

`tenant_id` comes from `TenantContext` only. Seasonal operations reference **SeasonPilgrim** and a Season owned by the foundation.

---

## Source of truth

Accommodation owns occupancy. Do not store current room, bed, or accommodation id on Pilgrim or as a writable copy in another module.

The **Current Accommodation Read Model** is how other modules see current operational state. It is produced here.

It must be able to distinguish at least:

- assigned but not arrived
- arrived at facility but not checked in
- currently checked in
- checked out, with a future next assignment when one exists

Pending offline operations on a device are **not** part of global confirmed state until the server accepts them.

Whether the read model is a query or a maintained projection is **TBD**. A projection must not be writable by Transportation or the foundation.

## Time-based occupancy

The same place can be occupied by different SeasonPilgrims in **non-overlapping** periods.

One SeasonPilgrim can have **sequential** assignments. Illustration only (not a data fixture):

| Period | Place |
|---|---|
| 1–4 Dhu al-Hijjah | Makkah hotel, room 405 |
| 5–9 | Mina camp, unit/tent 206, bed 28 |
| 10–15 | Madinah hotel, another room |

Assignments are intervals, not a single current-room foreign key.

**TBD:** calendar system for those dates (Hijri stored vs Gregorian instant plus tenant timezone); whether end is inclusive; gaps between stays.

## Modes

| Mode | Meaning |
|---|---|
| Capacity-based | Place has a capacity (example: room capacity 4). Explicit bed rows are not required. |
| Position-based | Place has explicit bed/place identifiers. An assignment targets one position. |

Both honor time-based availability.

**TBD:** whether one place can switch mode after assignments exist; naming of hotel vs camp vs tent.

## Audience

Allocation supports **Male**, **Female**, and **Family**.

Family Group identity stays in the Season / Pilgrim foundation. This module stores a reference only.

**TBD:** whether audience is a property of the place, the demand, the assignment, or more than one of those.

## Planning and assignment concepts

Approved as concepts. **Structures and fields TBD:**

- accommodation stages / phases
- demand
- allocation and allocation queue
- draft assignments and confirmed assignments
- time-based availability
- capacity planning (demand compared with available capacity)
- auto allocation and incremental allocation
- family-aware allocation
- reserved family capacity
- reuse of a previous allocation as a preference
- relocation and bulk relocation
- extension
- cancellation before check-in

Do not add tables for these until a later spec names columns without guessing.

## Capacity

- Intentional overbooking is forbidden.
- Creating an assignment that exceeds **actual** capacity is forbidden.
- Changing capacity may reveal an existing over-capacity state. That revelation does not allow another assignment that would exceed capacity.
- How capacity is counted in position-based mode (number of positions vs a separate number) is **TBD**, except that the create-assignment rule still applies.

## Arrival and check-in

Operational distinction:

| State | Meaning (operational, this domain) |
|---|---|
| Assigned | Assignment exists; arrival not recorded |
| Arrived at facility | At the facility; not checked into the place/position |
| Checked-in | Checked into the accommodation place/position |
| Checked-out | Stay operationally ended |

These are **not** `SeasonPilgrim.status` values.

Transport arrival must **not** automatically mark accommodation check-in.

If the actual check-in place differs from the confirmed assignment, the UI may run relocation and check-in in one user action, but the operational history must keep **relocation** and **check-in** as two events.

**TBD:** whether “arrived at facility” is required before check-in; status names as stored enums.

## Backdated operations

Operational events may include:

| Field | Meaning |
|---|---|
| `occurred_at` | When the operation happened in the field |
| `recorded_at` | When it was entered in the system |

Business validation uses the actual operational occurrence time where that is the right clock. Which rules use `occurred_at` vs `recorded_at` beyond that sentence is **TBD**.

## Operational timeline

Separate from generic audit:

| Record | Question |
|---|---|
| Audit trail (`audit_logs`) | Who changed what in the system? |
| Accommodation operational timeline | What happened operationally to this pilgrim’s accommodation? |

Timeline events are historical and must not be silently rewritten. This is **not** a requirement to implement full event sourcing. Corrections, if any, are **TBD** (no silent rewrite).

Critical system changes still go through `Core/Audit` as well.

## Offline (later slice — do not implement now)

Approved for selected field operations such as check-in and check-out:

- locally queued operation
- UUID / idempotency
- Pending Sync
- server validation
- Sync Conflict
- no last-write-wins for sensitive occupancy rules
- a conflict-resolution workflow

Device pending state is not global source of truth until the server accepts it. Full offline mode for the whole ERP stays out of scope.

## QR (direction approved — details TBD)

- Pilgrim QR
- Accommodation space QR
- Position QR where the place is position-based

Payload, signing, expiry, and permissions are **TBD**.

## Notifications

Business notification configuration (what accommodation events should notify, and the configuration model) is in scope as a **concept**. It is separate from delivery.

Delivery uses the existing **in-app** notification module only ([ADR-0013](../../10-decisions/ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md)). Email, SMS, WhatsApp, and push are not approved.

Which events, recipients, and templates exist is **TBD**. Do not add `NotificationType` cases in this sprint.

## Cross-domain

- Read SeasonPilgrim status. Do not write it.
- Read Family Group. Do not create or dissolve groups here.
- Do not mutate Transportation records.
- Cancelling a SeasonPilgrim (foundation) must not erase accommodation history (foundation rule F7). This module must retain timeline rows.

## Security notes (no new product rules)

Private files, if attachments are added later, use the Documents module. QR and offline queues increase replay and double-assignment risk; controls are **TBD** with those slices. Cross-tenant access remains 404.

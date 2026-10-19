# Accommodation and Camps — Business Rules

> **Status:** Sprint 1–3 behavior implemented. Sprint 3 **ACCEPTED** (`docs/reviews/ACCOMMODATION_SPRINT3_ACCEPTANCE.md`). ADR-0019 remains **PROPOSED / NOT ACCEPTED**.
> **Last updated:** 2026-10-19

`tenant_id` comes from `TenantContext` only. Seasonal operations reference **SeasonPilgrim** and a Season owned by the foundation.

---

## Backend Sprint 1 (implemented)

This section is the behavior of the ten tables in [DATA_MODEL.md](DATA_MODEL.md) for preparation and drafts. It does not confirm stays.

**Identity.** A hotel or camp, its optional building or floor, its rooms or tents, and its beds or positions persist for the tenant across seasons. A season allocation selects a subset and stores that season’s mode, capacity number, and audience. In Sprint 1, storing the number does not enforce it against stays.

**Drafts.** A draft assignment does not reserve capacity, a position, or a pilgrim. Two drafts may share one unit and one overlapping interval. Both are kept. Gender and family group are not checked on draft write. `booking_status` is only `draft` until Sprint 2 confirmation. Operational status is null. The effective end equals the original end, and the end is after the start (half-open `[starts_at, effective_ends_at)`). Dates are Gregorian UTC. Hijri display is still **TBD** and is not this slice.

**Season guards (preparation / drafts).** Context season comes from `SeasonContext` / `X-Season-Id`.

| Context season | Facility, structure, unit, position, season allocation, draft write, scope grant or revoke | Scoped read |
|---|---|---|
| Draft | Allowed | Allowed |
| Active | Allowed | Allowed |
| Closed | Rejected (`SeasonGuard::assertWritable`) | Allowed |

Preparation and drafts do **not** use `assertActive`.

**Authorization (Sprint 1).** `manage_facilities`, `manage_season_allocation`, and `manage_scopes` do not require an existing facility-scope row. `view` and `draft` do. Details: [PERMISSIONS.md](PERMISSIONS.md).

---

## Backend Sprint 2 (scoped-approved — confirmation & capacity)

Authority: product-owner decisions D1–D7 in `ACCOMMODATION_SPRINT2_SCOPED_APPROVAL.md`. Implementation is gated (Gate B+); this section is the binding product rule set for that slice. It does **not** accept ADR-0019.

### Confirmation eligibility

- Context season must be **Active** (`SeasonGuard::assertActive`).
- SeasonPilgrim status must be `registered` or `active`. Reject `completed` and `cancelled` (**D1**).
- Gender must be known (nullable gender rejects confirmation), including for Family units.
- Unit audience (and optional facility audience) must match.
- Verb `accommodation.confirm` plus facility or supervisor scope. Scope is re-checked inside the write transaction.

### Capacity and positions

- **Numeric (capacity) mode:** peak concurrent **confirmed** stays on the season unit over the candidate interval must not exceed season-unit `capacity`. Use half-open overlap on `starts_at` / `effective_ends_at`. Drafts do not count.
- **Position mode (D3):** operational capacity equals the count of **enabled** season positions, subject to the configured `capacity` **ceiling** (effective cap = minimum of enabled count and ceiling). The assignment must target an enabled season position. That position must not have an overlapping confirmed assignment. Unit peak still respects the effective cap.
- **Cross-season (D4):** detection-only fail-closed. If any other season has an overlapping **confirmed** interval on the same stable `accommodation_units` row, reject. Do not invent a sharing formula or `physical_capacity`. Unused numeric capacity in either season does not allow the overlap.
- **Same SeasonPilgrim:** no overlapping confirmed intervals across units/facilities.

### Family (D2)

- Confirmation into a Family unit requires a non-null, **valid** Foundation `family_group_id` for that tenant/season (snapshotted onto the assignment at confirm).
- Different family-group snapshots cannot overlap in one Family unit (approved exclusivity).
- Family-group confirmation is **atomic** (all members succeed or none).

### Capacity-decrease safety (D5)

Reject a season capacity **decrease**, season-position **deactivation** (or removal from the season allocation), or unit **deallocation** that would conflict with any **confirmed** assignment interval. This preserves ADR provisional decision 8 for the scoped Sprint 2 implementation.

The older Module 18 sentence that allowed capacity reductions to merely “reveal” over-capacity **without** rejecting the decrease is **superseded for Sprint 2** by D5. Sprint 1 draft-only behavior is unchanged until confirmed rows exist.

### Explicitly not in Sprint 2

Arrival, check-in, check-out, relocation, extension, staff cancel of confirmed stays, `SeasonPilgrimCancelled` listeners, historical corrections, post-Closed writes, and cross-season sharing formulas. (Staff cancel and ops moves to Sprint 3 scoped approval.)

---

## Backend Sprint 3 (scoped-approved — operations; Gates A–C implemented)

Authority: product-owner decisions **B1–B5** in `ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md`. Implementation is gated (Gate B+). This section does **not** accept ADR-0019. Sprint 1 draft behavior and Sprint 2 confirmation (D1–D7) remain binding.

### Booking vs operational status

- **Booking** `booking_status`: `draft` → `confirmed` → `cancelled` (staff cancel **before** check-in only); `confirmed` → `completed` after check-out (or after checked-in relocate source close).
- **Operational** `operational_status`: `assigned` → `arrived` → `checked_in` → `checked_out`, or skip arrival. Sprint 2 confirmed rows may have `operational_status` null — treat as **assigned** for eligibility.
- Dimensions stay distinct from each other and from `SeasonPilgrim.status`.

### Season and authorization

- All Sprint 3 ops writes require **Active** season (`SeasonGuard::assertActive`).
- Facility or supervisor scope; relocate requires **source and destination** (or supervisor). Scope re-checked inside the write transaction after locks.

### Arrival, check-in, check-out

- Arrival is **optional** before check-in.
- Check-out requires prior check-in. Do not reinterpret rejected checkout as cancel (**B1**).
- **B1:** reject checkout when `occurred_at < starts_at`.
- **B2:** late checkout after `original_ends_at` is allowed; record `actual_departed_at`; **never** auto-raise `effective_ends_at` above `original_ends_at`; no automatic reservation extension.
- Early/on-plan checkout: set `effective_ends_at` to release future capacity when before `original_ends_at`; preserve `original_ends_at`; `checkout_source=staff`; `booking_status=completed`; `operational_status=checked_out`.

### Staff cancel (B4) and drafts (B5)

- Authorized staff may cancel **confirmed** assignments **before** check-in (`accommodation.cancel_assignment`). Cancelled rows are excluded from occupancy. History retained.
- Cancel after check-in is rejected (use check-out).
- **Defer** `SeasonPilgrimCancelled` listener — no Foundation-triggered Accommodation cancel in Sprint 3.
- Drafts are **not** auto-cancelled (B5).

### Relocation (B3)

- Atomic: shorten source `effective_ends_at`, create destination confirmed assignment, validate destination with Sprint 2 capacity/overlap/family/cross-season rules.
- Inside that transaction only, exclude the source assignment from same-pilgrim self-overlap so relocate is not falsely rejected; still reject all other conflicts. No invalid overlapping confirmed state after commit. Preserve source row history.
- Checked-in relocate: source completes/checked_out; destination may optionally check in in the same transaction — always **two** timeline events when combined with check-in.
- Pre-check-in relocate: source booking cancelled (no invented checkout); destination confirmed.

### Occupancy

Count `booking_status IN ('confirmed','completed')` on half-open `[starts_at, effective_ends_at)`. Exclude draft and cancelled. Peak concurrent evaluation unchanged in spirit from Sprint 2.

### Explicitly not in Sprint 3

Listener, automatic draft cancel, extension, historical correction, post-Closed writes, `physical_capacity` formula, role-template grants, ADR acceptance.

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

Whether the read model is a query or a maintained projection is **TBD**. A projection must not be writable by Transportation or the foundation. Sprint 2 confirmation does not require shipping the full read model.

## Time-based occupancy

The same place can be occupied by different SeasonPilgrims in **non-overlapping** periods.

One SeasonPilgrim can have **sequential** confirmed assignments (gaps allowed). Half-open Gregorian UTC intervals apply.

## Modes

| Mode | Meaning |
|---|---|
| Capacity-based | Place has a capacity (example: room capacity 4). Explicit bed rows are not required on the assignment. |
| Position-based | Place has explicit bed/place identifiers. A confirmed assignment targets one enabled position (D3). |

**TBD (not Sprint 2):** whether one place can switch mode after confirmed assignments exist.

## Audience

Allocation supports **Male**, **Female**, and **Family**.

Family Group identity stays in the Season / Pilgrim foundation. This module stores a snapshot reference on confirmed assignments.

Sprint 1 **stores** audience without enforcing it on drafts. Sprint 2 **enforces** audience and family rules at confirmation (D2).

## Planning and assignment concepts

Approved as concepts. Structures beyond Sprint 1–2 tables remain **TBD** (demand, queue, auto-allocation, reserved family capacity, etc.).

## Arrival and check-in

Operational distinction (Sprint 3 scoped-approved — see Backend Sprint 3 above):

| State | Meaning (operational, this domain) |
|---|---|
| Assigned | Assignment exists; arrival not recorded |
| Arrived at facility | At the facility; not checked into the place/position |
| Checked-in | Checked into the accommodation place/position |
| Checked-out | Stay operationally ended |

These are **not** `SeasonPilgrim.status` values. Transport arrival must **not** automatically mark accommodation check-in.

## Backdated operations / operational timeline / offline / QR / notifications

Sprint 3 introduces append-only `accommodation_events` with `occurred_at` / `recorded_at` for ops verbs (see scoped approval). Timeline remains separate from `Core/Audit`. Offline and QR later; in-app notifications only.

## Cross-domain

- Read SeasonPilgrim status. Do not write it.
- Read Family Group. Do not create or dissolve groups here.
- Do not mutate Transportation records.
- Cancelling a SeasonPilgrim (foundation) must not erase accommodation history (foundation rule F7). Automatic accommodation release on cancellation is **not** Sprint 2 and remains **deferred** in Sprint 3 (B4).

## Security notes (no new product rules)

Private files, if attachments are added later, use the Documents module. Cross-tenant access remains 404.

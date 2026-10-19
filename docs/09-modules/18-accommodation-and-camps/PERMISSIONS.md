# Accommodation and Camps — Permissions

> **Status:** Sprint 1 five verbs plus Sprint 2 `accommodation.confirm` are seeded with **zero** role-template grants. Sprint 3 ops verbs are catalogued (Gate B) and enforced by ops APIs (Gate C) with **zero** role-template grants. ADR-0019 remains **PROPOSED / NOT ACCEPTED**.
> **Last updated:** 2026-10-19

## Namespace

`accommodation.*`

No `camps.*` split.

## Sprint 1 verbs (implemented)

| Verb | Power | Facility-scope row required? |
|---|---|---|
| `accommodation.view` | Read facilities, allocations, and drafts that the actor may see | Yes |
| `accommodation.manage_facilities` | Create and maintain stable facilities, structures, units, and positions | **No.** |
| `accommodation.manage_season_allocation` | Select facilities, units, and positions for the context season and store mode, capacity, and audience | **No.** After Sprint 2, D5 rejects decreases that conflict with confirmed stays. |
| `accommodation.draft` | Create, update, and delete non-reserving drafts | Yes |
| `accommodation.manage_scopes` | Grant and revoke facility scope and season supervisor scope | **No.** |

## Sprint 2 verb (scoped-approved — seed with no role grants)

| Verb | Power | Facility-scope row required? |
|---|---|---|
| `accommodation.confirm` | Confirm a draft (individual or atomic family batch) under Active season and capacity/family rules | Yes (facility or supervisor scope) |

Supervisor scope widens the facility set for `view`, `draft`, `confirm`, and Sprint 3 facility-scoped ops. It never grants a verb by itself.

Missing verb: 403. Same tenant, scoped verb, no live scope: 403. Another tenant: 404. Another season: 404.

Confirm and Sprint 3 ops require Active context season (`assertActive`). Preparation verbs still use Draft or Active (`assertWritable`).

Revocation is re-read on the next scoped write, including confirm and ops inside the transaction after locks.

## Sprint 3 verbs (implemented — catalogued Gate B; enforced Gate C; no role grants)

| Verb | Power | Facility-scope row required? |
|---|---|---|
| `accommodation.arrive` | Record facility arrival on a confirmed assignment | Yes (facility or supervisor) |
| `accommodation.check_in` | Check in (arrival not required) | Yes |
| `accommodation.check_out` | Check out after check-in (B1/B2 clocks) | Yes |
| `accommodation.relocate` | Atomic relocate; authorize **source and destination** (or supervisor) | Yes (both facilities, or supervisor) |
| `accommodation.cancel_assignment` | Cancel confirmed assignment **before** check-in only (B4) | Yes |

## Not seeded in Sprint 1–3

Do not seed, and do not grant:

- `accommodation.extend`
- `accommodation.correct_history`
- `accommodation.resolve_family_review`

## Role grants

**None** for Sprint 1, Sprint 2, or Sprint 3 verbs. Role-template grants require a **separate** approval. Which roles will later operate check-in or cancel is still **TBD**.

Policies remain authoritative. UI `can()` / `permissionsAny` is visibility only ([UI.md](UI.md)).

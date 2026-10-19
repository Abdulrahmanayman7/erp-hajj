# Accommodation & Camps — Sprint 3 Scoped Approval & Implementation Plan

> **Type:** Documentation only. No application code, migration, seed, API, frontend, or listener change was made in this pass.  
> **Approval date:** 2026-10-19  
> **Authority:** Explicit product-owner approval of decisions **B1–B5** recorded below.  
> **Prerequisites:** Sprint 1 **ACCEPTED**; Sprint 2 **ACCEPTED** (`ACCOMMODATION_SPRINT2_ACCEPTANCE.md`).  
> **Readiness supersession:** `ACCOMMODATION_SPRINT3_READINESS.md` recorded **BLOCKED** for implementation kickoff pending this scoped approval. That historical verdict is **preserved**. This file is the superseding Gate A authority for Sprint 3 operations.  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Gate A status:** Complete when Module 18 docs are aligned with this file (see §17).  
> **Implementation:** Not started. Do not begin Gate B automatically from this documentation pass.

This scoped approval is **narrower than ADR-0019**. It does **not** accept the ADR. It authorizes only the Sprint 3 operations slice named in §2. Sprint 1 and Sprint 2 accepted behavior remain binding.

**Existing operational baseline (not reopened):** arrival optional before check-in; Active season required for operational writes; booking status distinct from operational status; check-out requires prior check-in; early checkout releases future capacity via `effective_ends_at` while preserving `original_ends_at`; relocation atomically releases source and reserves destination; checked-in relocation preserves check-in semantics and separate audit/timeline history; facility and season supervisor scopes enforced; relocate requires both facilities or supervisor scope; deterministic locking and InnoDB concurrency safety mandatory.

---

## 1. Explicit product-owner approval of B1–B5

| # | Approved decision (exact) |
|---|---|
| **B1** | Reject checkout before the assignment’s `starts_at`. Do **not** reinterpret checkout as booking cancellation. |
| **B2** | Allow late recording of an actual departure after `original_ends_at`. Do **not** automatically extend reserved capacity beyond `original_ends_at`. Preserve actual departure time separately where required. Never make `effective_ends_at` exceed `original_ends_at` automatically. |
| **B3** | Allow the source assignment to be treated specially **only inside** the atomic relocation transaction, to avoid a false self-overlap. Revalidate destination inventory and all other conflicting assignments. Do not leave an invalid overlapping confirmed booking state after commit. Preserve source assignment history. |
| **B4** | First capacity-release package: include authorized **staff** cancellation of confirmed assignments **before check-in**. **Defer** the `SeasonPilgrimCancelled` listener. No automatic Foundation-triggered cancellation in Sprint 3. |
| **B5** | Existing drafts remain unchanged. Do **not** automatically cancel drafts in Sprint 3. |

---

## 2. Authorized Sprint 3 operations and exclusions

### Authorized

| Operation | Verb | Notes |
|---|---|---|
| Arrival recording | `accommodation.arrive` | Optional; Active only |
| Check-in | `accommodation.check_in` | Arrival not required; Active only |
| Check-out | `accommodation.check_out` | Requires prior check-in; B1/B2 clock rules; Active only |
| Relocation | `accommodation.relocate` | Atomic source release + destination reserve; optional combined check-in → two events; B3; Active only |
| Staff cancel confirmed (pre-check-in) | `accommodation.cancel_assignment` | B4; capacity release; Active only |
| Schema / events / capacity semantics | — | Widen assignment CHECK; add `actual_departed_at`; create `accommodation_events` if still absent; occupancy includes completed until `effective_ends_at` |
| Permissions seed | ops verbs above | **Zero** role-template grants |
| Concurrency tests | MariaDB / `erp_hajj_testing` | §13 |
| Frontend ops actions | After Gate C | §14 |

Sprint 2 confirmation rules (D1–D7) remain binding for destination reservation during relocate and for all confirmed-interval capacity checks.

### Explicit exclusions

- `SeasonPilgrimCancelled` listeners / cancellation tasks / Foundation-triggered auto cancel  
- Automatic cancellation of drafts (B5)  
- Historical correction workflows / `accommodation.correct_history`  
- Stay extension / `accommodation.extend`  
- Post-Closed operational writes  
- Predictive occupancy or analytics  
- Unrelated Foundation or Transportation changes  
- Transport-triggered check-in  
- Family compatibility reviews table / gating  
- `physical_capacity` / cross-season sharing formula (D4 detection-only remains)  
- Automatic role-template grants  
- ADR-0019 acceptance  

---

## 3. Operational state-transition matrix

Treat Sprint 2 `booking_status=confirmed` with `operational_status IS NULL` as **assigned** for eligibility. First ops write materializes `assigned`, `arrived`, or `checked_in` as appropriate.

| From (booking / operational) | Action | To (booking / operational) | Active? | Capacity |
|---|---|---|---|---|
| confirmed / null\|assigned | arrive | confirmed / arrived | Yes | Unchanged (still reserved through `effective_ends_at`) |
| confirmed / null\|assigned\|arrived | check_in | confirmed / checked_in | Yes | Unchanged |
| confirmed / checked_in | check_out | completed / checked_out | Yes | Per §5 (B1/B2) |
| confirmed / null\|assigned\|arrived | cancel_assignment | cancelled / (unchanged) | Yes | Full remaining interval released (cancelled excluded from occupancy) |
| confirmed / checked_in | cancel_assignment | **Reject** | — | Use check-out |
| confirmed / * (not terminal) | relocate | Source closed (§7) + new confirmed destination | Yes | Atomic release + reserve |
| confirmed / * + `check_in_after` | relocate + check-in | Destination confirmed / checked_in; **two** events | Yes | Same |
| draft / * | any Sprint 3 ops verb | **Reject** | — | — |
| completed\|cancelled / * | arrive / check_in / relocate / cancel | **Reject** (idempotent no-op only for already-cancelled cancel / already-checked-out checkout) | — | — |

**Illegal:** checkout without check-in; cancel after check-in; Draft/Closed ops writes; reinterpreting B1 reject as cancel.

**Idempotent (engineering):** arrive when arrived; check-in when checked_in; check-out when checked_out; cancel when cancelled — success no-op, no duplicate event when idempotency key matches.

---

## 4. Booking status vs operational status semantics

| Dimension | Column | Values in Sprint 3 | Role |
|---|---|---|---|
| **Booking** | `booking_status` | `draft` → `confirmed` → `cancelled` (staff cancel **before** check-in only); `confirmed` → `completed` (after check-out, or source close after checked-in relocate) | Capacity / overlap eligibility |
| **Operational** | `operational_status` | `assigned` → `arrived` → `checked_in` → `checked_out`, or `assigned` → `checked_in` → `checked_out` | Facility presence |

Do **not** collapse these dimensions. Do not store them on SeasonPilgrim. Transport arrival must not set Accommodation check-in.

**Occupancy filter (Sprint 3):** count intervals where `booking_status IN ('confirmed','completed')` using half-open `[starts_at, effective_ends_at)`. Exclude `draft` and `cancelled`. Peak/position/pilgrim/family/D4 checks reuse Sprint 2 guards with this filter (completed rows contribute only until `effective_ends_at`).

---

## 5. Actual departure time vs reserved interval semantics (B1 / B2)

| Clock case | Rule |
|---|---|
| Checkout `occurred_at` **&lt; `starts_at`** | **Reject** (B1). Do not cancel the booking. |
| Checkout `occurred_at` in `[starts_at, original_ends_at]` | Early or on-plan: set `effective_ends_at = occurred_at` when `occurred_at < original_ends_at`; if equal to `original_ends_at`, leave `effective_ends_at = original_ends_at`. Set `actual_departed_at = occurred_at`. `booking_status=completed`, `operational_status=checked_out`, `checkout_source=staff`. |
| Checkout `occurred_at` **&gt; `original_ends_at`** | Late recording allowed (B2). Set `actual_departed_at = occurred_at`. **Do not** raise `effective_ends_at` above `original_ends_at` (keep `effective_ends_at = original_ends_at`). Complete booking / checked_out as above. **No automatic reservation extension.** |

Invariants:

- `original_ends_at` never rewritten by checkout.  
- After any successful checkout: `effective_ends_at <= original_ends_at`.  
- Capacity after `effective_ends_at` is free; late physical presence is recorded only in `actual_departed_at` + timeline event, not as extra reserved capacity.

---

## 6. Staff cancellation behavior and capacity release (B4 / B5)

| Topic | Rule |
|---|---|
| Eligibility | `booking_status=confirmed`; operational not `checked_in` / not `checked_out`; Active season; facility or supervisor scope |
| Effect | `booking_status=cancelled`; row retained; `effective_ends_at` unchanged (cancelled rows excluded from occupancy); append `cancelled` event with `source=staff` |
| After check-in | **Reject** — use check-out |
| Drafts | Unchanged (B5). Staff cancel does **not** target drafts (draft delete remains Sprint 1 `draft` verb) |
| Listener | **Not implemented** in Sprint 3 (B4) |
| Foundation cancel | No automatic Accommodation release in this sprint |

Capacity-release paths in Sprint 3: early/on-plan checkout; late checkout (no extension); staff pre-check-in cancel; relocate source close (§7).

---

## 7. Relocation — source/destination history and interval rules (B3)

### Atomic transaction

1. Lock stable units (source + destination) by ascending `id`, then pilgrim lock, season units/positions, assignments, scopes.  
2. Shorten source `effective_ends_at` to relocate `occurred_at` (must be `>= starts_at` and `<= original_ends_at`; reject otherwise — fail closed, no invented extension).  
3. **B3:** When validating same-pilgrim overlap for the destination interval, **exclude the source assignment** (already shortened / closing in this transaction) so self-overlap is not falsely detected. Still reject overlap with **any other** confirmed/completed assignment for that pilgrim.  
4. Create destination assignment as `confirmed` (new row); copy/snap family group as required by D2 for Family destination; validate peak/position/D3/D4/family/audience/gender/D1 as at confirm.  
5. If source was `checked_in`: set source `booking_status=completed`, `operational_status=checked_out`, `checkout_source=staff` (administrative close of source stay; not evidence of physical camp departure beyond relocate). Preserve check-in history on the source row and prior events.  
6. If source was not checked-in: set source `booking_status=cancelled` (release without inventing checkout). Operational status left as assigned/arrived/null.  
7. Optional `check_in_after`: set destination `operational_status=checked_in` in the **same** transaction.  
8. Append timeline: always `relocated` (source and/or destination linkage in event payload); if check-in after, also `checked_in` — **two events**.  
9. Commit. Never delete the source assignment.

### History

- Source row retained forever for the tenant.  
- Optional additive FK `relocated_from_assignment_id` on the destination row (nullable) for chain navigation — see §8.  
- No erasure of prior confirm/arrive/check-in events.

### Authorization

`accommodation.relocate` plus live scope on **both** source and destination facilities, or season supervisor scope. Re-check after locks.

---

## 8. Data-model and migration plan (Gate B — not executed now)

### Why additive migration is required

Current Sprint 2 CHECK (`acc_asg_booking_ck`) allows only `draft|confirmed` with `operational_status` null, `checkout_source` null, and `effective_ends_at = original_ends_at`. That **cannot** represent arrived/checked-in/checked-out, cancelled, completed, early checkout, or late `actual_departed_at`. `accommodation_events` was named in Sprint 2 docs but **was not delivered** (confirm uses `Core/Audit` only).

### Minimal Gate B plan (`erp_hajj_testing` first; never target `erp_hajj` from Pest)

1. **Add** nullable `actual_departed_at` (`dateTime`) on `accommodation_assignments` (B2).  
2. **Add** nullable `relocated_from_assignment_id` FK to `accommodation_assignments` (same tenant) — additive history link for relocate destination; RESTRICT.  
3. **Replace** `acc_asg_booking_ck` with status-aware branches:  
   - **draft:** unchanged Sprint 1 predicates (`actual_departed_at` null; `relocated_from_assignment_id` null).  
   - **confirmed:** `operational_status` in (`null`,`assigned`,`arrived`,`checked_in`); `checkout_source` null; `effective_ends_at <= original_ends_at`; `effective_ends_at > starts_at`; `actual_departed_at` null.  
   - **cancelled:** pre-check-in close; `operational_status` not `checked_in`/`checked_out`; `checkout_source` null; `actual_departed_at` null; interval columns retained.  
   - **completed:** `operational_status='checked_out'`; `checkout_source` in allowed staff values for this sprint (`staff`); `effective_ends_at <= original_ends_at`; `effective_ends_at > starts_at`; `actual_departed_at` null or set (required when checkout path set it).  
4. **Create** `accommodation_events` (append-only): `tenant_id`, `season_id`, `assignment_id`, `event_type`, `occurred_at`, `recorded_at`, `actor_user_id` nullable, `source`, `idempotency_key`, `correlation_id`, optional JSON/meta for relocate pair ids — unique idempotency per tenant.  
5. **Seed** verbs: `arrive`, `check_in`, `check_out`, `relocate`, `cancel_assignment` — **zero** role-template grants.  
6. **Application occupancy filter** update: include `completed` until `effective_ends_at` (not schema-only).

Rollback: only when no completed/cancelled/ops rows exist (or reverse carefully on testing DB).

**Not created in Sprint 3:** `accommodation_cancellation_tasks`, `accommodation_family_reviews`.

---

## 9. API endpoints, request/response contracts, and error-code plan

Base: `/api/v1`, `auth:sanctum`, `tenant.active`, `season.context`. Envelope: `success` / `message` / `code` / optional `errors` / `data`.

| Method | Path | Verb | Body (frozen at Gate C Form Requests) |
|---|---|---|---|
| `POST` | `/accommodation/assignments/{id}/arrive` | `accommodation.arrive` | Optional `{ "occurred_at": iso8601 }` (default now UTC) |
| `POST` | `/accommodation/assignments/{id}/check-in` | `accommodation.check_in` | Optional `occurred_at` |
| `POST` | `/accommodation/assignments/{id}/check-out` | `accommodation.check_out` | Optional `occurred_at` (B1/B2) |
| `POST` | `/accommodation/assignments/{id}/relocate` | `accommodation.relocate` | `destination_season_unit_id`, optional `destination_season_position_id`, optional `occurred_at`, optional `check_in_after` bool |
| `POST` | `/accommodation/assignments/{id}/cancel` | `accommodation.cancel_assignment` | Optional reason string (audit/timeline); no Foundation side effects |

Responses: assignment resource including `booking_status`, `operational_status`, `starts_at`, `original_ends_at`, `effective_ends_at`, `actual_departed_at`, `checkout_source`, `relocated_from_assignment_id` when present. Relocate returns source + destination (or destination with source id).

### Error codes

Reuse Sprint 2 D7 unchanged for destination inventory conflicts:

| Code | HTTP | When |
|---|---|---|
| `ACCOMMODATION_PILGRIM_OVERLAP` | 422 | Same-pilgrim conflict (after B3 source exclusion) |
| `ACCOMMODATION_CAPACITY_CONFLICT` | 422 | Peak/position/D3/D4/D5 class conflicts on destination or remaining capacity rules |
| `ACCOMMODATION_FAMILY_EXCLUSIVITY` | 422 | Family destination rules |

Sprint 3 transition / clock codes:

| Code | HTTP | When |
|---|---|---|
| `ACCOMMODATION_ILLEGAL_TRANSITION` | 422 | Wrong booking/ops state for the verb (e.g. checkout without check-in; cancel after check-in; ops on draft/terminal) |
| `ACCOMMODATION_CHECKOUT_BEFORE_START` | 422 | B1 |
| `ACCOMMODATION_RELOCATE_INTERVAL_INVALID` | 422 | Relocate `occurred_at` outside `[starts_at, original_ends_at]` |

Reuse existing: season not active / closed, 401, 403 (verb or scope), 404 cross-tenant/season, `ACCOMMODATION_INTERVAL_INVALID`, `ACCOMMODATION_INVALID_REFERENCE`.

Do not invent further business codes without another approval.

---

## 10. Permission catalog plan (no automatic role grants)

| Verb | Scope rule |
|---|---|
| `accommodation.arrive` | Facility of assignment **or** season supervisor |
| `accommodation.check_in` | Same |
| `accommodation.check_out` | Same |
| `accommodation.cancel_assignment` | Same |
| `accommodation.relocate` | **Source and destination** facilities, **or** season supervisor |

Seed all five with **zero** role-template grants. Supervisor widens facilities only; never grants a verb. Revocation re-checked inside the write transaction after locks. UI `can()` is visibility only.

---

## 11. Transaction and deterministic lock strategy

```
DB::transaction(fn () => {
  // 1. Stable accommodation_units for all touched units, ORDER BY id ASC, lockForUpdate
  // 2. accommodation_pilgrim_locks for affected pilgrims, ORDER BY season_pilgrim_id
  // 3. Season units / season positions / overlapping assignments, ORDER BY id, lockForUpdate
  // 4. Locking occupancy reads (SELECT … FOR UPDATE) — mandatory (Sprint 2 Gate E)
  // 5. Re-check live facility / supervisor scope (relocate: both facilities)
  // 6. SeasonGuard::assertActive
  // 7. Transition + B1/B2/B3 rules; destination D1–D4 / family / gender / audience
  // 8. Append accommodation_events + Core/Audit on commit only
}, attempts: 3);
```

Never `lockForUpdate` on `season_pilgrims` for occupancy serialization. Never partial relocate commit. Family **confirm** atomicity remains Sprint 2; Sprint 3 relocate is per-assignment (no invented family-wide relocate batch).

---

## 12. Audit and event integrity plan

| Layer | Role |
|---|---|
| `Core/Audit` | Security-relevant mutations (arrive/check-in/check-out/relocate/cancel) with correlation id; never secrets |
| `accommodation_events` | Append-only operational timeline: `arrived`, `checked_in`, `checked_out`, `relocated`, `cancelled` (+ `confirmed` optional backfill not required for old rows) |

Rules:

- `occurred_at` (business time) vs `recorded_at` (server accept time) always both set.  
- Relocate + check-in → two event rows, one transaction.  
- Idempotency key: `(tenant_id, assignment_id, event_type, source, occurred_at)` or equivalent unique; duplicate key → no-op.  
- Timeline ≠ audit viewer.  
- No listener-driven events in Sprint 3.

---

## 13. Concurrency test matrix

| ID | Race | Expect |
|---|---|---|
| C1 | Two check-ins same assignment | One wins or idempotent; single checked_in event |
| C2 | Check-out vs relocate (checked-in) | Exactly one wins; no double release / no orphan destination |
| C3 | Relocate A→B vs confirm other pilgrim into B | Capacity/position integrity |
| C4 | Relocate vs confirm same pilgrim elsewhere overlapping | `ACCOMMODATION_PILGRIM_OVERLAP` for loser |
| C5 | Early checkout vs D5 capacity decrease | No invalid under-cap state |
| C6 | Staff cancel vs check-in | One wins; never cancel after check-in committed |
| C7 | Dual relocate A↔B | Deterministic unit lock order; no deadlock beyond retries |
| C8 | Late checkout (B2) concurrent with confirm into freed post-`original_ends_at` slot | Confirm may succeed; late checkout must not extend `effective_ends_at` |
| C9 | B3 relocate destination overlapping source’s **original** interval after source shorten | Allowed only if no other conflicts; post-commit no overlapping confirmed pair for pilgrim |

MariaDB / `erp_hajj_testing` only; independent processes; `erp_hajj` untouched.

---

## 14. Frontend implementation plan (Gate D — after Gate C)

| Surface | Work |
|---|---|
| Assignments | Arrive / Check-in / Check-out / Relocate / Cancel (pre-check-in) behind verbs + Active season |
| Badges | Booking + operational labels |
| Relocate drawer | Destination unit/position; optional check-in-after; explain atomicity |
| Checkout | Allow late `occurred_at`; surface B1 error; never client-extend capacity |
| Errors | Map D7 + Sprint 3 transition/clock codes |
| Absent | Listener UI; draft auto-cancel; extension; correction; transport check-in |

Vitest + `vue-tsc`. Browser E2E remains Sprint 2 verification debt unless separately staffed.

---

## 15. Gate B–E acceptance criteria

| Gate | Content | Exit criteria |
|---|---|---|
| **A** | This scoped approval + Module 18 alignment | This file; B1–B5 recorded; ADR still PROPOSED; readiness pointer added |
| **B** | Migrations on `erp_hajj_testing`: `actual_departed_at`, CHECK widen, `accommodation_events`, optional `relocated_from_assignment_id`, permission seeds | Schema tests; Sprint 1/2 draft+confirm CHECKs preserved for those branches; `erp_hajj` untouched |
| **C** | Backend ops APIs + staff cancel; capacity release; B1–B3; locks; audit/events; security tests | Pest MariaDB green; 401/403/404/422 matrix; concurrency C1–C9 as applicable |
| **D** | Frontend ops UX | Vitest + type-check; no excluded surfaces |
| **E** | Integrated acceptance | Evidence-typed matrix; no false browser PASS; Sprint 1/2 regressions green |

**Sprint 3 slice acceptance (future):** authorized verbs only; invariants in §16 hold; listener still absent; ADR-0019 still PROPOSED; zero role-template grants.

**Do not begin Gate B in this documentation pass.**

---

## 16. Integrity invariants (must hold after every operation)

| Invariant | How preserved |
|---|---|
| No over-capacity | Peak/position/D3 under locks; locking reads; occupancy filter §4 |
| No invalid same-pilgrim overlapping reservations after relocate | B3 source exclusion only inside txn; revalidate others; post-commit assert |
| No premature capacity release | Cancel only pre-check-in; checkout only after check-in; relocate shortens at `occurred_at` within interval |
| No loss of historical source assignments | Source row retained; events append-only; optional `relocated_from_assignment_id` |
| No cross-tenant or cross-season access | Tenant scope + season context; 404 |
| No bypass of revoked facility scopes | Re-check after locks; relocate both facilities |
| No partial family or relocation commits | Single transaction; relocate all-or-nothing; no invented family relocate batch |
| No automatic reservation extension from late departure | B2: `effective_ends_at` never auto-raised past `original_ends_at`; `actual_departed_at` holds late time |

---

## 17. Existing Sprint 2 verification debt

| Item | Class | Carry into Sprint 3 |
|---|---|---|
| Browser E2E not executed | Verification debt | Manual QA still required before operator cutover |
| Production frontend build not run | Verification debt | Re-run when disk allows |
| MySQL 8 CI unproven on this machine | Verification debt | MariaDB proven for Sprint 2; Gate E minimum MariaDB multi-process |
| ADR-0019 remains PROPOSED | Process fact | Unchanged by this approval |
| Confirmed cancel/release (pre-Sprint 3) | **Addressed in scope** by B4 staff cancel + checkout/relocate paths; listener still deferred |

---

## 18. ADR-0019 statement

**ADR-0019 remains PROPOSED / NOT ACCEPTED.**

This scoped approval authorizes only the Sprint 3 operations slice described here. It does not accept the ADR, does not authorize listeners, extension, corrections, or Closed-season writes, and does not change ADR status in `docs/10-decisions/ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md`.

---

## 19. Remaining technical blockers

**None that block Gate B design start** after this approval.

Engineering notes (not product blockers):

- Exact CHECK SQL for completed/`actual_departed_at` coupling must be validated on MariaDB 10.4 in Gate B.  
- Confirm whether existing confirmed rows (`operational_status` null) need a one-time backfill to `assigned` — **not required** if eligibility treats null as assigned.  
- `accommodation_events` creation is net-new (Sprint 2 did not ship the table).

---

## 20. Gate A completion

| Item | Status |
|---|---|
| Product-owner B1–B5 recorded | Yes (§1) |
| Scope / exclusions / capacity-release package | Yes (§2, §6) |
| State matrix, booking vs ops, departure clocks | Yes (§3–§5) |
| Relocation / schema / API / permissions / locks / audit / concurrency / FE / gates | Yes (§7–§15) |
| Invariants | Yes (§16) |
| Sprint 2 verification debt listed | Yes (§17) |
| Module 18 docs aligned | Yes (companion edits in this pass) |
| Readiness supersession pointer | Yes (`ACCOMMODATION_SPRINT3_READINESS.md`) |
| ADR status unchanged | Yes |
| Implementation started | **No** |

**Gate A: COMPLETE** (documentation).

**Final verdict for next step:** **READY FOR GATE B** — when engineering explicitly starts implementation. Do not begin Gate B from this documentation pass automatically.

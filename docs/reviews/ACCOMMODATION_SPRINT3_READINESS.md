# Accommodation & Camps — Sprint 3 Readiness (Operations)

> **Type:** Documentation and readiness review only. No application code, migration, seed, frontend, or ADR status change was made.  
> **Date:** 2026-10-19  
> **Prerequisites:** Sprint 1 **ACCEPTED**; Sprint 2 **ACCEPTED** (`ACCOMMODATION_SPRINT2_ACCEPTANCE.md`).  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Baseline:** ADR-0019 provisional decisions 4, 5, 6, 7, 11, 13; architecture draft; reconciliation §§5–8; Module 18 lifecycle vocabulary. Do not invent new product rules.

## Supersession (do not erase original assessment)

> **Historical verdict of this readiness review:** **BLOCKED** for implementation kickoff (see below). That assessment is preserved.  
> **Superseding authority:** product-owner approval of B1–B5 and the Sprint 3 operations slice are recorded in [`ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md`](ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md) (Gate A). Implementation still must not start from *this* readiness file alone — start Gate B only under that scoped approval when engineering explicitly kicks off.  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**.

## Verdict (original readiness assessment — preserved)

### **BLOCKED** for implementation kickoff

Sprint 3 operations can be **planned** from already documented provisional rules. Implementation must not start until:

1. A **Sprint 2–style scoped product approval** names the exact Sprint 3 verb set and capacity-release paths (staff cancel vs listener vs checkout-only), without re-deciding rules that are already written in ADR §2.  
2. The **genuine unresolved edge decisions** in §8 are answered (checkout clock edges + same-pilgrim relocation into own interval). Fail-closed engineering guesses are **not** authorization to ship.

ADR-0019 remains **PROPOSED / NOT ACCEPTED**. This file does not accept it.

**(Resolved after this review):** B1–B5 were product-approved; see scoped approval. The original BLOCKED text above is kept for audit trail.

| Label | Meaning |
|---|---|
| **Documented provisional rule** | ADR §2 / architecture / reconciliation — product intent; ADR still not Accepted |
| **Sprint 2 fact** | Implemented and accepted for confirmation/capacity only |
| **Engineering proposal** | Safe candidate for design; not authority to build |
| **Unresolved blocker** | Must be decided before the affected ops API ships |
| **Verification debt** | Sprint 2 carryover; does not by itself block Sprint 3 design |

---

## 1. Existing approved / documented operational rules (baseline)

Do **not** re-approve these. Cite them as already recorded.

| Source | Rule |
|---|---|
| ADR decision 4 / Module 18 | Facility **arrival is optional** before check-in. |
| ADR decision 4 | Assignment may be **cancelled before check-in**, including after arrival. |
| ADR decision 4 | **Checkout requires prior check-in.** No checkout from assigned/arrived alone. |
| ADR decision 4 | Relocation + check-in may be **one user action** and must produce **two timeline events**. |
| ADR decision 5 | SeasonPilgrim cancel: **before check-in** → cancel confirmed assignment; **after check-in** → administrative checkout + immediate capacity release; preserve history; record administrative source. Not physical departure. |
| ADR decision 5 / Module 18 | Transport arrival must **not** auto check-in Accommodation. |
| ADR decision 6 | **Active only** for confirmation, arrival, check-in, checkout, relocation, extension, assignment cancellation. Draft/Closed forbidden for those writes. |
| ADR decision 7 | Separate verbs: arrive / check_in / check_out / relocate / cancel_assignment (and later correct/extend). |
| ADR decision 11 | Early checkout moves **`effective_ends_at`**, preserves **`original_ends_at`**, frees future occupancy. Relocation is atomic. |
| ADR decision 13 | Facility or supervisor scope; relocate needs **source and destination** scope (or supervisor). Revocation re-checked inside the write transaction. |
| ADR §5 / reconciliation §8.2 | Booking vs operational dimensions stay distinct (see §3). |
| Sprint 2 D1–D7 | Confirm eligibility, peak/position/family/cross-season/D5, error codes — remain binding for destination reservation during relocate. |
| Sprint 2 fact | Confirmed stays currently ship with `operational_status = null` and `effective_ends_at = original_ends_at` under `acc_asg_booking_ck`. |

---

## 2. Proposed narrow Sprint 3 scope

### In scope (proposed — pending scoped approval)

1. **Arrival recording** (`assigned`/`null` → `arrived`)  
2. **Check-in** (`assigned`/`arrived`/`null-confirmed` → `checked_in`)  
3. **Check-out** (early or on-plan; capacity release via `effective_ends_at`)  
4. **Relocation** (atomic source release + destination reserve; optional combined check-in → two events)  
5. **Capacity release & historical integrity** for ops paths: early checkout; pre-check-in **staff** `cancel_assignment`; append-only timeline; no history erase  
6. Schema widen for operational booking states + `accommodation_events` (ops event types)  
7. Permissions seed for ops verbs (**zero** role-template grants unless separately approved)  
8. MariaDB concurrency tests for ops lock races  
9. Frontend ops actions on the assignments surface (Active only)

### Explicit exclusions (this Sprint 3 proposal)

| Excluded | Why |
|---|---|
| Extension | Separate ADR later slice |
| Historical correction / Closed-season writes | Named but mechanism not approved (reconciliation §3 / §10) |
| `SeasonPilgrimCancelled` **listener implementation** | Documented in ADR; this readiness reviews it (§6) but **does not authorize coding listeners** in the first ops gates — see §8 |
| Family compatibility reviews table / gating | Decision 12 review path; gating **UNRESOLVED**; keep out of narrow Sprint 3 |
| `physical_capacity` / cross-season sharing formula | Still not approved |
| QR, offline, new notification channels, demand/queue | Module 18 later |
| Role-template grants | Separate approval |
| Accepting ADR-0019 | Separate change |
| Changing Foundation or Transportation behavior | Hard boundary |
| Inventing transport-triggered check-in | Forbidden |

### Capacity-release dependency (Sprint 2 carryover)

Sprint 2 explicitly deferred staff cancel and participation-cancel release. Confirmed stays still consume capacity until:

- checkout (after check-in), or  
- pre-check-in assignment cancel (staff), or  
- participation-cancel listener (after-commit; planned, not in first coding gates here).

A scoped Sprint 3 approval must state whether **staff cancel** is in the first ops delivery. Without it, only check-out releases capacity for checked-in stays, and pre-check-in confirmed inventory remains stuck (same debt as Sprint 2 acceptance §7).

---

## 3. Operational state machine

### 3.1 Dimensions (do not collapse)

| Dimension | Values (documented engineering strings) | Role |
|---|---|---|
| **Booking** `booking_status` | `draft` → `confirmed` → `cancelled` (cancel **only before check-in**); `confirmed` → `completed` (after checkout) | Capacity / overlap eligibility |
| **Operational** `operational_status` | `assigned` → `arrived` → `checked_in` → `checked_out`, or `assigned` → `checked_in` → `checked_out` | Facility presence / check-in lifecycle |

Sprint 2 confirmed rows today may have `operational_status = null`. **Engineering proposal (not a new product rule):** treat `booking_status=confirmed` and `operational_status IS NULL` as **assigned** for eligibility; first ops transition materializes `assigned` or jumps to `arrived` / `checked_in` per the transition table. Do not invent a third booking dimension.

### 3.2 Transition matrix

| From (booking / operational) | Action | To | Active season? | Capacity effect |
|---|---|---|---|---|
| confirmed / null\|assigned | arrive | confirmed / arrived | Yes (`assertActive`) | None (still reserved) |
| confirmed / null\|assigned\|arrived | check_in | confirmed / checked_in | Yes | None (still reserved through `effective_ends_at`) |
| confirmed / checked_in | check_out | completed / checked_out | Yes | Release from checkout instant via `effective_ends_at` |
| confirmed / null\|assigned\|arrived | cancel_assignment | cancelled / (unchanged or null) | Yes | Release entire remaining confirmed interval |
| confirmed / checked_in | cancel_assignment | **Reject** (use checkout path) | — | — |
| confirmed / * | relocate | source shortened + new confirmed assignment | Yes | Atomic: free old effective interval; reserve destination |
| confirmed / * + relocate&check_in | relocate+check_in | new assignment checked_in; two events | Yes | Same + check-in on destination |
| draft / * | any ops verb | **Reject** | — | — |
| completed\|cancelled / * | arrive/check_in/relocate | **Reject** (idempotent no-op only where documented for listener) | — | — |

**Arrival optional:** check-in does **not** require prior arrive (decision 4).

**Illegal:** checkout from `assigned` or `arrived` without check-in; cancel after check-in; Draft/Closed ops writes.

### 3.3 Repeated requests

| Action | Documented intent |
|---|---|
| Arrive when already arrived | Idempotent success or stable 422 — **engineering proposal:** idempotent no-op, no duplicate event if same idempotency key |
| Check-in when already checked_in | Idempotent no-op / reject duplicate event |
| Check-out when already checked_out | Idempotent no-op |
| Cancel when already cancelled | Idempotent no-op |

Idempotency key format for staff vs listener remains an engineering gap (reconciliation §11.9) — propose `(tenant_id, assignment_id, event_type, source, occurred_at_bucket)` only after scoped approval of event table rules.

---

## 4. Check-in and check-out (review)

| Topic | Baseline |
|---|---|
| Eligibility | Confirmed booking; Active season; facility or supervisor scope for the unit’s facility; SeasonPilgrim not required to change Foundation status |
| Authorization | `accommodation.check_in` / `accommodation.check_out`; scope re-check after locks |
| Check-in prerequisites | Confirmed; not cancelled/completed; not already checked_out; arrival **not** required |
| Checkout prerequisites | `operational_status = checked_in`; checkout time rules → **§8 blocker** for edges |
| Early checkout | Set `effective_ends_at` to checkout occurrence (UTC); keep `original_ends_at`; `checkout_source=staff`; `booking_status=completed`; `operational_status=checked_out` |
| Capacity release | Occupancy filters: confirmed (and completed only up to `effective_ends_at`); cancelled excluded — same half-open predicate as Sprint 2 |
| Original interval | Never rewrite `original_ends_at` on early checkout |
| Audit / history | `Core/Audit` for security-relevant mutation **and** append-only `accommodation_events` (`checked_in` / `checked_out`) with `occurred_at` / `recorded_at` |
| Transport | Never set check-in from Transportation |

---

## 5. Relocation (review)

| Topic | Baseline |
|---|---|
| Atomicity | One transaction: shorten source `effective_ends_at`, create destination confirmed assignment (or equivalent row), validate destination peak/position/pilgrim/family/cross-season using Sprint 2 guards |
| Destination checks | Reuse `AccommodationCapacityGuard` + lock order; no temporary hold |
| Permission | `accommodation.relocate`; authorize **source and destination** facilities, or season supervisor scope |
| Check-in after relocate | Combined action allowed: still **two events** (`relocated`, `checked_in`) |
| History | Source assignment retained; effective interval closed; no delete of prior row/events |
| Lock order | Stable units (source+destination) sorted by `id` → pilgrim lock anchors → season units → season positions → assignments (extends Gate B/C order) |
| Concurrency risks | Dual-unit deadlock if lock order not sorted; pilgrim overlap on destination; D4 cross-season on destination unit; family exclusivity on Family destination |

---

## 6. Foundation integration (review only — no listener implementation)

| Case | Documented behavior (ADR decision 5 / reconciliation §8.3) |
|---|---|
| Cancel participation **before** check-in | Cancel confirmed assignment (`booking_status=cancelled`); keep row; timeline `cancelled` with `source=automatic_cancellation` |
| Cancel participation **after** check-in | Administrative checkout: `checked_out` + `completed` + `checkout_source=automatic_cancellation`; set `effective_ends_at` from cancel time when inside interval; release future capacity; **not** physical departure |
| Family-group atomicity | Family bulk was one assignment per pilgrim at confirm (Sprint 2). Listener applies **per assignment**; no invented family-wide sync beyond documented cancel rules |
| Capacity release | Via booking cancel or admin checkout effective end — same occupancy clock as Sprint 2 |
| Event consistency | After-commit gap: listener cannot share Foundation transaction; needs durable task + closing-readiness query (engineering proposal `accommodation_cancellation_tasks`) |
| Drafts of cancelled participation | **UNRESOLVED** whether cancel/leave draft (§8) |
| Family membership drift | No Foundation event; review path out of narrow Sprint 3 |

**This readiness does not authorize implementing the listener.** Plan it as a dedicated gate after staff ops + staff cancel paths exist, or as an explicit scoped inclusion if product so decides.

---

## 7. Sprint 2 carryover

| Item | Class | Notes |
|---|---|---|
| Browser E2E not executed | **Verification debt** | Does not block ops design; manual QA still required before operator cutover |
| Production frontend build not run | **Verification debt** | Disk; re-run when space allows |
| Confirmed cancellation/release not implemented | **Functional debt / Sprint 3 driver** | Documented Sprint 2 exclusion; capacity stuck until ops/cancel/listener |
| MySQL 8 CI unproven on this machine | **Verification debt** | MariaDB 10.4.27 proven for Sprint 2 races |
| ADR-0019 remains PROPOSED | **Process fact** | Ops still need scoped approval to code; not ADR acceptance |

---

## 8. True blockers requiring product approval

> **Update:** B1–B5 are **approved** in [`ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md`](ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md). The table below is the original blocker list from this readiness pass (preserved).

Only **genuine unresolved** items that affect Sprint 3 ops. Already-documented ADR rules are **not** listed for reapproval.

| # | Decision needed | Why it blocks |
|---|---|---|
| **B1** | Checkout `occurred_at` **before** `starts_at` | Reject vs privileged correction vs clamp — reconciliation §11.2 / §8.2 |
| **B2** | Checkout `occurred_at` **after** `original_ends_at` | Same |
| **B3** | Relocation onto an interval the **same pilgrim already occupies** (source shortening + destination) | Atomic validation edge — reconciliation §11.2 |
| **B4** | Sprint 3 capacity-release package: include **staff** `cancel_assignment` in first delivery? Include **listener** in same sprint or defer? | Sprint 2 debt; user asked for capacity release; listener coding excluded from this doc’s first gates unless approved |
| **B5** | If listener in scope: fate of **draft** rows when SeasonPilgrim cancels | Reconciliation §11.8 |

**Not blockers for Sprint 3 kickoff design** (already decided in Sprint 2 or out of scope): confirm status matrix (D1); Family null group at confirm (D2); position ceiling (D3); cross-season formula (still out); family-review gating (exclude reviews from Sprint 3).

---

## 9. Data-model impact (engineering proposal)

| Change | Purpose |
|---|---|
| Widen `acc_asg_booking_ck` | Allow `cancelled` / `completed` branches; allow non-null `operational_status` / `checkout_source` for ops states; keep draft/confirmed Sprint 2 predicates |
| `accommodation_events` | Append-only: `arrived`, `checked_in`, `checked_out`, `relocated`, `cancelled` (+ confirm if still missing from Sprint 2) with `occurred_at`, `recorded_at`, `source`, `idempotency_key` |
| Optional later: `accommodation_cancellation_tasks` | Only if listener gate approved |
| No Pilgrim/SeasonPilgrim current-room columns | Unchanged hard forbid |
| Occupancy definition | Confirmed (and completed only until `effective_ends_at`); exclude draft/cancelled |

`accommodation_events` was named in Sprint 2 docs but **not** delivered in Gates B–C; treat as Sprint 3 schema work.

---

## 10. API and permission plan (engineering proposal)

| Method | Path (proposal) | Verb | Season |
|---|---|---|---|
| `POST` | `/accommodation/assignments/{id}/arrive` | `accommodation.arrive` | Active |
| `POST` | `/accommodation/assignments/{id}/check-in` | `accommodation.check_in` | Active |
| `POST` | `/accommodation/assignments/{id}/check-out` | `accommodation.check_out` | Active |
| `POST` | `/accommodation/assignments/{id}/relocate` | `accommodation.relocate` | Active |
| `POST` | `/accommodation/assignments/{id}/cancel` | `accommodation.cancel_assignment` | Active; pre-check-in only |

Stable error codes: reuse Sprint 2 D7 where applicable; add transition codes (names **TBD at Gate A** — e.g. illegal transition) without inventing product outcomes.

Seed ops verbs with **zero** role-template grants (same pattern as Sprint 1–2).

---

## 11. Transaction and lock strategy

Extend Gate B/C order:

1. Stable `accommodation_units` for all touched units (`id` ASC)  
2. `accommodation_pilgrim_locks` for affected pilgrims  
3. Season units  
4. Season positions (if any)  
5. Assignments  
6. Facility/supervisor scope rows (`lockForUpdate`)  
7. Locking reads for occupancy (`SELECT … FOR UPDATE`) — mandatory after Sprint 2 Gate E RR fix  
8. Append events + audit only on commit  

Relocation: lock **min(source_unit_id, dest_unit_id)** first. Combined relocate+check-in: one transaction, two event rows.

Deadlock retries: keep `AccommodationTransaction` attempts = 3.

---

## 12. Concurrency test matrix (MariaDB / `erp_hajj_testing` only)

| Race | Expect |
|---|---|
| Two check-ins same assignment | One wins / idempotent |
| Check-out vs relocate on checked-in stay | One wins; no double release |
| Relocate A→B vs confirm other pilgrim into B | Capacity/position integrity |
| Relocate vs pilgrim confirm on third unit | Pilgrim overlap |
| Early checkout vs capacity decrease (D5) | No invalid under-cap state |
| Staff cancel vs check-in | One wins; never cancel after check-in committed |
| Dual relocate crossing units (A↔B) | Deterministic lock order; no deadlock loop beyond retries |

Reuse independent-process workers; never target `erp_hajj`.

---

## 13. Frontend plan (after backend gates)

| Surface | Work |
|---|---|
| Assignments list/detail | Arrive / Check-in / Check-out / Relocate / Cancel (pre-check-in) behind verbs + Active season |
| Badges | Booking + operational labels (Confirmed/Arrived/Checked-in/Checked-out/Cancelled/Completed) |
| Relocate drawer | Destination season unit/position; explain atomicity; optional check-in flag → two server events |
| Errors | Map capacity/overlap/family + illegal-transition codes |
| Explicit absence | No transport check-in; no extension; no correction UI |

Vitest + `vue-tsc`; browser E2E remains verification debt unless separately staffed.

---

## 14. Recommended implementation gates

| Gate | Content | Exit |
|---|---|---|
| **A** | Scoped PO approval: Sprint 3 verb set; B1–B5 answers; listener in/out | Written approval file (like Sprint 2 scoped approval); Module 18 ops sections aligned; ADR still PROPOSED |
| **B** | Schema: CHECK widen + `accommodation_events` (+ tasks only if approved) on `erp_hajj_testing` | Migrations + schema tests; `erp_hajj` untouched |
| **C** | Backend: arrive, check-in, check-out, relocate; staff cancel if approved; capacity release; audit/events | Pest MariaDB green; concurrency matrix |
| **D** | Frontend ops UX | Vitest + type-check |
| **E** | Integrated acceptance | Matrix with evidence types; no false browser PASS |
| **F (optional)** | `SeasonPilgrimCancelled` listener + tasks + closing-readiness contributor | Only if Gate A includes it |

**Do not start Gate B coding from this readiness file alone.** Require Gate A scoped approval first (same discipline as Sprint 2).

---

## 15. Final verdict

**BLOCKED**

Reasons (implementation, not documentation):

1. Checkout clock edges and same-pilgrim relocation edge remain **UNRESOLVED** product decisions (B1–B3).  
2. Capacity-release package (staff cancel vs deferred listener) must be **explicitly scoped** (B4–B5) before coding release paths.  
3. ADR-0019 is still **PROPOSED**; Sprint 3 needs a scoped approval ceremony analogous to Sprint 2 D1–D7, not silent ADR acceptance.

Sprint 2 verification debt (browser E2E, production build, MySQL 8 CI) is **not** a Sprint 3 design blocker.

**Do not start implementation. Do not change ADR-0019 status. Do not implement listeners from this document.**

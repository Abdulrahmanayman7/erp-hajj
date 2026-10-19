# Accommodation & Camps — Pre-Implementation Audit

> **Type:** Audit and readiness report only. No application code, migrations, seeds, or other documentation were changed to produce this file.
> **Date:** 2026-10-15
> **Repository:** `erp-hajj` (`d:\erp-hajj-1`)
> **Branch / HEAD:** `develop` @ `736c776dd1d8ca6ddcb53239ab8cd2e87a9943fa`
> **Subject:** Module 18 — Accommodation and Camps (الإسكان والمخيمات)
> **Verdict:** Specification exists. **Implementation must not start** until blocking TBDs (physical schema, permission verbs, transition graph, overlap enforcement, calendar) are approved.

Classification used below: **IMPLEMENTED** (code or schema exists), **DOCUMENTED** (approved text, not built), **PROPOSED** (not in the repository; would need product approval), **UNKNOWN** / **NOT VERIFIED**.

---

## 1. Executive Summary

Accommodation and Camps is an approved Phase 2 bounded context and the intended source of truth for time-based occupancy (الإشغال الزمني). It is specified in `docs/09-modules/18-accommodation-and-camps/` and [ADR-0017](../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md). There is **no** `backend/app/Modules/Accommodation/`, **no** accommodation migration, **no** `accommodation.*` permission seed, and **no** frontend module.

The Season & Pilgrims foundation and Transportation & Fleet backends are implemented and expose contracts Accommodation can register against without editing those modules: season context, closing readiness, operational-reference checks, resource-deletion checks, and the after-commit `SeasonPilgrimCancelled` event.

What is **not** specified at field level, and is explicitly marked TBD, includes: place taxonomy (hotel / building / floor / room / camp / tent), permission verbs, API paths, status enums as stored values, Hijri vs Gregorian intervals, overlap enforcement mechanism, capacity counting in position-based mode, whether “arrived at facility” is mandatory before check-in, notification recipients, and which SeasonPilgrim statuses may be assigned.

The roadmap and the module acceptance criteria both treat those TBDs as a **blocker** for the first implementation sprint. Offline field sync and QR are later slices and are out of the first sprint even after the schema is approved.

---

## 2. Repository & Git State

| Claim | Evidence | Class |
|---|---|---|
| Branch is `develop` | `git rev-parse --abbrev-ref HEAD` → `develop` | IMPLEMENTED |
| HEAD is `736c776` | `736c776dd1d8ca6ddcb53239ab8cd2e87a9943fa` — `feat(phase2): add season foundation and transportation backend` (2026-10-15) | IMPLEMENTED |
| Working tree | Untracked directory `تقرير النظام/` only. No staged or modified tracked files at audit time. This report is the only file this audit adds. | IMPLEMENTED |
| Accommodation PHP module | Glob `backend/app/Modules/Accommodation/**` → 0 files | IMPLEMENTED (absence) |
| Accommodation frontend | Grep `frontend/src` for `accommodation` → no matches | IMPLEMENTED (absence) |
| Accommodation tables | `SeasonPilgrimsSchemaTest` and `TransportSchemaTest` assert no accommodation/hotel/room/bed/tent/camp tables | IMPLEMENTED (absence, enforced by tests) |

This audit did **not** run migrations, Pest, or MySQL. Runtime behavior of existing tests was not re-executed here.

---

## 3. Current System Architecture

### 3.1 Shape

| Layer | Pattern | Evidence | Class |
|---|---|---|---|
| Monorepo | `backend/` Laravel 12, `frontend/` Vue 3 | ADR-0001; `backend/bootstrap/providers.php` | DOCUMENTED + IMPLEMENTED |
| Modular monolith | One process, one MySQL database, modules under `app/Modules/` | ADR-0002 | DOCUMENTED + IMPLEMENTED |
| Tenancy | Single DB, shared schema, `tenant_id`, `TenantOwned` + `UsesTenantScope`, fail-closed context | ADR-0003; `Season` model lines 7–8, 27–32 | IMPLEMENTED |
| API | REST `/api/v1`, Sanctum, Form Requests, Resources, Policies | Season API header, lines 1–5 | IMPLEMENTED for foundation and transportation |
| Frontend | Vue 3, TypeScript, Vite, Pinia, Vue Router, TanStack Query, Tailwind, Arabic RTL | Existing `frontend/src/modules/*` (auth, inventory, …). No season, transportation, or accommodation UI module. | IMPLEMENTED (MVP UI); Phase 2 UI **not built** |
| Module docs | Eight files per module (`README`, `BUSINESS_RULES`, `DATA_MODEL`, `API`, `PERMISSIONS`, `UI`, `TEST_PLAN`, `ACCEPTANCE_CRITERIA`) | `docs/02-architecture/MODULE_TEMPLATE.md` lines 4–6 | DOCUMENTED |

`backend/bootstrap/providers.php` lines 4–12 register `SeasonPilgrimsServiceProvider` and `TransportationServiceProvider`. There is no Accommodation provider.

### 3.2 Conventions Accommodation must follow (do not invent a parallel stack)

- Tenant id from `TenantContext` only. Never from the payload. Cross-tenant id → **404**.
- Season-scoped writes use `season.context` (`X-Season-Id`), not a client `season_id`. See Season API lines 8–16.
- Composite foreign keys `(tenant_id, id)` and `(season_id, id)` so a child cannot point at another tenant or season. Pattern: `2026_10_05_100006_create_season_pilgrims_table.php` lines 10–16 and 56–75.
- Policies are authoritative; `can()` on the UI is visibility only.
- Critical mutations audit through `Core/Audit` (ADR-0015). Operational timelines are a **second** history, not a replacement. Transportation already does this (ADR-0018 T6).
- Pest feature tests per endpoint: 200/201, 422, 401, 403, 404 cross-tenant. At least one explicit cross-tenant test per tenant-owned module.

### 3.3 Documentation drift (do not treat stale lines as current code state)

| Document | Statement | Conflict | Class |
|---|---|---|---|
| `docs/00-project/MVP_KNOWN_LIMITATIONS.md` lines 35–36 | “Transportation and accommodation implementation … not built” | Transportation backend exists (`TransportationServiceProvider`, 15 `transport_*` migrations). Accommodation is still not built. | DOCUMENTED (stale for transportation) |
| `docs/00-project/OUT_OF_SCOPE.md` line 10 | Phase 2 is “documentation only until an implementation sprint” | Season and Transportation backends are implemented. Accommodation remains documentation-only. | DOCUMENTED (partially stale) |
| `docs/00-project/ROADMAP.md` lines 52–64 | Header says Phase 2 “not implemented”; table says Season and Transportation backends are implemented; line 64 says Accommodation is blocked on TBD schemas and verbs | Accurate for Accommodation. Header is stale for Season and Transportation. | DOCUMENTED |

---

## 4. Season & Pilgrims Integration Contracts

**Status: IMPLEMENTED (backend). Frontend: not built.** Module: `backend/app/Modules/SeasonPilgrims/`. Docs: `docs/09-modules/16-season-and-pilgrims/`.

### 4.1 Tables (IMPLEMENTED)

From `docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md` lines 12–24, migrations `2026_10_05_100000`–`100010`:

| Table | Role |
|---|---|
| `seasons` | Draft / Active / Closed; `code`, `hajj_year`, operational dates |
| `pilgrims` | Tenant-level identity: `full_name`, nullable `gender` (`male` \| `female`), nationality, phone |
| `pilgrim_identity_documents` | Identity documents |
| `season_programs` | Season packages |
| `season_pilgrim_groups` | Operational groups |
| `season_family_groups` | Family groups (مجموعة عائلية) |
| `season_pilgrims` | One participation per pilgrim per season; current program, pilgrim group, family group |
| `season_pilgrim_identity_snapshots` | Historical identity snapshot |
| `season_pilgrim_program_changes` | Append-only program history |
| `season_pilgrim_group_supervisors` | Employee supervisor history |
| `season_pilgrim_reference_sequences` | Reference-number counter |

No accommodation columns. Schema test `backend/tests/Feature/SeasonPilgrims/SeasonPilgrimsSchemaTest.php` lines 84–96 forbids `current_room*`, `current_bed*`, `current_accommodation*` and accommodation-like table names.

### 4.2 Lifecycle (IMPLEMENTED)

| Object | States | Evidence | Class |
|---|---|---|---|
| Season | `draft` → `active` → `closed`; reopen exists as an endpoint | `SeasonStatus.php` lines 5–14; Season API line 26 | IMPLEMENTED |
| Season writes | Closed season: reads allowed; writes `422 SEASON_CLOSED`, except audited snapshot correction | Season API lines 15–16; `SeasonGuard.php` lines 12–21 | IMPLEMENTED |
| SeasonPilgrim | `registered` → `active` → `completed`; side path `cancelled` | `SeasonPilgrimStatus.php` lines 5–15 | IMPLEMENTED |
| Terminal | `completed` and `cancelled` are terminal | `SeasonPilgrimStatus::isTerminal()` lines 12–15; `SeasonGuard::assertNotTerminal` lines 33–37 | IMPLEMENTED |
| Active season uniqueness | One Active season per tenant | DATA_MODEL.md lines 31–32 | IMPLEMENTED |
| Participation uniqueness | `UNIQUE(tenant_id, season_id, pilgrim_id)` | migration lines 80 | IMPLEMENTED |

`SeasonPilgrim.status` must not store visa, finance, accommodation, or transport state (ADR-0017 lines 49, 142). Accommodation may **read** it and must not write it (business rules lines 163–167; API hard rules lines 24–27).

### 4.3 What Accommodation may reference (read / FK only)

| Target | Rule | Evidence | Class |
|---|---|---|---|
| `Season` | Season-scoped stays | DATA_MODEL.md lines 10, 31–33 | DOCUMENTED |
| `SeasonPilgrim` | Seasonal operations key, not permanent `pilgrims.id` alone | ADR-0017 lines 39–40 | DOCUMENTED |
| `SeasonFamilyGroup` | Reference only; do not create or dissolve groups | BUSINESS_RULES.md lines 56–58, 165 | DOCUMENTED |
| `Pilgrim.gender` | `male` / `female`, **nullable** | `PilgrimGender.php` lines 5–8; migration `pilgrims.gender` nullable line 21 | IMPLEMENTED on foundation; **mapping to Male/Female/Family audience is not specified** |
| Program / pilgrim group | Not named as assignment keys. Family-aware allocation is a concept only. | BUSINESS_RULES.md lines 64–78 | DOCUMENTED (concept) / UNKNOWN (whether a program entitlement exists) |

### 4.4 Extension points Accommodation can register without editing the foundation

All four are IMPLEMENTED. Transportation already registers three of them. Accommodation registers **none** today.

| Tag / event | Interface | File | What Accommodation should do later | Class |
|---|---|---|---|---|
| `season_pilgrims.closing_readiness` | `SeasonClosingReadinessContributor::evaluate(Season): list<ReadinessResult>` | `Contracts/SeasonClosingReadinessContributor.php` lines 8–19 | Report BLOCKING vs WARNING occupancy (rules **TBD**) | IMPLEMENTED contract; accommodation contributor **not implemented** |
| `season_pilgrims.operational_reference_checkers` | `isReferenced(SeasonPilgrim): bool` | `Contracts/SeasonPilgrimOperationalReferenceChecker.php` lines 7–15 | Return true once an assignment or timeline references the participation, so hard delete is forbidden (F7) | IMPLEMENTED contract |
| `season_pilgrims.deletion_reference_checkers` | `blocksDeletion(Season\|Program\|PilgrimGroup\|FamilyGroup): bool` | `Contracts/SeasonResourceDeletionChecker.php` lines 10–18 | Block hard delete of a season (and any foundation row historically referenced). Deactivation is never blocked. | IMPLEMENTED contract |
| `season_pilgrims.activation_requirements` | `check(SeasonPilgrim): ?string` | `Contracts/SeasonPilgrimActivationRequirement.php` lines 7–18 | Only if product later says “must have accommodation” before activation. **Not documented as a requirement.** | IMPLEMENTED contract; use is PROPOSED |
| `SeasonPilgrimCancelled` | After-commit event, scalar ids only | `Events/SeasonPilgrimCancelled.php` lines 8–28 | Idempotent handler: do not erase history (F7). Exact effect on open assignments is **TBD** (transportation cancels or flags review; accommodation has no equivalent rule). | IMPLEMENTED event; accommodation listener **not implemented** |

Registration pattern to copy (do not import Accommodation from the foundation): `TransportationServiceProvider.php` lines 44–53.

Season API lines 61–71 lists the same contracts and states: “No endpoint writes transportation movements or accommodation assignments.” Registered contributor today: Transportation only (line 71).

### 4.5 Locked behavior Accommodation must not change

- Do not add `current_room_id`, `current_bed_id`, or `current_accommodation_id` on `pilgrims` or `season_pilgrims` (ADR-0017 lines 72, 80, 139; DATA_MODEL.md lines 38–46).
- Do not write `SeasonPilgrim.status`.
- Do not create or dissolve Family Groups.
- Cancellation must not delete accommodation history (BUSINESS_RULES.md lines 167).
- Composite FKs and tenant scope stay as they are. A new accommodation table that references `season_pilgrims` should follow the same `(tenant_id, season_id, id)` target (`season_pilgrims_tenant_season_id_unique`, migration lines 79).

---

## 5. Transportation Integration Contracts

**Status: IMPLEMENTED (backend sprint 1, ADR-0018).** Frontend not built. Final MySQL concurrency re-run was **not** executed in this audit (**NOT VERIFIED** here). The module TEST_PLAN documents a MySQL-only concurrency suite (`TransportConcurrencyTest.php`); this audit did not run it.

### 5.1 What Accommodation must not do

| Rule | Evidence | Class |
|---|---|---|
| Do not mutate transportation rows | ADR-0017 line 96; BUSINESS_RULES.md line 166; README line 36 | DOCUMENTED |
| Transport arrival does not check the pilgrim into accommodation | ADR-0017 line 85; BUSINESS_RULES.md lines 102; API.md lines 28; TEST_PLAN.md line 20 | DOCUMENTED |
| No accommodation columns on transport tables | `TransportSchemaTest.php` lines 37–47 | IMPLEMENTED |

### 5.2 The only approved future link

ADR-0018 T2 / I14 (lines 21–23 and 94): a Transport Point may **later** be linked read-only to an Accommodation Facility. Transportation does not own that facility. **No link column or table exists.** `transport_points` (`2026_10_05_200000_create_transport_points_table.php` lines 8–9, 17–30) has `code`, `name`, `point_type`, `is_active`, `notes` — no facility id.

Adding that link is an additive change **in Transportation** when Accommodation tables exist. It is not part of the first Accommodation sprint unless a change request says so. Doing it now would modify a module this audit treats as implemented and stable.

### 5.3 Patterns worth copying (technique, not an accommodation requirement)

| Pattern | Where | Why it matters | Class |
|---|---|---|---|
| Append-only operational timeline with `occurred_at` / `recorded_at`; corrections append, they do not rewrite | ADR-0018 T6 lines 45–49 | Accommodation timeline has the same rule, but correction policy is still TBD (BUSINESS_RULES.md lines 119–128) | DOCUMENTED (transport) / TBD (accommodation corrections) |
| Time-range overlap cannot be a plain UNIQUE on MySQL; use transactions, row locks, locking reads | ADR-0018 T7 line 53; H4 lines 103 | Accommodation DATA_MODEL.md line 51 leaves exclusion constraint vs application lock as **TBD** | DOCUMENTED technique |
| `lockForUpdate` + deadlock retry (`TransportTransaction`, 3 attempts) | ADR-0018 H4 | Reusable once overlap rules are approved | IMPLEMENTED in transportation |
| Group/family bulk add resolves to **individual** rows and snapshots source metadata without a live FK | ADR-0018 T5 lines 40–41 | Accommodation “family-aware allocation” does **not** say whether the stored assignment is individual. Do not assume the transport shape. | DOCUMENTED for transport only |
| Closing-readiness contributor | `TransportationClosingReadiness.php` lines 24–79 | Accommodation must add its own contributor; blocking conditions are not listed | IMPLEMENTED pattern |

Movement example “Hotel → Mina” (ADR-0018 line 18) is a transportation business example. It does not create an accommodation facility type.

---

## 6. Accommodation Requirements Inventory

Sources searched: module 18 docs, ADR-0017 §5, ADR-0018 T2/I14, glossary, roadmap, change request, season/transport docs and schema tests, Arabic terms (الإسكان، المخيمات، فندق، خيمة، غرفة، سرير). No other product specification of camps was found.

### 6.1 Confirmed documented requirements

| # | Requirement | Path | Lines | Class |
|---|---|---|---|---|
| R1 | Accommodation is an independent bounded context and the occupancy source of truth | `18-…/README.md` | 7–11 | DOCUMENTED |
| R2 | Other modules read a Current Accommodation Read Model; they do not write occupancy | README.md; BUSINESS_RULES.md | 11; 13–25 | DOCUMENTED |
| R3 | Time-based occupancy: non-overlapping use of a place; one SeasonPilgrim may have sequential stays | BUSINESS_RULES.md; ADR-0017 | 27–39; 79–80 | DOCUMENTED |
| R4 | Illustration only (not a fixture): Makkah hotel room 405 (1–4 Dhu al-Hijjah), Mina camp unit/tent 206 bed 28 (5–9), Madinah hotel (10–15) | BUSINESS_RULES.md | 31–37 | DOCUMENTED example, **not** a location enum |
| R5 | Two modes: capacity-based (no bed rows required) and position-based (explicit bed/place) | BUSINESS_RULES.md | 43–50 | DOCUMENTED |
| R6 | Audience: Male, Female, Family. Family Group is consumed, not owned | BUSINESS_RULES.md | 54–58 | DOCUMENTED |
| R7 | Planning concepts approved, columns not approved: stages/phases, demand, allocation, queue, draft vs confirmed, availability, capacity planning, auto allocation, incremental allocation, family-aware allocation, reserved family capacity, reuse of a previous allocation as preference, relocation, bulk relocation, extension, cancellation before check-in | BUSINESS_RULES.md | 62–80 | DOCUMENTED concepts; schema **TBD** |
| R8 | Intentional overbooking forbidden. New assignment must not exceed actual capacity. A capacity decrease may reveal over-capacity but must not allow another exceeding assignment | BUSINESS_RULES.md | 82–87 | DOCUMENTED |
| R9 | Operational states, distinct from `SeasonPilgrim.status`: Assigned, Arrived at facility, Checked-in, Checked-out | BUSINESS_RULES.md | 89–100 | DOCUMENTED |
| R10 | Transport arrival must not auto check-in | BUSINESS_RULES.md | 102 | DOCUMENTED |
| R11 | If check-in place differs from the confirmed assignment, one user action may relocate and check in, but history stores two events | BUSINESS_RULES.md | 104 | DOCUMENTED |
| R12 | `occurred_at` (field time) and `recorded_at` (entry time). Validation uses occurrence time “where that is the right clock” | BUSINESS_RULES.md | 108–117 | DOCUMENTED; which rules use which clock is **TBD** |
| R13 | Operational timeline ≠ `audit_logs`. Timeline is not silently rewritten. Not full event sourcing. Critical system changes still use `Core/Audit` | BUSINESS_RULES.md | 119–130 | DOCUMENTED |
| R14 | Read model must distinguish: assigned but not arrived; arrived not checked in; currently checked in; checked out with a future next assignment | BUSINESS_RULES.md | 15–21 | DOCUMENTED |
| R15 | Pending offline device state is not global truth until the server accepts it | BUSINESS_RULES.md | 23, 132–144 | DOCUMENTED later slice |
| R16 | QR direction: pilgrim, space, and position QR. Payload/signing/expiry **TBD** | BUSINESS_RULES.md | 146–152 | DOCUMENTED direction |
| R17 | Notification **configuration** concept only. Delivery is existing in-app channel (ADR-0013). No email/SMS/WhatsApp/push. Do not add `NotificationType` cases in the first sprint | BUSINESS_RULES.md | 154–160; README.md | 35–39 | DOCUMENTED |
| R18 | Read SeasonPilgrim status; do not write it. Read Family Group; do not create groups. Do not mutate Transportation. Keep history after cancellation | BUSINESS_RULES.md | 163–167 | DOCUMENTED |
| R19 | Cross-tenant access remains 404 | BUSINESS_RULES.md | 171 | DOCUMENTED |
| R20 | All business rows tenant-owned; seasonal stays season-scoped; `tenant_id` immutable, FK RESTRICT; client never sends `tenant_id` | DATA_MODEL.md | 10–12 | DOCUMENTED |
| R21 | Forbidden columns on Pilgrim / SeasonPilgrim: `current_room_id`, `current_bed_id`, `current_accommodation_id` | DATA_MODEL.md | 38–46 | DOCUMENTED + enforced by schema tests |
| R22 | Overlap of two assignments on the same place or position is rejected | DATA_MODEL.md | 51 | DOCUMENTED; mechanism **TBD** |
| R23 | Permission namespace `accommodation.*`. No `camps.*` unless a later decision. Verbs not seeded | PERMISSIONS.md | 6–23 | DOCUMENTED |
| R24 | Powers that verbs must eventually separate: view; maintain places; allocate/confirm; relocate; record arrival/check-in/check-out; cancel before check-in; resolve sync conflicts (later); configure notification rules | PERMISSIONS.md | 12–19 | DOCUMENTED powers; verb strings **TBD** |
| R25 | API groups (purposes, not URLs): places, assignments, availability, demand, current accommodation, timeline, notification configuration, later offline, later QR | API.md | 8–22 | DOCUMENTED |
| R26 | Hard handler rules: no pilgrim current-room writes; no status writes; no check-in from transport; combined relocate+check-in still two timeline events; reject over capacity and overlap; read model omits unaccepted offline items | API.md | 24–31 | DOCUMENTED |
| R27 | Contract category `hotel` stays a legal Contracts category, not room inventory | README.md | 44; contracts BUSINESS_RULES.md line 31 (`عقد إسكان / فندق`) | DOCUMENTED |
| R28 | First implementation slice excludes offline and QR | ACCEPTANCE_CRITERIA.md | 25; README.md | 28 | DOCUMENTED |
| R29 | Implementation is blocked while schemas and permission verbs are TBD | ACCEPTANCE_CRITERIA.md line 25; ROADMAP.md line 64; ADR-0017 lines 109 | DOCUMENTED blocker |
| R30 | Module placement when built: `backend/app/Modules/Accommodation/`, `frontend/src/modules/accommodation/` | README.md | 50–56 | DOCUMENTED |
| R31 | UI, when built, uses existing RTL shell, TanStack Query, and `can()` for visibility only. No sidebar/router change in the spec sprint | UI.md | 3–8 | DOCUMENTED |
| R32 | Glossary: Accommodation = الإسكان; source of truth for time-based occupancy; not a current-room column | `docs/00-project/GLOSSARY.md` | 51 | DOCUMENTED |

### 6.2 Explicit TBD / not specified (do not invent)

From BUSINESS_RULES.md, DATA_MODEL.md, API.md, PERMISSIONS.md, UI.md:

- Calendar: Hijri stored vs Gregorian instant + tenant timezone; whether the end instant is inclusive; gaps between stays (line 41).
- Whether one place can change mode after assignments exist; naming of hotel vs camp vs tent (line 52).
- Whether audience sits on the place, the demand, the assignment, or several of those (line 60).
- How capacity is counted in position-based mode (line 87).
- Whether arrival is required before check-in; stored enum names (line 106).
- Which validations use `occurred_at` vs `recorded_at` (line 117).
- Correction policy for timeline events (line 128).
- Offline payload and conflict workflow details (later slice).
- QR payload, signing, expiry, permissions (line 152).
- Which events notify whom; templates (line 160).
- Responsible employee on a place (DATA_MODEL.md line 35).
- Overlap enforcement mechanism (DATA_MODEL.md line 51).
- Whether the read model is a query or a projection (BUSINESS_RULES.md line 25).
- Exact API paths, bodies, and error codes (API.md lines 22–33).
- Role grants and personas (README.md line 48; PERMISSIONS.md line 25).
- UI copy, routes, bulk relocation entry (UI.md lines 14–25).
- Migration order details and rollback (DATA_MODEL.md lines 59–61) beyond “foundation tables first”.

### 6.3 Searched and not found as requirements

NOT VERIFIED as product rules (absent from module 18 and ADR-0017 §5):

- Building and floor entities.
- A required place hierarchy (hotel → floor → room → bed, or camp → tent → bed).
- Sacred-site enums for Makkah / Madinah / Mina / Arafat / Muzdalifah (they appear only inside the illustrative stay table and a transport movement example).
- Check-in/check-out as a full allowed-transition graph (who may skip arrival, whether check-out requires check-in, reopen after check-out).
- Accommodation entitlements analogous to transport program entitlements.
- Individual vs group as the stored assignment grain (sequential stays are described per SeasonPilgrim, which implies an individual interval, but family allocation storage is TBD).
- Double-booking of one SeasonPilgrim in two places at the same time (place overlap is forbidden; pilgrim-interval overlap is **not** explicitly forbidden).
- Season closing BLOCKING codes for open stays.
- Effect of `SeasonPilgrimCancelled` on a checked-in stay.
- Whether Draft vs Active vs Closed seasons allow planning vs execution (transportation decided this in ADR-0018 I2; accommodation has not).
- Gender required before Male/Female allocation (`pilgrims.gender` is nullable).
- Arafat and Muzdalifah operations.

---

## 7. Existing Accommodation Implementation

**None.**

| Surface | Result | Class |
|---|---|---|
| `backend/app/Modules/Accommodation/` | Does not exist | IMPLEMENTED (absence) |
| Migrations matching accommodation/hotel/room/bed/tent/camp | Forbidden and asserted empty by schema tests | IMPLEMENTED (absence) |
| Routes containing `accommodation` | Asserted absent by `TransportSchemaTest.php` lines 54 | IMPLEMENTED (absence) |
| `frontend/src/modules/accommodation/` | Does not exist | IMPLEMENTED (absence) |
| Permission catalog `accommodation.*` | PERMISSIONS.md line 21: do not edit `PermissionCatalog.php` until verbs are decided | DOCUMENTED |
| Demo data | `MvpDemoDatasetBuilder.php` creates an org unit and contract for “الإسكان” / housing staff. That is MVP org/contract demo data, not room inventory | IMPLEMENTED (unrelated) |
| Contracts `hotel` category | Legal category only | DOCUMENTED |

---

## 8. Proposed Domain Model

Every name below is a **concept**. None is a physical table. DATA_MODEL.md lines 14–16: “Do not treat concept names as physical tables.”

| Concept | Role | Label |
|---|---|---|
| Facility / place | A bookable space. Hotel, camp, room, tent, or unit — taxonomy not chosen | DOCUMENTED concept; physical shape **REQUIRES APPROVAL** |
| Position | Bed or place identifier under a position-based parent. Absent when the parent is capacity-only | DOCUMENTED |
| Assignment interval | SeasonPilgrim on a place or position for a time range. Draft vs confirmed is a named concept without columns | DOCUMENTED concept; columns **REQUIRES APPROVAL** |
| Operational state | Assigned / arrived at facility / checked-in / checked-out. Not on SeasonPilgrim | DOCUMENTED; stored enum **REQUIRES APPROVAL** |
| Operational timeline event | Append-only; `occurred_at`, `recorded_at`; relocation and check-in are distinct | DOCUMENTED |
| Demand, phase, allocation, queue | Planning | DOCUMENTED as concepts; **REQUIRES APPROVAL** before any table |
| Current Accommodation read model | Derived. Not writable by Transportation or the foundation | DOCUMENTED; query vs projection **REQUIRES APPROVAL** |
| Notification configuration | Which business events are selected. Not a channel | DOCUMENTED concept; schema **REQUIRES APPROVAL**; first sprint must not add `NotificationType` |
| Offline queue / conflict | Later slice | DOCUMENTED later; **do not build first** |
| QR | Later slice | DOCUMENTED later; **do not build first** |
| Building, floor | Not in the specification | **REQUIRES APPROVAL** — do not add |
| Site enum (Makkah, Madinah, Mina, Arafat, Muzdalifah) | Only an illustration | **REQUIRES APPROVAL** if stored as a type |
| Accommodation entitlement per program | Not specified (transport has this; accommodation does not) | **REQUIRES APPROVAL** |
| Transport Point → Facility link | Approved as a future additive transportation column | DOCUMENTED future; not this module’s first tables |

### Conceptual relationships (not a migration)

```text
Tenant
  └── Season                         (foundation, LOCKED)
        └── SeasonPilgrim            (foundation, LOCKED) ── optional SeasonFamilyGroup
              └── AssignmentInterval (accommodation, NOT BUILT)
                    ├── Place or Position
                    ├── operational state
                    └── TimelineEvents (append-only)

Place ──< Position     only when mode = position-based
Read model             derived from assignments + timeline; no pilgrim column
```

---

## 9. Proposed Database Relationships

**No migration is authorized by this audit.**

When a later approved schema exists, the foundation pattern to copy is:

- `tenant_id` NOT NULL, FK to `tenants` ON DELETE RESTRICT, never updated.
- Unique `(tenant_id, id)` on every referenced parent.
- Season-scoped child FKs `(tenant_id, season_id)` → `seasons (tenant_id, id)` and `(tenant_id, season_id, season_pilgrim_id)` → `season_pilgrims (tenant_id, season_id, id)`.
- RESTRICT on business parents so history cannot be cascade-deleted (F7).
- Short explicit FK names (MySQL 64-character limit), as the foundation hardening note states (ADR-0017 addendum item 7).

| Relationship | Rule already written | Physical FK | Class |
|---|---|---|---|
| Assignment → SeasonPilgrim | Same tenant, same season, read/FK only | Not created | DOCUMENTED intent |
| Assignment → Family Group | Reference only | Not created | DOCUMENTED intent |
| Assignment → Place / Position | Non-overlapping in time for the same place or position | Enforcement TBD | DOCUMENTED rule |
| Timeline → Assignment or SeasonPilgrim | Historical, not rewritten | Not created | DOCUMENTED intent |
| Place → Employee | “unless a future rule” | Not created | UNKNOWN |
| Transport Point → Facility | Additive later, owned by Transportation | Explicitly absent | DOCUMENTED future |

Overlap and capacity **cannot** be expressed as a simple UNIQUE on MySQL time ranges. That limit is proven in transportation (ADR-0018 T7). Accommodation has not chosen the enforcement mechanism (DATA_MODEL.md line 51).

---

## 10. Business Rules & Lifecycle

Documented operational meanings (not a stored transition graph):

```text
Assigned  →  Arrived at facility  →  Checked-in  →  Checked-out
```

Unknown edges (all **REQUIRES APPROVAL**):

- Is arrival mandatory before check-in? (BUSINESS_RULES.md line 106 says TBD.)
- Can an assignment be cancelled only before check-in (concept R7) after arrival?
- Does check-out require a prior check-in?
- Can a stay be extended after check-in, and does extension rewrite the interval or append a timeline event?
- May two intervals for the same SeasonPilgrim overlap?
- Are gaps allowed? (line 41 TBD.)
- Draft assignment: does it consume capacity?
- What happens to an in-house pilgrim when the SeasonPilgrim is cancelled? Transportation’s rule (cancel if not departed, else Needs Review) is **not** copied into accommodation docs.

Season write policy for accommodation is **UNKNOWN**. Transportation’s approved split (ADR-0018 I2: planning in Draft and Active; execution only in Active; Closed rejects every write) is a candidate to propose, not an accommodation rule.

---

## 11. RBAC & Authorization Requirements

| Item | State | Evidence | Class |
|---|---|---|---|
| Namespace | `accommodation.*` only | PERMISSIONS.md lines 6–8 | DOCUMENTED |
| Verb strings | Not decided. Do not seed `PermissionCatalog.php` | PERMISSIONS.md lines 10–21 | DOCUMENTED blocker |
| Separate powers | view; maintain places; allocate/confirm; relocate (bulk verb TBD); arrival/check-in/check-out; cancel before check-in; later sync conflicts; notification configuration | PERMISSIONS.md lines 12–19 | DOCUMENTED |
| Role matrix | TBD. Foundation note: only `tenant_owner` received season permissions; other templates are a pending business decision (ADR-0017 addendum item 10) | DOCUMENTED gap |
| Policies | “Policies remain authoritative” | PERMISSIONS.md line 25 | DOCUMENTED |
| Personas | “Existing templates only. Which roles allocate, check in, or resolve sync conflicts is TBD.” | README.md line 48 | DOCUMENTED |

Deny-by-default: no permission → 403. Cross-tenant → 404, never 403 (security baseline). UI `can()` hides actions; it does not authorize.

---

## 12. Multi-Tenancy & Season Isolation

| Rule | Evidence | Class |
|---|---|---|
| `tenant_id` from context only | BUSINESS_RULES.md line 6; DATA_MODEL.md line 10 | DOCUMENTED |
| Seasonal rows reference foundation Season | BUSINESS_RULES.md line 6 | DOCUMENTED |
| Header `X-Season-Id` resolved only through the tenant-scoped season query | Season API lines 10–14 | IMPLEMENTED (foundation) |
| Another tenant’s season id → 404 | Season API line 12 | IMPLEMENTED |
| Record in a different season than the context → 404 | Season API line 14 | IMPLEMENTED |
| Closed season writes → `422 SEASON_CLOSED` | Season API line 15; `SeasonGuard.php` lines 17–21 | IMPLEMENTED for foundation writes. Accommodation must call the same guard once it writes. **Not implemented** |
| Jobs | `SeasonAware` trait captures tenant + season | Season API line 16 | IMPLEMENTED mechanism |
| Composite FKs make cross-tenant and cross-season references impossible at the database | migration header lines 10–15 | IMPLEMENTED pattern to copy |

Accommodation must not introduce `runWithoutTenancy()` or trust `tenant_id` / `season_id` from JSON.

---

## 13. Concurrency & Data Integrity

| Risk control | Specified for accommodation? | Evidence | Class |
|---|---|---|---|
| No intentional overbooking | Yes | BUSINESS_RULES.md lines 84–85 | DOCUMENTED |
| Reject overlapping assignments on one place/position | Yes | DATA_MODEL.md line 51; TEST_PLAN.md line 14 | DOCUMENTED |
| How to enforce overlap under concurrency | **No** | DATA_MODEL.md line 51 “TBD” | UNKNOWN |
| Position-based capacity formula | **No** | BUSINESS_RULES.md line 87 | UNKNOWN |
| One SeasonPilgrim in two places at once | **Not stated** | — | UNKNOWN |
| Capacity row lock / deadlock retry | Not stated. Transportation has a proven pattern (ADR-0018 H4) | PROPOSED reuse after approval |
| Timeline immutability | Yes, no silent rewrite. Correction policy TBD | BUSINESS_RULES.md lines 127–128 | DOCUMENTED |
| History survives capacity decrease and SeasonPilgrim cancellation | Yes | DATA_MODEL.md line 52; BUSINESS_RULES.md line 167 | DOCUMENTED |
| Idempotency | Required for the later offline slice only | BUSINESS_RULES.md lines 137–138 | DOCUMENTED later |

Transportation’s own concurrency suite was **not re-run** in this audit (**NOT VERIFIED**). Do not treat this document as a MySQL race-test result.

---

## 14. Auditing, Notifications & Events

| Channel | Rule | Class |
|---|---|---|
| `audit_logs` via `Core/Audit` | Who changed what. Actor, before/after, correlation id. Secrets never in values. | DOCUMENTED (DATA_MODEL.md lines 55–57). Event name list **TBD** |
| Operational timeline | What happened to the stay. Separate table/concept. Not event sourcing. | DOCUMENTED |
| In-app notifications | ADR-0013 is the only delivery channel. Configuration concept only. Do not add `NotificationType` cases in the first sprint (BUSINESS_RULES.md line 160). | DOCUMENTED |
| Domain events | No accommodation events exist. `SeasonPilgrimCancelled` is the inbound event to handle. Outbound events are not specified. | UNKNOWN outbound |
| Documents | “Private files, if attachments are added later, use the Documents module” (BUSINESS_RULES.md line 171). Attachments are not a current requirement. | DOCUMENTED conditional |

---

## 15. Testing Strategy

No Pest or Vitest files exist for accommodation (TEST_PLAN.md lines 1–6, 34).

When behavior exists, TEST_PLAN.md lines 10–30 requires:

- Cross-tenant id → 404; 401; 403.
- Overlap → 422; sequential stays allowed.
- Capacity-based assignment without a position succeeds while capacity remains; position-based assignment without a position is rejected.
- Over capacity → 422; lowering capacity keeps rows and still rejects a further over-capacity insert.
- Transport arrival does not create check-in.
- Combined relocate + check-in writes two timeline events.
- Both timestamps stored.
- Timeline not silently rewritten.
- Read model states; no write to pilgrim current-room columns.
- Family audience uses a foundation family-group id and does not create the group.
- `SeasonPilgrim.status` unchanged.
- Audit rows on critical mutations.
- UI permission and loading/empty/error states.
- Offline and QR cases only in their later slices.

Also required by project testing rules, not repeated as accommodation-specific rows: tenant factories, no production data, deterministic security tests. Concurrency tests should follow the transportation MySQL multi-process pattern **after** the lock design is approved — that suite is not specified in the accommodation TEST_PLAN today (**gap**).

---

## 16. Performance Considerations

NOT VERIFIED by measurement. No volume numbers are in the accommodation docs (MODULE_TEMPLATE item 22 is unanswered).

Expected pressure once built:

- Availability checks for a place across date ranges (index leading with `tenant_id`, `season_id`, place/position id, time).
- Current-accommodation read model per SeasonPilgrim (avoid N+1 if embedded on pilgrim lists).
- Closing readiness counts (transportation uses grouped counts in `TransportationClosingReadiness.php` lines 32–50 — copy the aggregate style, not per-row PHP loops).
- Overlap checks must lock the place/position (or a capacity parent), not scan the whole season inside a request without a bound.

Do not add a maintained projection until the “query vs projection” TBD is decided (BUSINESS_RULES.md line 25). A projection that Transportation can write would violate the source-of-truth rule.

---

## 17. Missing Requirements & Open Questions

These must be answered by product **before** migrations. They are not implementation bugs.

1. **Place taxonomy.** Is a hotel a place, or a parent of rooms? Is a Mina camp a place, a parent of tents, or both? Are building and floor in scope? (BUSINESS_RULES.md line 52.)
2. **Mode changes.** Can a capacity-based room gain beds later?
3. **Audience location.** Place, demand, assignment, or combination? What if `pilgrims.gender` is null?
4. **Family grain.** Is the stored row always one SeasonPilgrim, with family rules applied by the allocator? Is reserved family capacity a number on the place?
5. **Calendar.** Hijri dates vs Gregorian instants in the tenant timezone; inclusive end; gaps; overlap of one pilgrim’s own stays.
6. **Draft vs confirmed.** Does a draft consume capacity and block overlap?
7. **Transition graph.** Arrival required? Cancel after arrival? Extend vs new interval?
8. **Cancellation of the participation.** Release the place, keep a checked-in row, or raise a review? Transportation’s T8 is not an accommodation rule.
9. **Season phase.** Planning in Draft? Execution only in Active? Any write on Closed?
10. **Closing readiness.** Which open stays BLOCK close?
11. **Permission verb list and role grants**, including whether bulk relocation is its own verb.
12. **Overlap and capacity enforcement** under two concurrent requests (lock target and transaction boundary).
13. **Position capacity.** Count of position rows vs a separate number.
14. **Pilgrim double-book.** Forbid overlapping intervals for the same SeasonPilgrim?
15. **Read model implementation.** Query or projection, and who may read it (transport UI later).
16. **Timeline corrections.** Append-only supersede (as transport I8) or forbidden entirely?
17. **Notification event list.** Explicitly deferred; do not invent types.
18. **Responsible employee / documents / QR / offline.** All TBD or later. Out of the first schema unless pulled in by a decision.
19. **Transport Point link.** Separate change to Transportation after facility identity exists.
20. **Stale docs.** `MVP_KNOWN_LIMITATIONS.md` still says transportation is not built. Fixing that doc is out of this audit’s write scope; it should be a follow-up so planners do not skip the existing backend.

---

## 18. Architectural Risks

| ID | Risk | Severity | Evidence | If ignored |
|---|---|---|---|---|
| AR-1 | Starting schema while columns and verbs are TBD | **Critical** | ADR-0017 line 109; ACCEPTANCE_CRITERIA.md line 25; ROADMAP.md line 64; MODULE_TEMPLATE.md line 38 | Invented fields become the product. Violates “do not invent business requirements”. |
| AR-2 | `current_*` accommodation columns on Pilgrim / SeasonPilgrim | **Critical** | ADR-0017 lines 80, 139; schema tests lines 84–96 | Breaks sequential stays and the locked foundation. |
| AR-3 | Accommodation writes `SeasonPilgrim.status` or transportation rows | **Critical** | ADR-0017 lines 49, 74; BUSINESS_RULES.md lines 163–166 | Cross-context corruption. Foundation and transport are LOCKED. |
| AR-4 | Cross-tenant or cross-season FK using a single id column | **Critical** | Season migration lines 10–15; tenancy rules | Isolation depends on application memory. Composite FKs exist specifically to prevent this. |
| AR-5 | Overlap check without row locks | **High** | DATA_MODEL.md line 51 TBD; ADR-0018 T7/H4 shows UNIQUE cannot express ranges and plain reads miss concurrent commits | Double-booking under concurrency. |
| AR-6 | Capacity counted differently in position mode vs “actual capacity” | **High** | BUSINESS_RULES.md line 87 | Silent overbooking or false 422. |
| AR-7 | Auto check-in from transport arrival | **High** | BUSINESS_RULES.md line 102; TEST_PLAN.md line 20 | False occupancy. Explicitly forbidden. |
| AR-8 | Erasing timeline or assignment rows on SeasonPilgrim cancel | **High** | BUSINESS_RULES.md line 167; F7 | Historical corruption. |
| AR-9 | Last-write-wins if offline is built early | **High** | ADR-0017 line 143; BUSINESS_RULES.md lines 140–142 | Occupancy conflicts. Later slice only. |
| AR-10 | New notification channel or premature `NotificationType` | **Medium** | BUSINESS_RULES.md lines 158–160; ADR-0013 | Scope break. |
| AR-11 | Treating Contracts `hotel` as inventory | **Medium** | README.md line 44 | Two sources of “rooms”. |
| AR-12 | N+1 read model on pilgrim lists | **Medium** | No projection decision (line 25) | List endpoints degrade. Not a data-loss risk. |
| AR-13 | Nullable gender used as Male/Female audience | **Medium** | `pilgrims.gender` nullable, migration line 21; audience TBD line 60 | Wrong-room allocation. |
| AR-14 | Editing Transportation to add a facility FK in the first accommodation PR | **Medium** | ADR-0018 I14 lines 94 | Couples two deliveries. Link is approved only once the facility exists, as an additive change. |
| AR-15 | Closing a season while stays are checked in, with no readiness contributor | **Medium** | Contract exists; no accommodation contributor; blocking list TBD | Closed season with live occupancy, or an arbitrary block. |
| AR-16 | Stale “transportation not built” limitation | **Low** | MVP_KNOWN_LIMITATIONS.md line 36 | Planners re-implement transport or miss its contracts. |

---

## 19. Recommended Implementation Phases

These phases are a plan, not approval to code. **Phase A is a product decision, not a coding sprint.**

### Phase A — Close blocking TBDs (required before code)

Answer questions 1–16 in section 17 in the module `DATA_MODEL.md`, `PERMISSIONS.md`, `API.md`, and `BUSINESS_RULES.md`. Name columns, verbs, error codes, and the transition graph. Do not add buildings, floors, QR, or offline unless the decision explicitly includes them.

### Phase B — First backend slice (only after Phase A)

Smallest slice that still matches the invariants:

- Places (one approved taxonomy) and optional positions.
- Assignment intervals for one SeasonPilgrim.
- Capacity and overlap enforcement with the approved lock design.
- Operational states and an append-only timeline (`occurred_at`, `recorded_at`).
- Register `SeasonClosingReadinessContributor`, `SeasonPilgrimOperationalReferenceChecker`, and `SeasonResourceDeletionChecker`.
- Listen to `SeasonPilgrimCancelled` with the approved effect (keep history).
- `SeasonGuard` on writes.
- Audit events.
- Pest tests from TEST_PLAN.md lines 10–28, plus a MySQL concurrency test for overlap and capacity.
- No UI required to prove the API. No offline, no QR, no `NotificationType`, no transport-point FK.

### Phase C — Read model and API stability

Current Accommodation read model (assigned / arrived / checked-in / checked-out, including “checked out with a future assignment”). Confirm Transportation still cannot write it.

### Phase D — Planning concepts that Phase A actually specified

Demand, queue, draft vs confirmed, auto/incremental allocation, family reservation, bulk relocation. Skip any concept still marked TBD.

### Phase E — Frontend

Arabic RTL screens from UI.md lines 12–19, `can()` visibility, loading/empty/error. No auto check-in on a transport screen.

### Phase F — Later slices (explicitly not first)

Offline queue and conflict resolution. QR. Notification configuration wired to existing in-app delivery. Optional Transport Point → Facility link in Transportation.

---

## 20. Final Readiness Assessment

| Question | Answer |
|---|---|
| Is the bounded context approved? | **Yes** (ADR-0017, change request, module 18). DOCUMENTED |
| Is the foundation ready to be referenced? | **Yes**, backend only. IMPLEMENTED. Do not modify it. |
| Is transportation ready to be referenced? | **Yes**, as a non-writer and as a pattern source. Do not mutate it in the first slice. Facility link is future. |
| Does a physical data model exist? | **No.** |
| Can coding start now? | **No.** |
| Blocker | Schemas, permission verbs, stored statuses, calendar, overlap enforcement, and the transition graph are TBD. ADR-0017 line 109, ACCEPTANCE_CRITERIA.md line 25, and ROADMAP.md line 64 forbid the sprint until those are written. |
| Safe to reuse without touching LOCKED modules | Season context middleware, `SeasonGuard`, composite FK style, the four container tags, `SeasonPilgrimCancelled`, `Core/Audit`, in-app notifications (later), Documents (only if attachments are later approved). |
| Must not build in the first sprint | Offline sync, QR, new notification channels, current-room columns, transport auto check-in, finance, pilgrim mobile app. |

**Implementation cannot safely begin.** The next authorized step is a product decision that fills the TBD list in section 17. After that text is approved in the module docs, Phase B can be estimated. This audit stops here.

---

## Evidence index (primary)

| Claim | Path | Lines | Class |
|---|---|---|---|
| Module not implemented; offline/QR later | `docs/09-modules/18-accommodation-and-camps/README.md` | 3–4, 28 | DOCUMENTED |
| Occupancy source of truth | same | 7–11 | DOCUMENTED |
| TBD field schemas | `BUSINESS_RULES.md` | 3, 41, 52, 60, 80, 87, 106, 117, 128, 152, 160 | DOCUMENTED |
| No physical tables yet | `DATA_MODEL.md` | 3–4, 14–16, 59–61 | DOCUMENTED |
| Verbs not seeded | `PERMISSIONS.md` | 3, 10–21 | DOCUMENTED |
| Implementation blocked | `ACCEPTANCE_CRITERIA.md` | 23–25 | DOCUMENTED |
| ADR status | `docs/10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md` | 3, 76–92, 109–119 | DOCUMENTED |
| Do not start while TBDs open | same | 109 | DOCUMENTED |
| No accommodation link yet | `docs/10-decisions/ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md` | 21–23, 94 | DOCUMENTED |
| Roadmap blocker | `docs/00-project/ROADMAP.md` | 60–64 | DOCUMENTED |
| Glossary | `docs/00-project/GLOSSARY.md` | 49–51 | DOCUMENTED |
| Extension interfaces | `backend/app/Modules/SeasonPilgrims/Contracts/*.php` | see section 4.4 | IMPLEMENTED |
| Transport registration pattern | `backend/app/Modules/Transportation/TransportationServiceProvider.php` | 37–53 | IMPLEMENTED |
| No accommodation module provider | `backend/bootstrap/providers.php` | 4–12 | IMPLEMENTED |
| Gender nullable | `backend/database/migrations/2026_10_05_100001_create_pilgrims_table.php` | 21 | IMPLEMENTED |
| No current-room columns | `backend/tests/Feature/SeasonPilgrims/SeasonPilgrimsSchemaTest.php` | 84–96 | IMPLEMENTED |
| Closed-season guard | `backend/app/Modules/SeasonPilgrims/Support/SeasonGuard.php` | 12–21 | IMPLEMENTED |
| MySQL concurrency of accommodation | — | — | **NOT VERIFIED** (no code) |
| MySQL concurrency of transportation | `docs/09-modules/17-transportation-and-fleet/TEST_PLAN.md` | 30–46 | DOCUMENTED as existing; **NOT VERIFIED** by this audit |

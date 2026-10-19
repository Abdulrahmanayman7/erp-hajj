# Accommodation Sprint 2 — Gate B Verification (Database & Concurrency Foundation)

> **Date:** 2026-10-19  
> **Scope:** Gate B only — status-aware assignment CHECK, `accommodation_pilgrim_locks`, overlap indexes, locking infrastructure.  
> **Out of scope:** Confirmation APIs, capacity services, family batch confirm, check-in/out, cancel, listeners, frontend, role-template grants, `accommodation_events`, permission seed for `confirm`.  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Verdict:** **PASS**

Gate C was **not** started.

---

## 1. Environment safety

| Check | Result |
|---|---|
| Test database | `erp_hajj_testing` only (`phpunit.mysql.xml` forces `DB_DATABASE`) |
| Preflight `SELECT DATABASE()` | `erp_hajj_testing` |
| Server | MariaDB **10.4.27** |
| Development DB `erp_hajj` | Not migrated/rolled back by these tests; table count left at **85** (preflight pattern from Sprint 1) |
| Disk | TEMP/TMP redirected to `D:\erp-hajj-1\.tmp` (C: low free space) |

No `migrate:fresh` against `erp_hajj`. Pest `RefreshDatabase` / `DatabaseTruncation` target the testing connection only.

---

## 2. Files created / modified

### Created

| File | Role |
|---|---|
| `backend/database/migrations/2026_10_19_190001_create_accommodation_pilgrim_locks_table.php` | Lock-anchor table + composite FKs |
| `backend/database/migrations/2026_10_19_190002_replace_accommodation_assignment_booking_check.php` | Replace `acc_asg_draft_ck` → `acc_asg_booking_ck`; overlap indexes |
| `backend/app/Modules/Accommodation/Models/AccommodationPilgrimLock.php` | Eloquent model |
| `backend/app/Modules/Accommodation/Support/AccommodationTransaction.php` | `DB::transaction(..., 3)` |
| `backend/app/Modules/Accommodation/Support/AccommodationLockOrder.php` | Deterministic lock helpers + safe anchor create |
| `backend/tests/Feature/Accommodation/AccommodationGateBSchemaTest.php` | Schema / FK / lock-order / rollback tests |
| `backend/tests/Concurrency/AccommodationPilgrimLockConcurrencyTest.php` | Multi-process unique insert race |
| `backend/tests/Concurrency/accommodation_lock_worker.php` | Independent PDO worker |
| `docs/reviews/ACCOMMODATION_SPRINT2_GATE_B_VERIFICATION.md` | This report |

### Modified

| File | Change |
|---|---|
| `backend/app/Modules/Accommodation/Models/AccommodationAssignment.php` | Docblock for Gate B CHECK |
| `backend/tests/Feature/Accommodation/AccommodationSchemaTest.php` | Expect pilgrim_locks; allow valid confirmed CHECK rows; reject cancelled / invalid drafts |

Sprint 1 migrations were **not** edited.

---

## 3. Migration details and constraints

### `accommodation_pilgrim_locks`

- Columns: `id`, `tenant_id`, `season_id`, `season_pilgrim_id`, `timestamps`
- Unique: `(tenant_id, season_id, season_pilgrim_id)`
- Unique: `(tenant_id, id)`
- FK `(tenant_id, season_id)` → `seasons`
- FK `(tenant_id, season_id, season_pilgrim_id)` → `season_pilgrims`
- No occupancy payload; not a substitute for locking `season_pilgrims`

### Assignment CHECK `acc_asg_booking_ck`

Replaces `acc_asg_draft_ck`:

- **draft:** Sprint 1 predicates unchanged (null operational/family/checkout; `effective_ends_at = original_ends_at`; interval valid)
- **confirmed:** null operational/checkout; `effective_ends_at = original_ends_at`; interval valid; `family_group_id` optional at DB layer
- Other `booking_status` values rejected

### Indexes (lead with `booking_status` to avoid InnoDB FK index adoption)

- `acc_asg_pilgrim_status_start_idx` — `(booking_status, tenant_id, season_id, season_pilgrim_id, starts_at)`
- `acc_asg_unit_status_start_idx` — `(booking_status, tenant_id, unit_id, starts_at)`
- `acc_asg_position_status_start_idx` — `(booking_status, tenant_id, season_id, season_position_id, starts_at)`

---

## 4. Locking infrastructure

| Component | Behavior |
|---|---|
| `AccommodationTransaction::ATTEMPTS` | `3` (same as Transportation) |
| Lock order API | `lockUnits` → `ensureAndLockPilgrimAnchors` → `lockSeasonUnits` → `lockSeasonPositions` → `lockAssignments` (each `orderBy` + `lockForUpdate`) |
| Anchor create under concurrency | Insert; on unique violation (SQLSTATE 23000 / MySQL 1062), ignore and `SELECT … FOR UPDATE` the committed row |
| Never | `lockForUpdate` on `season_pilgrims` for occupancy |

No capacity reservation or confirmation side effects in these helpers.

---

## 5. Commands executed

```text
php D:\erp-hajj-1\.tmp\acc_gateb_preflight.php
# → 10.4.27-MariaDB, erp_hajj_testing

cd backend
php vendor/bin/pest tests/Feature/Accommodation --compact
# SQLite :memory: → 20 passed, 1 skipped (MySQL-only rollback)

php vendor/bin/pest -c phpunit.mysql.xml `
  tests/Feature/Accommodation `
  tests/Concurrency/AccommodationPilgrimLockConcurrencyTest.php --compact
# → 22 passed (227 assertions)
```

---

## 6. Test results

| Suite | Result |
|---|---|
| Feature Accommodation (SQLite) | **20 passed**, 1 skipped |
| Feature Accommodation (MariaDB) | **21 passed** (includes Gate B rollback of 2 migrations + re-migrate) |
| Concurrency pilgrim lock (MariaDB, 2 processes / 2 connections) | **1 passed** — exactly one insert wins; loser `DUPLICATE`; row count 1 |
| **Total MariaDB Gate B run** | **22 passed** |

### Draft compatibility

- Overlapping drafts still allowed; season-unit `capacity` unchanged by draft creates  
- Draft API behavior not altered (no confirm routes)  
- Invalid draft shapes still fail CHECK  

### Confirmed DB support (schema only)

- Valid `booking_status='confirmed'` row insert succeeds under CHECK  
- `cancelled` and invalid confirmed/draft shapes rejected  

---

## 7. Remaining risks

| Risk | Note |
|---|---|
| No `accommodation.confirm` seeded yet | Deferred to Gate C (catalog + zero role grants) |
| No `accommodation_events` table | Deferred; not required for lock foundation |
| Capacity / family / cross-season logic | Gate C — not in Gate B |
| MySQL 8.0 CI image | Not run; local proof is MariaDB 10.4.27 |
| C: disk pressure | Continues to constrain local builds |
| Staff cancel of confirmed | Still excluded (scoped approval dependency) |

---

## 8. Final verdict

### **PASS**

Gate B database and concurrency foundation is verified on MariaDB `erp_hajj_testing`. Do **not** start Gate C automatically from this report.

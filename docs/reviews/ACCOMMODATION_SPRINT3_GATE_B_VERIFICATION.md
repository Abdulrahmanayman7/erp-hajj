# Accommodation Sprint 3 — Gate B Verification (Database & Operational Integrity)

> **Date:** 2026-10-19  
> **Scope:** Gate B only — widen assignment CHECK, `actual_departed_at`, `relocated_from_assignment_id`, `accommodation_events`, ops permission catalog (zero role grants).  
> **Out of scope:** Arrive / check-in / check-out / relocate / cancel APIs; listeners; frontend; occupancy-filter application change; role-template grants; ADR acceptance.  
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
| Development DB `erp_hajj` | Table count **85** before and after (untouched) |
| `erp_hajj_testing` tables | **96 → 97** (`accommodation_events` added) |
| Disk | TEMP/TMP → `D:\erp-hajj-1\.tmp` (C: ~234 MB free) |

No `migrate:fresh` against `erp_hajj`. No MySQL 8 claim.

---

## 2. Files created / modified

### Created

| File | Role |
|---|---|
| `backend/database/migrations/2026_10_19_200001_add_accommodation_assignment_ops_columns.php` | `actual_departed_at`, `relocated_from_assignment_id` + composite FK |
| `backend/database/migrations/2026_10_19_200002_widen_accommodation_assignment_booking_check.php` | Sprint 3 `acc_asg_booking_ck` |
| `backend/database/migrations/2026_10_19_200003_create_accommodation_events_table.php` | Append-only events + type CHECK |
| `backend/app/Modules/Accommodation/Models/AccommodationEvent.php` | Eloquent model (update/delete blocked) |
| `backend/tests/Feature/Accommodation/AccommodationSprint3GateBSchemaTest.php` | Schema / FK / rollback / permissions |
| `docs/reviews/ACCOMMODATION_SPRINT3_GATE_B_VERIFICATION.md` | This report |

### Modified

| File | Change |
|---|---|
| `backend/app/Modules/Accommodation/Models/AccommodationAssignment.php` | Columns, casts, `relocatedFrom()` |
| `backend/app/Core/Authorization/PermissionCatalog.php` | Five ops verbs; still excluded from role templates |
| `backend/tests/Feature/Accommodation/AccommodationSchemaTest.php` | Events table present; cancelled shapes; permission list |
| `backend/tests/Feature/Accommodation/AccommodationGateBSchemaTest.php` | Rollback peels Sprint 3 first |
| `backend/tests/Feature/Accommodation/AccommodationSprint1Test.php` | Permission catalog expectation |
| `docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md` | Gate B implemented notes |

Sprint 1 / Sprint 2 migrations were **not** edited.

---

## 3. Migration details

### Columns on `accommodation_assignments`

| Column | Type | Notes |
|---|---|---|
| `actual_departed_at` | nullable `dateTime` | B2 late departure; does not extend reservation |
| `relocated_from_assignment_id` | nullable `unsignedBigInteger` | Destination → source chain |

FK `acc_asg_relocated_from_fk`: `(tenant_id, season_id, relocated_from_assignment_id)` → `accommodation_assignments (tenant_id, season_id, id)` **RESTRICT**. Same tenant and season only; no cross-season relocate at DB layer.

### CHECK `acc_asg_booking_ck` (Sprint 3)

| Branch | Key predicates |
|---|---|
| **draft** | Sprint 1 + `actual_departed_at` null + `relocated_from_assignment_id` null |
| **confirmed** | ops in (`null`,`assigned`,`arrived`,`checked_in`); no checkout_source; no actual_departed_at; `effective_ends_at <= original_ends_at` |
| **cancelled** | ops in (`null`,`assigned`,`arrived`); no checkout/actual; interval retained |
| **completed** | ops **IS NOT NULL** and `checked_out`; checkout_source **IS NOT NULL** and `staff`; `effective_ends_at <= original_ends_at` |

`IS NOT NULL` on completed equalities is required: SQL CHECK treats `col = 'x'` as UNKNOWN when `col` is NULL (would otherwise accept invalid completed rows).

**Self-reference:** not in CHECK — MariaDB 10.4 error **1901** (`AUTO_INCREMENT` cannot appear in CHECK of `id`). Gate C must reject `relocated_from_assignment_id = id`.

### `accommodation_events`

| Column | Notes |
|---|---|
| `tenant_id`, `season_id`, `assignment_id` | Composite FK to assignment (tenant+season+id) |
| `event_type` | CHECK: `arrived`, `checked_in`, `checked_out`, `relocated`, `cancelled` |
| `occurred_at`, `recorded_at` | Business vs accept time |
| `actor_user_id` | nullable FK users |
| `source`, `idempotency_key` | unique `(tenant_id, idempotency_key)` |
| `correlation_id`, `meta` | nullable; `meta` JSON for relocate pair ids |
| `created_at` only | No `updated_at`; model blocks update/delete |

### Permissions

Catalogued (synced by `PermissionCatalogSynchronizer`):  
`accommodation.arrive`, `check_in`, `check_out`, `relocate`, `cancel_assignment` — **zero** system role-template grants (accommodation.* still excluded from `tenant_owner` template).

---

## 4. Valid / invalid state matrix (DB)

| Shape | DB |
|---|---|
| draft (Sprint 1) | Accept |
| confirmed + null ops (Sprint 2) | Accept |
| confirmed + assigned/arrived/checked_in | Accept |
| cancelled + null/assigned/arrived | Accept |
| completed + checked_out + staff + early `effective_ends_at` | Accept |
| completed + late `actual_departed_at` with `effective_ends_at = original_ends_at` | Accept |
| confirmed + actual_departed_at | Reject |
| cancelled + checked_in | Reject |
| completed without staff / without checked_out | Reject |
| `effective_ends_at > original_ends_at` | Reject |
| unknown booking_status | Reject |
| relocate FK cross-tenant / cross-season | Reject |
| delete source while destination references it | Reject (RESTRICT) |
| event wrong type / duplicate idempotency / cross-tenant assignment | Reject |

---

## 5. Relocation chain representation

- Destination row stores `relocated_from_assignment_id` = source assignment id.  
- Multi-hop: A ← B ← C by walking `relocated_from_assignment_id`.  
- Source rows remain; no cascade delete.  
- Cross-season relocate impossible via composite FK.

---

## 6. Commands and results

```text
php .tmp/acc_db_count.php
# 10.4.27-MariaDB; erp_hajj_tables=85; erp_hajj_testing active

cd backend
# SQLite
php vendor/bin/pest tests/Feature/Accommodation --compact
# → 37 passed, 2 skipped (MySQL-only rollback tests)

# MariaDB
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/Accommodation --compact
# → 39 passed (463 assertions)
```

| Suite | Result |
|---|---|
| Feature Accommodation (SQLite) | **37 passed**, 2 skipped |
| Feature Accommodation (MariaDB) | **39 passed** |
| Sprint 3 Gate B rollback (3 steps) + remigrate | **Passed** on MariaDB; pilgrim_locks retained during Sprint 3-only rollback |
| `erp_hajj` table count | **85** unchanged |

---

## 7. Regression findings

- Sprint 1 draft CRUD / overlapping drafts unaffected.  
- Sprint 2 confirmation feature tests green on MariaDB after CHECK widen.  
- Capacity/overlap application filter still counts **confirmed** only (completed inclusion deferred to Gate C — no completed rows via API yet).  
- Existing locking foundation unchanged.

---

## 8. Remaining technical risks

| Risk | Note |
|---|---|
| Self-ref not in CHECK | Gate C must reject; MariaDB AUTO_INCREMENT limitation |
| Occupancy filter not yet counting `completed` | Gate C when checkout/relocate write completed rows |
| No ops APIs yet | Capacity still stuck for confirmed until Gate C cancel/checkout |
| MySQL 8.0 | Not tested |
| C: disk pressure | Continues |

---

## 9. Final verdict

### **PASS**

Sprint 3 Gate B database foundation is verified on MariaDB 10.4.27 / `erp_hajj_testing`. Do **not** start Gate C automatically.

# Accommodation Sprint 1 — MySQL Verification

> **Date:** 2026-10-15
> **Scope:** Phase 1 database foundation only. No APIs, controllers, services, policies, or frontend.
> **Verdict:** **PASS**

The local server is MariaDB 10.4.27, reached through PHP's `mysql` driver. MySQL 8.0 is the CI image and was not running on this machine. `phpunit.mysql.xml` forces the disposable database `erp_hajj_testing`. The development database `erp_hajj` was not migrated, rolled back, or dropped. Its table count stayed **85**.

## Server and database

| Item | Value |
|---|---|
| Server | MariaDB 10.4.27 (`mariadb.org binary distribution`) |
| SQL mode | `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION` |
| `explicit_defaults_for_timestamp` | `0` |
| Host | `127.0.0.1:3306`, user `root` |
| Test database | `erp_hajj_testing` (created empty for this run; `utf8mb4` / `utf8mb4_unicode_ci`) |
| Development database | `erp_hajj` (85 tables before and after) |
| Config | `backend/phpunit.mysql.xml` (`DB_DATABASE=erp_hajj_testing`, `force="true"`) |

`erp_hajj_testing` did not exist before this verification. No other database was used for DDL.

## Commands

All application commands set `DB_DATABASE=erp_hajj_testing` before connecting. A preflight read `SELECT DATABASE()` and stopped unless the name was `erp_hajj_testing`.

1. Read-only inspection of `VERSION()` and `information_schema.SCHEMATA`.
2. `CREATE DATABASE erp_hajj_testing`.
3. `php artisan migrate --force --no-interaction` — failed on `accommodation_assignments` (see correction).
4. Same migrate after the correction — ten accommodation migrations applied.
5. Constraint script against `erp_hajj_testing` only — 0 failures.
6. `php artisan migrate:rollback --step=10 --force` — the ten accommodation migrations, newest first.
7. `php artisan migrate --force` — those ten migrations applied again.
8. `php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/Accommodation/AccommodationSchemaTest.php tests/Feature/SeasonPilgrims/SeasonPilgrimsSchemaTest.php tests/Feature/Transportation/TransportSchemaTest.php`
9. Same accommodation file again, against the populated test database.
10. `php artisan test tests/Feature/Accommodation/AccommodationSchemaTest.php` with `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`.

`migrate:fresh` was not run by hand. Pest's `RefreshDatabase` rebuilds only `erp_hajj_testing`.

## Migration results

The first migrate created the foundation, SeasonPilgrims, and Transportation tables, then failed:

`SQLSTATE[42000] 1067 Invalid default value for 'original_ends_at'`

`accommodation_assignments` was not created. The nine earlier accommodation tables from that attempt were rolled back later with the corrected set.

After the correction, all ten migrations applied, rolled back, and applied again:

- `2026_10_15_180001_create_accommodation_facilities_table`
- `2026_10_15_180002_create_accommodation_structures_table`
- `2026_10_15_180003_create_accommodation_units_table`
- `2026_10_15_180004_create_accommodation_positions_table`
- `2026_10_15_180005_create_accommodation_season_facilities_table`
- `2026_10_15_180006_create_accommodation_season_units_table`
- `2026_10_15_180007_create_accommodation_season_positions_table`
- `2026_10_15_180008_create_accommodation_assignments_table`
- `2026_10_15_180009_create_accommodation_facility_scopes_table`
- `2026_10_15_180010_create_accommodation_supervisor_scopes_table`

## Required correction

`starts_at`, `original_ends_at`, and `effective_ends_at` are `DATETIME NOT NULL`, not `TIMESTAMP NOT NULL`.

MariaDB 10.4 with `explicit_defaults_for_timestamp=0` gives only the first `TIMESTAMP NOT NULL` a real default. The next ones receive `0000-00-00 00:00:00`, which `NO_ZERO_DATE` rejects. `DATETIME` stores the same `Y-m-d H:i:s` values and is accepted under this SQL mode. The draft check compares those three columns and rejected a shortened end and an end that is not after the start.

No Foundation or Transportation migration was edited. ADR-0019 and Module 18 were not edited.

## Constraint verification

All of the following passed on `erp_hajj_testing`.

**CHECK.** `acc_asg_draft_ck` is enforced (MariaDB error 4025). It rejects `booking_status = 'confirmed'`, a non-null `operational_status`, a non-null `family_group_id`, a non-null `checkout_source`, `effective_ends_at` different from `original_ends_at`, and `original_ends_at` equal to `starts_at`. Two overlapping drafts insert, and `capacity` stays 2. A draft may omit `season_position_id`.

**Generated columns.** `accommodation_facility_scopes.active_grant` and `accommodation_supervisor_scopes.active_grant` are generated. Assignments have no generated column. One live facility grant is allowed. A second live grant fails on `acc_fsc_live_grant_uq`. After `revoked_at` is set, a new live grant inserts, and exactly one row has `active_grant = 1`. The same one-live-grant rule holds for `acc_ssc_live_grant_uq`.

**Composite foreign keys.** Cross-tenant unit, cross-facility structure parent, cross-facility season unit, cross-season season facility, and a position from another season unit are rejected (error 1452). `acc_asg_position_fk` references `(tenant_id, season_id, id, season_unit_id)`. User grants reference `users.id`.

**Unique indexes.** Present and enforced: `(tenant_id, code)` on facilities, one facility per season, one unit per season, one position per season, `(tenant_id, season_id, id, season_unit_id)` on season positions, and `(tenant_id, season_id, id)` on assignments. There is no unique key on unit and time.

**RESTRICT.** Deleting a unit that a position still references fails (error 1451).

## Rollback

`migrate:rollback --step=10` dropped only the ten accommodation tables, in reverse migration order. `seasons` and `transport_review_items` remained. Accommodation table count was 0. The following migrate recreated all ten tables.

## Test results

| Command | Result |
|---|---|
| Pest `phpunit.mysql.xml`: Accommodation, SeasonPilgrims, and Transportation schema tests | **30 passed (472 assertions)** |
| Same accommodation file again on the populated test database | **10 passed (61 assertions)** |
| Accommodation schema tests on SQLite `:memory:` | **10 passed (61 assertions)** |

An earlier Pest run reported 6 accommodation setup failures while a second PHP process was migrating `erp_hajj_testing` at the same time. Those were connection races, not assertion failures. The single-process runs above passed. Foundation and Transportation application code and migrations were not modified.

MySQL 8.0 was not executed here. The driver path under test is the MySQL grammar, including `ALTER TABLE ... ADD CONSTRAINT acc_asg_draft_ck CHECK (...)`.

## Verdict

**PASS.** The corrected Sprint 1 schema migrates, enforces tenant, season, and facility foreign keys, keeps drafts non-reserving, enforces one live scope grant, and rolls back on the disposable MariaDB 10.4 database. Phase 2 was not started.

# Transportation and Fleet — Test Plan

> **Status:** Implemented as Pest suites under `backend/tests/Feature/Transportation/` (backend sprint 1).
> **Last updated:** 2026-10-05

## Suites

| Suite | Covers |
|---|---|
| Tenancy | Cross-tenant 404 on every route-model family (points, vehicles, external drivers, season vehicles, entitlements, movements, runs, passengers, timeline events, reviews); foreign-tenant ids in payloads rejected; `tenant_id` / `season_id` payload ignored; 401 unauthenticated; 403 without permission |
| Season | Season Context resolution; cross-season route model 404; Closed season blocks every write (incl. corrections); execution requires Active season; planning allowed in Draft |
| Registry | Point CRUD, code uniqueness, deactivate, delete blocked when used; Vehicle number generation, plate normalization/uniqueness, Asset/Contract link (same tenant, Asset uniqueness, nullOnDelete), delete blocked when in fleet; ExternalDriver CRUD |
| Season fleet | Duplicate SeasonVehicle rejected (app + DB); capacity / service level validation; capacity reduction guard; default crew validation (active same-tenant Employee, exactly ≤ 1 driver kind) |
| Crew | Run creation writes the initial vehicle / driver (internal or external) / supervisor as current history rows; substitution ends it and appends a second row (two-row history, one current) for each type; no crew rows without defaults; reason required after execution began; at most one current (DB); vehicle / internal driver / ExternalDriver overlap rejected; supervisor overlap allowed; substitution blocked on terminal Runs |
| Movement / Run lifecycle | Every valid transition and representative invalid ones; completion guards; cancellation cascade; depart requires driver; boarded passengers block run cancel |
| Passengers | Active-only eligibility; individual / group / family bulk resolution and source snapshot; membership change does not alter manifest; duplicate rejected; capacity enforced (vehicle capacity, override); no-show/cancel release capacity; passenger lifecycle transitions |
| Atomic assignment | Group / family / individual rejected as a whole for a non-active member, a duplicate, another season's participation, an entitlement mismatch without authorized override, or capacity; no passenger rows, timeline events, or audit written; forced failure after the first insert rolls everything back; authorized override admits mismatched members with source snapshot |
| Entitlements | CRUD, uniqueness per Program, at-least-one rule; mismatch rejected; override requires permission + reason + audit; live mismatch exposed; override of existing passenger |
| Assignment decision history | Compatible assignment then Program change: decision unchanged, live mismatch, readiness warning, later override leaves the decision intact; entitlement edits after assignment (compatible → live mismatch, overridden mismatch → live compatible) leave the decision intact; SeasonVehicle / Vehicle reclassification and vehicle substitution leave it intact; model rejects updates to decision columns |
| Timeline | Events appended per action; occurred_at default / future rejected / backdate rules; correction appends superseding event, updates entity timestamp, one correction per event; model immutability |
| Foundation integration | `SeasonPilgrimCancelled` handling for each passenger state, idempotency, review creation; operational reference checker blocks SeasonPilgrim delete; deletion checker blocks Season / Program delete with `SEASON_RESOURCE_REFERENCED`; group/family delete not blocked by source metadata; Program deactivation unaffected; readiness codes (blocking / warning / ok) and closing blocked |
| Audit | Each audited action writes the expected event with tenant, actor, entity, reason |
| Schema / DB | Tables, generated flags, unique constraints, composite FKs reject cross-season / cross-tenant rows; explicit FK/index names ≤ 64 chars |
| Absence | No GPS / QR / offline / seat / Accommodation / `current_vehicle` columns or tables; no such routes |

## Concurrency

Capacity, duplicate passenger, current-assignment, and overlap rules are enforced inside transactions with `lockForUpdate` on the Run, resources, and SeasonPilgrim rows (deterministic order), locking reads for the checks, and deadlock retry. Database UNIQUE constraints are the final guard and are asserted directly.

`backend/tests/Concurrency/TransportConcurrencyTest.php` (MySQL only; skipped on SQLite) runs each side in its own PHP process and connection (`tests/Concurrency/worker.php`). Both are released at the same instant and hold their transaction for 1.5 s before COMMIT, so the loser must wait on the winner's locks. Process timeout 90 s, `innodb_lock_wait_timeout` 20 s. Races:

| Race | Expected |
|---|---|
| Last seat (two adds, one seat left) | one succeeds, one `TRANSPORT_CAPACITY_EXCEEDED`; occupancy = capacity |
| Same SeasonVehicle, overlapping Runs | one succeeds, one `TRANSPORT_VEHICLE_OVERLAP`; one current row for the vehicle |
| Same internal driver, overlapping Runs | one succeeds, one `TRANSPORT_DRIVER_OVERLAP` |
| Same ExternalDriver, overlapping Runs | one succeeds, one `TRANSPORT_DRIVER_OVERLAP` |
| Same supervisor, overlapping Runs (allowed) | both succeed; no deadlock surfaces |
| Same SeasonPilgrim twice on one Run | one row; loser `TRANSPORT_PASSENGER_DUPLICATE` (no raw SQL error) |

Outcomes (including transaction attempts) are appended to `storage/logs/transport-concurrency.jsonl`.

## Environments and commands

- SQLite in-memory (default CI job): `php artisan test`.
- MySQL 8 (CI job `backend-mysql`, or a disposable local server). `phpunit.mysql.xml` forces database `erp_hajj_testing`; host / port / credentials come from `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`. From `backend/`:

  ```bash
  # migrations (env: DB_CONNECTION=mysql DB_DATABASE=erp_hajj_testing + credentials)
  php artisan migrate:fresh --force
  php artisan migrate:rollback --step=15 --force   # the 15 Transportation migrations
  php artisan migrate --force
  # suites
  php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/Transportation tests/Feature/SeasonPilgrims
  php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency
  ```

- MariaDB 10.4 (local, disposable database) when available.

# Transportation and Fleet — Test Plan

> **Status:** Implemented as Pest suites under `backend/tests/Feature/Transportation/` plus MySQL concurrency under `backend/tests/Concurrency/`.
> **Last updated:** 2026-10-27

## Suites

| Suite | Covers |
|---|---|
| Tenancy | Cross-tenant 404 on every route-model family (points, vehicles, external drivers, season vehicles, entitlements, movements, runs, passengers, timeline events, reviews); foreign-tenant ids in payloads rejected; `tenant_id` / `season_id` payload ignored; 401 unauthenticated; 403 without permission |
| Season | Season Context resolution; cross-season route model 404; Closed season blocks every write (incl. corrections); execution requires Active season; planning allowed in Draft |
| Registry | Point CRUD, code uniqueness, deactivate, delete blocked when used; Vehicle number generation, plate normalization/uniqueness, Asset/Contract link (same tenant, Asset uniqueness, nullOnDelete), delete blocked when in fleet; ExternalDriver CRUD |
| Season fleet | Duplicate SeasonVehicle rejected (app + DB); capacity / service level validation; capacity reduction guard; default crew validation (active same-tenant Employee, exactly ≤ 1 driver kind) |
| Crew / initial assignments | Run creation writes the first current vehicle / driver / supervisor history rows from SeasonVehicle defaults; substitution ends current and appends; at most one current (DB); vehicle / internal driver / ExternalDriver overlap rejected; supervisor overlap allowed; substitution blocked on terminal Runs |
| Movement / Run lifecycle | Every valid transition and representative invalid ones; completion guards; cancellation cascade; depart requires driver; boarded passengers block run cancel |
| Passengers | Active-only eligibility; individual / group / family bulk resolution and source snapshot; **all-or-nothing** atomic assignment (no silent skip); membership change does not alter manifest; duplicate rejected; capacity enforced; no-show/cancel release capacity; passenger lifecycle transitions; transaction rollback |
| Entitlements / history | CRUD, uniqueness per Program, at-least-one rule; mismatch rejected; override requires permission + reason + audit; **immutable assignment_context** survives Program change, entitlement edit, and vehicle classification change; live mismatch exposed; later override does not rewrite history |
| Timeline | Events appended per action; occurred_at default / future rejected / backdate rules; correction appends superseding event, updates entity timestamp, one correction per event; model immutability |
| Foundation integration | `SeasonPilgrimCancelled` handling for each passenger state, idempotency, review creation; operational reference checker blocks SeasonPilgrim delete; deletion checker blocks Season / Program delete with `SEASON_RESOURCE_REFERENCED`; group/family delete not blocked by source metadata; Program deactivation unaffected; readiness codes (blocking / warning / ok) and closing blocked |
| Audit | Each audited action writes the expected event with tenant, actor, entity, reason |
| Schema / DB | Tables, generated flags, unique constraints, composite FKs reject cross-season / cross-tenant rows; explicit FK/index names ≤ 64 chars |
| Absence | No GPS / QR / offline / seat / Accommodation / `current_vehicle` columns or tables; no such routes |

## Concurrency (MySQL 8 only)

Real concurrent transactions are verified under `backend/tests/Concurrency/TransportConcurrencyTest.php` using separate PHP worker processes (`tests/Concurrency/worker.php`). Each side holds its transaction open briefly so the loser waits on InnoDB locks, then decides on committed state. Deadlocks retry via `DB::transaction(..., 5)` and surface as domain errors, never raw SQL.

| Case | Expected |
|---|---|
| Last seat | Exactly one add succeeds; loser `TRANSPORT_CAPACITY_EXCEEDED`; occupancy never exceeds capacity |
| Same SeasonVehicle / overlapping Runs | One succeeds; loser `TRANSPORT_VEHICLE_OVERLAP`; one current assignment for that vehicle |
| Same internal driver / overlapping Runs | One succeeds; loser `TRANSPORT_DRIVER_OVERLAP` |
| Same external driver / overlapping Runs | One succeeds; loser `TRANSPORT_DRIVER_OVERLAP` |
| Duplicate passenger | One row only; loser `TRANSPORT_PASSENGER_DUPLICATE` |
| Same supervisor / overlapping Runs | Both succeed (overlap allowed); no deadlock |
| Same open breakdown / concurrent replace | Exactly one replacement; loser `TRANSPORT_BREAKDOWN_NOT_OPEN`; one current vehicle assignment |

SQLite CI skips these tests (`markTestSkipped`). Bounded process timeout: 90s.

## Breakdown / replacement (S02 Round 2)

Feature suite: `backend/tests/Feature/Transportation/TransportBreakdownTest.php`.

| Case | Expected |
|---|---|
| Record + list | Creates `open` breakdown; audit `TRANSPORT_BREAKDOWN_RECORDED` |
| RBAC / closed season | Operate without permission → 403; closed season → `SEASON_CLOSED` |
| Capacity-sufficient replace | Status `replaced`; T7 current vehicle updated; passengers retained; idempotent replay |
| Capacity gap | Without plan → `TRANSPORT_REPLACEMENT_CAPACITY_PLAN_REQUIRED`; with plan → moves + retained counts |
| Overlap | Busy replacement → `TRANSPORT_VEHICLE_OVERLAP` |
| Close / cancel | Blocks further replace (`TRANSPORT_BREAKDOWN_NOT_OPEN`) |
| Cross-tenant | Show/close foreign breakdown → 404 |

### Recommended MySQL CI command

```bash
# From backend/, with MySQL 8 and disposable database erp_hajj_testing:
php artisan migrate:fresh --force
# 15 Transportation migrations plus later modules stacked above them (currently 31)
php artisan migrate:rollback --step=31 --force
php artisan migrate --force
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/Transportation tests/Feature/SeasonPilgrims
php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/TransportConcurrencyTest.php
```

Environment (also used by the `backend-mysql` GitHub Actions job in `.github/workflows/ci.yml`):

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp_hajj_testing
DB_USERNAME=root
DB_PASSWORD=<root password>
CACHE_STORE=array
```

`phpunit.mysql.xml` forces `DB_CONNECTION=mysql` and `DB_DATABASE=erp_hajj_testing` so a developer database is never wiped.

## Environments

- SQLite in-memory (default CI backend job).
- MySQL 8 (GitHub Actions `backend-mysql` job + local disposable container/database).
- MariaDB 10.4 (local disposable database) when available.

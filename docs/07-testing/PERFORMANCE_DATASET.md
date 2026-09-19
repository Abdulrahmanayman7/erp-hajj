# Local performance dataset

> **Status:** Local / disposable only  
> **Last updated:** 2026-09-19

Synthetic volume for trying lists, search, dashboard, and audit under load. **Never production data. Never run in `APP_ENV=production`.**

## What it creates

Isolated MySQL/MariaDB database `erp_hajj_perf` (name must contain `perf`) and tenant `perf_large`.

Medium profile (approximate row counts):

| Table | Rows |
|---|---|
| users | 1,000 |
| employees | 5,000 |
| contracts | 10,000 |
| meetings | 10,000 |
| decisions | 15,000 |
| tasks | 75,000 |
| inventory_items | 15,000 |
| inventory_movements | 150,000 |
| assets | 20,000 |
| asset_custodies | 40,000 |
| notifications | 200,000 |
| audit_logs | 500,000 |

## Generate (preferred)

From `backend/`:

```bash
php artisan performance:seed --confirm-perf --rebuild --dump-sql=erp_hajj_perf/erp_hajj_perf.sql
```

- `--rebuild` drops and recreates only the isolated `perf` database. It does not touch `erp_hajj` / demo DBs.
- `--dump-sql=` writes a local `.sql` dump (gitignored under `backend/erp_hajj_perf/`). The dump is large (hundreds of MB to ~1 GB); do not commit it.

## Point the app at it

In `backend/.env` (local only):

```env
DB_DATABASE=erp_hajj_perf
```

Restart `php artisan serve`. Login:

- Email: `owner@perf-large.invalid`
- Password: `PerfFixture@123`

Restore `DB_DATABASE=erp_hajj` when you are done.

## Import an existing dump

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS erp_hajj_perf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root erp_hajj_perf < backend/erp_hajj_perf/erp_hajj_perf.sql
```

If the dump was created with `--databases`, import without selecting a schema:

```bash
mysql -u root < backend/erp_hajj_perf/erp_hajj_perf.sql
```

## Safety

- Command refuses production.
- Requires `--confirm-perf`.
- Database name must contain `perf`.
- Rows bypass domain Actions for insert speed; they are labeled disposable fixtures.

# Volume dataset (`erp_hajj`)

> **Status:** Local / disposable only  
> **Last updated:** 2026-09-19

Synthetic volume for trying lists, search, dashboard, and audit under load. **Never production data. Never run in `APP_ENV=production`.**

The app default database name is **`erp_hajj`**. The volume fixture uses that same name so `.env` does not need `DB_DATABASE` switched.

Tenant: `perf_large`.

## Medium profile (approximate row counts)

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

## Login

Restart `php artisan serve` (`.env` already has `DB_DATABASE=erp_hajj`).

- Email: `owner@perf-large.invalid`
- Password: `PerfFixture@123`

## SQL dump

Gitignored under `backend/erp_hajj_perf/`:

| File | Purpose |
|---|---|
| `erp_hajj.sql` | Full dump (`CREATE DATABASE` / `USE erp_hajj`) — local MySQL only |
| `erp_hajj_tables.sql` | Tables only — no `CREATE DATABASE` |
| `erp_hajj_hostinger.sql.gz` | Same tables dump, gzipped for Hostinger phpMyAdmin |
| `erp_hajj_before_volume.sql` | Backup of the previous small local `erp_hajj` |

Hostinger phpMyAdmin: select `u561062901_erp_hajj` → Import → `erp_hajj_hostinger.sql.gz`. Do **not** import `erp_hajj.sql` (it tries to create `erp_hajj`, which the Hostinger user cannot access). Importing replaces whatever is already in that database.

Import locally:

```bash
mysql -u root < backend/erp_hajj_perf/erp_hajj.sql
```

## Regenerate (isolated)

Seeding still builds a throwaway `erp_hajj_perf` database first (the name must contain `perf`) so `--rebuild` cannot drop `erp_hajj` by accident:

```bash
php artisan performance:seed --confirm-perf --rebuild --dump-sql=erp_hajj_perf/erp_hajj_tables.sql
```

Then move/replace into `erp_hajj` only when you intend to overwrite the default local database.

## Safety

- Command refuses production.
- Requires `--confirm-perf`.
- Generator database name must contain `perf`.
- Importing `erp_hajj.sql` into Hostinger production **replaces** the live tenant. Use a separate Hostinger database, or import `erp_hajj_tables.sql` only into an explicit test schema.

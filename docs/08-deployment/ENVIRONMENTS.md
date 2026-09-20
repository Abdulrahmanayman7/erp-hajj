# Environments

> **Status:** Implementation-ready guidance; provider subscription still TBD where noted
> **Last updated:** 2026-08-12

## Purpose

Define the four environments and their rules. Exact hosting provider / Saudi topology may still be TBD contractually — see below.

## 1. Local Development

- **Native setup (no Docker)**: local PHP 8.2+/Composer, Node.js LTS/npm, `php artisan serve` + `npm run dev` — see the root [README.md](../../README.md).
- Individual `.env` per developer (never committed). Copy from `backend/.env.example` and `frontend/.env.example`.
- Local MySQL database preferred; SQLite acceptable for quick Pest runs.
- Local queue worker when testing notifications/jobs: `php artisan queue:work`.
- Local scheduler when testing scanners: `php artisan schedule:work` or cron `schedule:run`.
- Mail: `MAIL_MAILER=log` (or Mailpit — choice TBD).
- **No production data — ever.**
- Local volume testing uses the default database name `erp_hajj` — see [PERFORMANCE_DATASET.md](../07-testing/PERFORMANCE_DATASET.md). The previous small local copy is backed up as `backend/erp_hajj_perf/erp_hajj_before_volume.sql`.
- Seeded test tenants with separate tenant fixtures (at least two tenants to exercise isolation).
- Bootstrap Owner: `php artisan tenant:bootstrap-owner` after migrate/seed.

## 2. Testing / CI

- Isolated test database; repeatable migrations (fresh migrate per run).
- Factories for all entities.
- Multi-tenant isolation tests always run.
- No production external dependencies; mock storage and notifications where appropriate.
- Pipeline: `.github/workflows/ci.yml` — backend (`composer validate`, Pint, Pest, `composer audit` advisory) and frontend (`npm ci`, Vitest, type-check + build, `npm audit` advisory) on push/PR to `develop` and `main`.
- CI Pest uses SQLite by default; validate MySQL migrate path on disposable DB before release (see [DEPLOYMENT.md](DEPLOYMENT.md)).

## 3. Staging

- Production-like configuration; separate credentials and separate database.
- Fake or sanitized data only.
- HTTPS; queue worker; scheduler running.
- Persistent private document storage.
- Backup + restore drill performed here before production go-live.
- Error monitoring enabled (tool TBD).

## 4. Production

- **Hosting inside Saudi Arabia where contractually required** (see PDPL note in [SECURITY_BASELINE.md](../06-security/SECURITY_BASELINE.md)).
- HTTPS; secure secrets management; restricted access.
- `APP_ENV=production`, `APP_DEBUG=false`.
- Backups with tested restore procedures ([BACKUP_AND_RECOVERY.md](BACKUP_AND_RECOVERY.md)).
- Private file storage (never public paths for Documents).
- Queue workers; scheduler every minute.
- Monitoring and centralized logs.
- Deployments only from `main` after the release workflow.
- Incident process (definition TBD).

## Rules

- Configuration via environment variables only; no secrets in the repository.
- No production credentials stored in git.
- Same migration path across staging and production; no manual schema changes.
- Deployments only from `main` after the release workflow (see [TEAM_AND_GIT_WORKFLOW.md](../00-project/TEAM_AND_GIT_WORKFLOW.md)).
- Branch flow: `feature/* → develop` for integration; `develop → main` for release only.

## Branch protection (manual GitHub settings)

Recommended (do not automate from this doc):

- Protect `main`: require PR, require status checks, disable direct pushes.
- Protect `develop`: require PR + CI.

## TBD

- **Infrastructure ownership and subscription payment: TBD (not confirmed).**
- Hosting provider and exact topology: TBD.
- Monitoring/logging/alerting stack: TBD.
- Domain, TLS, and tenant URL scheme: TBD.

## Related

- [DEPLOYMENT.md](DEPLOYMENT.md)
- [MVP_RELEASE_CHECKLIST.md](MVP_RELEASE_CHECKLIST.md)
- [BACKUP_AND_RECOVERY.md](BACKUP_AND_RECOVERY.md)
- [../00-project/MVP_KNOWN_LIMITATIONS.md](../00-project/MVP_KNOWN_LIMITATIONS.md)

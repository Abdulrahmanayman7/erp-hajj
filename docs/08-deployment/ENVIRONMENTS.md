# Environments

> **Status:** Approved requirements; provisioning TBD
> **Last updated:** 2026-08-06

## Purpose

Define the four environments and their rules. Infrastructure is not provisioned at this stage.

## 1. Local Development

- **Native setup (no Docker)**: local PHP 8.2+/Composer, Node.js LTS/npm, `php artisan serve` + `npm run dev` — see the root [README.md](../../README.md).
- Individual `.env` per developer (never committed).
- Local MySQL database; local Redis when needed; local queue worker.
- Fake mail service (e.g. Mailpit/log driver — choice TBD).
- **No production data — ever.**
- Seeded test tenants with separate tenant fixtures (at least two tenants to exercise isolation).

## 2. Testing / CI

- Isolated test database; repeatable migrations (fresh migrate per run).
- Factories for all entities.
- Multi-tenant isolation tests always run.
- No production external dependencies; mock storage and notifications where appropriate.
- Pipeline: `.github/workflows/ci.yml` — backend (composer validate, Pint, Pest) and frontend (Vitest, type-check + build) on push/PR to `develop` and `main`, with dependency caching.

## 3. Staging

- Production-like configuration; separate credentials and separate database.
- Fake or sanitized data only.
- HTTPS; queue worker; scheduler running.
- Backup testing performed here.
- Error monitoring enabled (tool TBD).

## 4. Production

- **Hosting inside Saudi Arabia where contractually required** (see PDPL note in [SECURITY_BASELINE.md](../06-security/SECURITY_BASELINE.md)).
- HTTPS; secure secrets management; restricted access.
- Backups with tested restore procedures.
- Private file storage (never public paths).
- Redis; queue workers; scheduler.
- Monitoring and centralized logs.
- Incident process (definition TBD).

## Rules

- Configuration via environment variables only; no secrets in the repository.
- No production credentials exist or are stored at this stage.
- Same migration path across staging and production; no manual schema changes.
- Deployments only from `main` after the release workflow (see [TEAM_AND_GIT_WORKFLOW.md](../00-project/TEAM_AND_GIT_WORKFLOW.md)).

## TBD

- **Infrastructure ownership and subscription payment: TBD (not confirmed).**
- Hosting provider and topology: TBD.
- Monitoring/logging/alerting stack: TBD.
- Backup schedule and retention: TBD.
- Domain, TLS, and tenant URL scheme: TBD.

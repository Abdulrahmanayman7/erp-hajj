# MVP Release Checklist

> **Status:** Executable checklist — do not mark production complete unless steps were actually performed
> **Last updated:** 2026-08-12
> **Recommended version tag (when approved):** `v0.1.0` — do not create the tag until release is approved

Use Markdown checkboxes. Another developer/ops person should be able to follow this without tribal knowledge.

## 0. Preconditions

- [ ] Working on release candidate from `main` (after `develop → main`) or an agreed RC branch
- [ ] CHANGELOG Unreleased notes reviewed for the release section
- [ ] No known **P0** blockers in the Sprint 019 report
- [ ] Pre-deploy MySQL backup taken
- [ ] Pre-deploy private documents storage backup taken
- [ ] `APP_ENV=production` and `APP_DEBUG=false` confirmed for the target
- [ ] Secrets present only in the host secret store (not in git)

## 1. Quality gates (CI / local RC verification)

- [ ] Backend: `composer validate --strict`
- [ ] Backend: `vendor/bin/pint --test`
- [ ] Backend: `php artisan test` (full Pest green)
- [ ] Backend: `composer audit` reviewed (no unresolved P0)
- [ ] Frontend: `npm ci`
- [ ] Frontend: `npm run type-check` (or via `npm run build`)
- [ ] Frontend: `npm run test` (Vitest green)
- [ ] Frontend: `npm run build`
- [ ] Frontend: `npm audit` reviewed (no unresolved P0)
- [ ] CI green on the release PR

## 2. Database

- [ ] Fresh-migrate path validated on a **disposable** database (not valuable local data)
- [ ] Forward migrate on staging/RC schema succeeded
- [ ] Seed/RBAC provision idempotent (`db:seed` / catalog sync safe to re-run)
- [ ] Production: `php artisan migrate --force` only (never `migrate:fresh`)

## 3. Backend deploy

- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] Migrations applied
- [ ] Config/route cache refreshed if used
- [ ] Queue workers restarted
- [ ] Scheduler crontab / platform schedule confirmed (`schedule:run` every minute)
- [ ] Private storage mount/bucket writable and non-public
- [ ] PHP `upload_max_filesize` ≥ 20M and `post_max_size` ≥ 25M
- [ ] CORS / `FRONTEND_URL` / Sanctum domains match SPA origin
- [ ] `GET /api/v1/health` returns ok without leaking environment internals

## 4. Frontend deploy

- [ ] Vercel (or host) Root Directory = `frontend`
- [ ] Install `npm ci`, Build `npm run build`, Output `dist`
- [ ] `VITE_API_URL` points at production API HTTPS origin
- [ ] SPA rewrite to `index.html` verified for `/app`, `/app/tasks`, deep links
- [ ] Direct browser refresh on deep links does not return host 404

## 5. Production smoke (manual)

- [ ] Health
- [ ] Login (valid user)
- [ ] Login rejection (bad password) / throttle behavior sanity
- [ ] Dashboard loads for permitted role
- [ ] Users / Roles list (Owner or GM)
- [ ] Organization tree
- [ ] Employees list
- [ ] Contracts list + one lifecycle action if safe
- [ ] Meetings list
- [ ] Decisions list
- [ ] Tasks list
- [ ] Documents upload + authorized download
- [ ] Inventory balances / one movement if safe
- [ ] Assets / custody view
- [ ] Notifications bell / list
- [ ] Audit list (Auditor / Owner / GM)
- [ ] Logout

## 6. Security / ops

- [ ] No stack traces on forced 500 in production
- [ ] Private document JSON does not expose storage paths
- [ ] Correlation ID present on API responses (header)
- [ ] Failed jobs monitored
- [ ] Log channel does not print Authorization headers / passwords

## 7. UAT handoff

- [ ] [MVP_UAT_CHECKLIST.md](../07-testing/MVP_UAT_CHECKLIST.md) assigned
- [ ] Dedicated UAT tenant/users ready (no fake production data seeded into prod)
- [ ] Known limitations communicated to stakeholders

## 8. Post-release

- [ ] Tag `v0.1.0` (or agreed version) created **only after** approval
- [ ] CHANGELOG release section published
- [ ] Incident contact / rollback owner named

## Known incomplete before “full 21-module MVP”

- [ ] System settings API/UI (`tenant_settings.*`) — permissions/catalog exist; full settings vertical slice still pending (see ROADMAP)

## Related

- [DEPLOYMENT.md](DEPLOYMENT.md)
- [BACKUP_AND_RECOVERY.md](BACKUP_AND_RECOVERY.md)
- [ENVIRONMENTS.md](ENVIRONMENTS.md)

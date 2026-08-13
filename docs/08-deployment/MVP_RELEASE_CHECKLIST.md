# MVP Release Checklist

> **Status:** RC `release/0.1.0-uat` validation executed 2026-08-13 — **not** production-complete
> **Last updated:** 2026-08-13
> **Recommended version tag (when approved):** `v0.1.0` — do not create the tag until release is approved

Use Markdown checkboxes. Another developer/ops person should be able to follow this without tribal knowledge.

## 0. Preconditions

- [x] Working on release candidate from agreed RC branch (`release/0.1.0-uat`)
- [x] CHANGELOG Unreleased notes reviewed for the release section
- [x] No known **P0** blockers in automated RC validation (2026-08-13)
- [ ] Pre-deploy MySQL backup taken *(production/staging — pending ops)*
- [ ] Pre-deploy private documents storage backup taken *(pending ops)*
- [ ] `APP_ENV=production` and `APP_DEBUG=false` confirmed for the target *(pending production)*
- [ ] Secrets present only in the host secret store (not in git) *(pending production)*

## 1. Quality gates (CI / local RC verification)

- [x] Backend: `composer validate --strict`
- [x] Backend: `vendor/bin/pint --test`
- [x] Backend: `php artisan test` (full Pest green — **382 / 2131**, run twice on SQLite)
- [x] Backend: `composer audit` reviewed (no advisories found)
- [x] Frontend: `npm ci`
- [x] Frontend: `npm run type-check` (or via `npm run build`)
- [x] Frontend: `npm run test` (Vitest **215** green; second parallel run hit host OOM — re-ran with `--maxWorkers=1` green)
- [x] Frontend: `npm run build`
- [x] Frontend: `npm audit` reviewed (0 vulnerabilities)
- [ ] CI green on the release PR *(not verified in this session — confirm on GitHub)*

## 2. Database

- [x] Fresh-migrate path validated on a **disposable** database (`erp_hajj_release_test` / MariaDB 10.4 via XAMPP) — all migrations Ran
- [x] Forward migrate on disposable RC schema succeeded
- [x] Seed/RBAC provision idempotent (`db:seed` twice: roles `+7/~0` then `+0/~7`; 0 permission/role dups; Owner settings update; GM view-only)
- [ ] Production: `php artisan migrate --force` only (never `migrate:fresh`) *(pending production)*

## 3. Backend deploy

- [ ] `composer install --no-dev --optimize-autoloader` *(pending production)*
- [ ] Migrations applied *(pending production)*
- [ ] Config/route cache refreshed if used *(pending production)*
- [ ] Queue workers restarted *(pending production)*
- [ ] Scheduler crontab / platform schedule confirmed (`schedule:run` every minute) *(pending production)*
- [ ] Private storage mount/bucket writable and non-public *(pending production)*
- [ ] PHP `upload_max_filesize` ≥ 20M and `post_max_size` ≥ 25M *(documented in `.env.example`; host not verified)*
- [ ] CORS / `FRONTEND_URL` / Sanctum domains match SPA origin *(config reviewed; production origins pending)*
- [x] `GET /api/v1/health` covered by automated tests / local gates (no `APP_ENV` leak per Sprint 019)

## 4. Frontend deploy

- [x] Vercel (or host) Root Directory = `frontend` *(documented; `frontend/vercel.json` SPA rewrites present)*
- [x] Install `npm ci`, Build `npm run build`, Output `dist` *(local build verified)*
- [ ] `VITE_API_URL` points at production API HTTPS origin *(pending production)*
- [x] SPA rewrite to `index.html` verified conceptually for `/app`, `/app/tasks`, `/app/settings`, `/app/audit/:id` via `vercel.json`
- [ ] Direct browser refresh on deep links does not return host 404 *(needs hosted preview)*

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

*(Section 5 remains pending — no production/staging smoke in this RC session.)*

## 6. Security / ops

- [x] Private document JSON path exposure covered by Documents Pest suite
- [x] Correlation ID exposed in CORS (`X-Correlation-ID` in `config/cors.php`)
- [x] `failed_jobs` migration present (jobs table suite)
- [ ] No stack traces on forced 500 in production *(pending production)*
- [ ] Failed jobs monitored *(pending ops)*
- [ ] Log channel does not print Authorization headers / passwords *(pending ops review)*

## 7. UAT handoff

- [x] [MVP_UAT_CHECKLIST.md](../07-testing/MVP_UAT_CHECKLIST.md) updated with RC execution notes
- [ ] Dedicated UAT tenant/users ready for **interactive** browser UAT (human)
- [x] Known limitations communicated (see MVP_KNOWN_LIMITATIONS)

## 8. Post-release

- [ ] Tag `v0.1.0` (or agreed version) created **only after** approval
- [ ] CHANGELOG release section published
- [ ] Incident contact / rollback owner named

## Known incomplete before “full 21-module MVP”

- [x] System settings API/UI (`tenant_settings.*`) — **implemented** Sprint 020 (ADR-0016)

## Related

- [DEPLOYMENT.md](DEPLOYMENT.md)
- [BACKUP_AND_RECOVERY.md](BACKUP_AND_RECOVERY.md)
- [ENVIRONMENTS.md](ENVIRONMENTS.md)

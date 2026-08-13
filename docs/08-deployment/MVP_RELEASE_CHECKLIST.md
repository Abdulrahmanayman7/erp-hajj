# MVP Release Checklist

> **Status:** P1 closure attempt `release/0.1.0-final-validation` (2026-08-13) — **RELEASE NOT READY**
> **Last updated:** 2026-08-13
> **Recommended version tag (when approved):** `v0.1.0`

## Open P1 tracker (this session)

| ID | Gate | Result |
|---|---|---|
| P1-01 | Staging / real-host smoke | **OPEN — NOT AVAILABLE** |
| P1-02 | Host PHP upload limits | **OPEN — NOT AVAILABLE** |
| P1-03 | Firefox UAT | **OPEN — FAIL** (launch aborted) |
| P1-04 | Chrome UAT | **PARTIAL** (resource errors on some routes) |
| P1-05 | Full human workflow | **OPEN — PARTIAL** |
| P1-06 | Inventory/Assets human ops | **OPEN — PARTIAL / incomplete** |
| P1-07 | Notifications rich flow | **OPEN — PARTIAL** |
| P1-08 | Dashboard/Audit/Settings populated | **PARTIAL** (Settings timezone PASS; others incomplete) |
| P1-09 | Timezone Cairo/Riyadh human | **PASS** (Settings PATCH Cairo→Riyadh via live API) |
| P1-10 | Sustained queue fanout | **OPEN — PARTIAL** (worker alive, 0 jobs) |
| P1-11 | Product/Owner sign-off | **OPEN — NOT EXECUTED** |

## Already green (do not re-litigate)

- MariaDB migrate/seed, Pest, Vitest, type-check, build, composer/pint/npm audit (nanoid lockfile), Edge baseline, backup+restore drill, scheduler list/scanners, Settings automated suite

## Staging manual checklist (for human/ops — required to close P1-01/P1-02)

1. Provision staging host with persistent PHP, MySQL/MariaDB, private storage, queue worker, cron `schedule:run`, HTTPS.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `FRONTEND_URL`, Sanctum domains, CORS origin = SPA.
3. On **staging PHP**: record `php -i` → `upload_max_filesize` ≥ 20M and `post_max_size` ≥ 25M.
4. Deploy backend (`composer install --no-dev`, `migrate --force`, restart workers).
5. Deploy frontend (Vercel root `frontend`, `npm ci` / `npm run build`, `dist`, SPA rewrites).
6. Smoke: health, login, dashboard, settings GET/PATCH, tasks, notifications unread-count, audit, document upload+download, logout.
7. Confirm `X-Correlation-ID` exposed; no stack traces; private document path not in JSON.
8. Direct refresh: `/app`, `/app/tasks`, `/app/settings`, `/app/audit/:id`.
9. Keep queue worker running; process multiple notification jobs; scanners×2 dedupe.
10. Product/Owner signs MVP_UAT_CHECKLIST.

## Local quality (preserved)

- [x] composer validate / pint / Pest / composer audit
- [x] frontend type-check / Vitest / build / npm audit = 0 after nanoid lockfile bump
- [x] Edge UAT prior PASS
- [x] Backup+restore prior PASS

## Post-release (blocked)

- [ ] Tag `v0.1.0`
- [ ] develop → main merge
- [ ] Production deploy

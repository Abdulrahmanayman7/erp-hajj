# Backend & Frontend Deployment

> **Status:** Implementation-ready for controlled MVP release
> **Last updated:** 2026-08-13

## Purpose

Describe the production topology and operational requirements for ERP Hajj. The SPA may be hosted on a static CDN (e.g. Vercel); the Laravel API **cannot** run as a pure serverless static host — it needs a persistent PHP runtime, MySQL, private storage, a queue worker, and a scheduler.

## Topology (MVP)

| Component | Role | Notes |
|---|---|---|
| Frontend (Vue SPA) | Static build (`frontend/dist`) | Vite; Root Directory `frontend/`; SPA rewrites to `index.html` |
| Backend (Laravel 12) | REST `/api/v1` + Sanctum SPA cookies | Persistent PHP-FPM/CLI host (not Vercel) |
| MySQL | System of record | Single DB, shared schema, `tenant_id` isolation |
| Queue worker | Async jobs (notifications fanout, etc.) | `php artisan queue:work` continuously |
| Scheduler | Cron scanners | `* * * * * php artisan schedule:run` |
| Private storage | Documents / tenant files | Persistent disk or object storage; **not** ephemeral |

Infrastructure provider subscription and exact Saudi hosting choice remain TBD where contractually required — see [ENVIRONMENTS.md](ENVIRONMENTS.md).

## Frontend (Vercel or equivalent)

Expected project settings:

```text
Framework: Vite
Root Directory: frontend
Install: npm ci
Build: npm run build
Output: dist
```

- Env: `VITE_API_URL` = public HTTPS origin of the Laravel API (no trailing slash).
- SPA deep links (`/app`, `/app/tasks`, `/app/assets/:id`, `/app/audit/:id`, …) must rewrite to `index.html`. Repository includes `frontend/vercel.json` for Vercel rewrites.
- Do **not** use `vue-cli-service`.

## Backend runtime

Minimum production process set:

1. Web/PHP process serving Laravel (HTTPS terminated at reverse proxy or platform).
2. MySQL reachable with migrations applied (`php artisan migrate --force` — **never** `migrate:fresh` in production).
3. Continuous queue worker.
4. Minute scheduler.
5. Persistent private filesystem (or private object storage configured as a non-public disk).

### Recommended production env (excerpt)

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
FRONTEND_URL=https://app.example.com
CORS_ALLOWED_ORIGINS=https://app.example.com
QUEUE_CONNECTION=database   # or redis when provisioned
CACHE_STORE=database        # or redis when provisioned
FILESYSTEM_DISK=local
DOCUMENT_STORAGE_DISK=local # must remain a private disk
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

See `backend/.env.example` for the full inventory. Never commit real secrets.

### Composer install (release)

```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
# restart queue workers after deploy
```

### Queue worker

```bash
php artisan queue:work --sleep=1 --tries=3 --timeout=90
```

Use a process supervisor (systemd, Supervisor, platform worker). Restart workers after every code deploy so they load new code.

Failed jobs: ensure `failed_jobs` table exists (Laravel default migration) and monitor it.

### Scheduler

```text
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Notifications scanners and other scheduled commands depend on this. Vercel does **not** run the Laravel scheduler.

Verify locally / staging:

```bash
php artisan schedule:list
```

### PHP upload limits (Documents)

Documents MVP max size is **20 MiB**. Production PHP/web server must allow:

```text
upload_max_filesize >= 20M
post_max_size >= 25M
```

### Private storage

- Paths: `tenants/{tenant_id}/documents/...` via `TenantStorage` (private disk).
- Do **not** expose Documents via `php artisan storage:link` / public disk.
- `storage:link` is only for intentionally public assets (if any).
- Ephemeral container filesystems without a mounted volume are a **release blocker** for Documents.

### CORS / Sanctum

- `FRONTEND_URL` / Sanctum stateful domains must match the SPA origin exactly (`localhost` ≠ `127.0.0.1`).
- Local: `CORS_ALLOWED_ORIGINS` may list both loopback SPA URLs; production should list only the real SPA origin.
- Do not set CORS `allowed_origins` to `*` when credentials/cookies are used.
- Response header `X-Correlation-ID` is exposed to the SPA (`config/cors.php` `exposed_headers`).

### Health smoke

```text
GET /api/v1/health
```

Public; returns application name, API version, status, timestamp. Does **not** expose `APP_ENV` or DB internals.

## Deploy sequence (safe)

1. Pre-deploy MySQL backup + private documents storage backup.
2. Maintenance window if required by ops policy.
3. Deploy backend code; `composer install --no-dev --optimize-autoloader`.
4. `php artisan migrate --force`.
5. Cache config/routes if safe for the environment.
6. Restart queue workers.
7. Deploy frontend build (Vercel / static host).
8. Run [MVP_RELEASE_CHECKLIST.md](MVP_RELEASE_CHECKLIST.md) smoke tests.
9. Confirm scheduler still ticking.

## Rollback

- **Application code:** redeploy previous known-good backend + frontend artifacts; restart workers.
- **Database:** migrations are often **not** safely reversible after data writes. Prefer restore from pre-deploy backup over `migrate:rollback` for production incidents.
- **Documents storage:** restore private files backup consistent with the DB snapshot time.
- Do not promise automatic one-click rollback unless the hosting platform provides it and it has been tested.

## Branch / release flow

```text
feature/* → develop   (integration)
develop → main        (release only)
```

Do not merge feature branches directly to `main`. See [TEAM_AND_GIT_WORKFLOW.md](../00-project/TEAM_AND_GIT_WORKFLOW.md).

Recommended GitHub branch protection (configure manually; not automated by this sprint):

- Protect `main`: require PR, require CI status checks, disallow direct pushes.
- Protect `develop` appropriately (PR + CI).

## Related

- [ENVIRONMENTS.md](ENVIRONMENTS.md)
- [BACKUP_AND_RECOVERY.md](BACKUP_AND_RECOVERY.md)
- [MVP_RELEASE_CHECKLIST.md](MVP_RELEASE_CHECKLIST.md)
- [../07-testing/MVP_UAT_CHECKLIST.md](../07-testing/MVP_UAT_CHECKLIST.md)

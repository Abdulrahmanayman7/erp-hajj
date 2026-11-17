# Hostinger — Runtime Topology & Operational Notes

> **Does not authorize** production deploy, migrations, credential changes, or data writes.  
> **Secrets** must never be committed. Paths below are operational labels, not permanent architecture.

## Observed production topology (2026-11)

| Concern | Observed value | Notes |
|---|---|---|
| Public domain | `https://erp-hij.esappx.com` | Owner/ops |
| Document root | `~/domains/erp-hij.esappx.com/public_html` | SPA + Laravel `index.php` co-located |
| Active backend release (observed) | `~/erp-hajj-release-f3c4c444/backend` | SHA-named release directory after GPS hub upgrade |
| Stale reference | `~/erp-hajj-app/backend` | Historical path still present in older docs — **do not assume active** |
| PHP | 8.2 (CloudLinux 8) | Confirm with host panel |
| `proc_open` | **Disabled** in SSH PHP CLI | Affects Composer scripts / some Artisan process spawns from SSH; FPM request path may differ — validate ops runbooks |
| Node.js on server | **Unavailable** in server shell | Build frontend on Windows/CI; upload `dist` artifacts |
| Database | Preserve existing MySQL data | Never run disposable/test suites against production |

Manual versioned directories such as `erp-hajj-release-<sha>` are **ops conveniences**, not a permanent product requirement. Future releases may use a different layout; keep `public_html/index.php` bootstrap path accurate after each cutover.

## Frontend publish

1. Build on a supported Node host (see `frontend/package.json` `engines` when present; CI Node 20/24 as configured).  
2. Upload `dist/` into `public_html` including `.htaccess` from `frontend/public/.htaccess`.  
3. Keep Laravel `index.php` in `public_html` pointing at the **active** backend release directory.  
4. After GPS permission / SPA fixes, hard-refresh or reinstall PWA so service worker does not retain a stale shell.

## Backend cutover (manual, Owner-authorized only)

1. Pre-deploy **verified** database backup (previous Hostinger cutovers may lack attested backups — treat as residual ops risk).  
2. Place release under a new directory (or agreed layout).  
3. Point `public_html/index.php` at the new release `vendor`/`bootstrap`.  
4. Preserve existing `.env` / `APP_KEY` / private storage / uploads.  
5. Run approved migrations only.  
6. Clear permission / config / route caches as needed.  
7. Signal queue restart; **independently verify** worker + scheduler health (signal alone ≠ proof).

## Queue & scheduler

| Item | Requirement | Verification |
|---|---|---|
| Queue worker | Continuous `queue:work` (or host equivalent) | Process list / job drain evidence |
| Scheduler | Cron `* * * * * php artisan schedule:run` | `schedule:list` + scanner execution evidence |
| GPS sync | Only if live outbound authorized (G4) | Default **BLOCKED** |
| Complaint SLA scanner | Fail-closed until activation timestamp set | U03 **BLOCKED** |

## PHP `proc_open` disabled

**Impact (SSH CLI):** Composer scripts or Artisan helpers that spawn subprocesses via `proc_open` may fail in SSH.

**Mitigation:**

- Prefer Hostinger panel / SSH commands that do not require subprocess spawning.  
- Run Composer/npm on a build machine, then upload artifacts.  
- Do not “enable proc_open globally” without host security review.  
- Document any Artisan command that fails under this restriction as an ops runbook exception.

## Explicit non-claims

- This file does **not** prove queue/scheduler health on production.  
- This file does **not** authorize G4 Live, G5 history, or U03 SLA activation.  
- Production browser UAT of GPS pages after the PR #68 frontend fix remains a separate evidence gate unless recorded elsewhere with screenshots.

# Backup and Recovery (MVP)

> **Status:** Policy documented; production restore **not** executed in Sprint 019 (credentials/infra TBD)
> **Last updated:** 2026-08-12

## Principle

Database backup alone is insufficient. Documents live on private storage.

```text
MySQL backup  +  private documents storage backup
```

Restore must bring both to a consistent point in time.

## MySQL

Minimum MVP ops policy (when infrastructure exists):

| Item | Guidance |
|---|---|
| Automated backup | Daily (at least) |
| Retention | ≥ 7 daily copies (adjust per contract) |
| Pre-deploy | Mandatory backup before every production migrate/deploy |
| Staging restore drill | Perform at least once before go-live |

Do **not** claim backups exist until the hosting provider/job is actually configured.

## Private documents storage

Backup the private disk/bucket used by `DOCUMENT_STORAGE_DISK` / `TenantStorage` (paths under `tenants/{id}/...`).

Exclude ephemeral `temp` if ops agrees; never omit `documents`.

## Restore procedure (manual)

1. Put application in maintenance / stop writers if needed.
2. Restore MySQL from the chosen snapshot.
3. Restore private storage to the matching snapshot.
4. Deploy matching application version.
5. Restart queue workers; confirm scheduler.
6. Smoke: health, login, dashboard, one document download for a known file.
7. Record restore time and outcome in the ops log.

## Sprint 019 limitation

No production/staging credentials were available to execute a live restore test. Treat **first production restore drill** as a release gate owned by ops/UAT.

## Related

- [DEPLOYMENT.md](DEPLOYMENT.md)
- [MVP_RELEASE_CHECKLIST.md](MVP_RELEASE_CHECKLIST.md)

# PR #63 — executive_alerts migration compatibility

> **Date:** 2026-11-14  
> **Branch:** `feature/s05-s06-s07-closure`  
> **Disposable DBs only:** `erp_hajj_pr63_mig_uat` (fresh). No production access.

## Problem

Create migration `2026_11_10_330001_create_executive_alerts_table` originally used two bare `TIMESTAMP NOT NULL` columns (`opened_at`, `last_seen_at`). Under MariaDB `NO_ZERO_DATE`, the second column’s implicit zero-date default fails migrate. MySQL 8 CI historically succeeded, so some environments may already have the table without explicit `DEFAULT CURRENT_TIMESTAMP`.

## Approach

| Path | Mechanism |
|---|---|
| **Fresh install** | Create migration updated with `->useCurrent()` for both columns |
| **Upgrade (already migrated)** | Forward-only `2026_11_14_360001_fix_executive_alerts_timestamp_defaults` runs `ALTER … DEFAULT CURRENT_TIMESTAMP` on MySQL/MariaDB; SQLite no-op |

Editing the historical create alone is **not** sufficient for DBs that already ran it — hence the forward migration.

## Verification

| Check | Result |
|---|---|
| `migrate:fresh` on `erp_hajj_pr63_mig_uat` | **PASS** — both columns `Default=current_timestamp()`; both migrations recorded |
| Pest SQLite ExecutiveAlerts | **PASS** (forward test skipped on SQLite) |
| Pest MariaDB forward-migration + schema tests | **PASS** |
| Production / Hostinger | **NOT TOUCHED** |

## Non-claims

- Does not authorize production migrate/deploy  
- Does not claim Hostinger already applied either migration  

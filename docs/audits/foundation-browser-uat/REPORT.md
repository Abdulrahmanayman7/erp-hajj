# Foundation Browser UAT — Evidence Pack

**Date:** 2026-10-29  
**Verdict:** `READY FOR OWNER ACCEPTANCE`  
**Not:** `COMPLETE — ACCEPTED` (Project Owner sign-off required)

## Isolation (no secrets)

| Item | Value |
|---|---|
| Database | MySQL schema `erp_hajj_foundation_uat` (created empty for this UAT) |
| Production / `erp_hajj` | Not used for writes/seeds/migrations in this run |
| `.env` | Untouched — API process used shell `DB_DATABASE` override only |
| Frontend | Vite `http://localhost:5173` |
| API | `http://127.0.0.1:8000` bound to UAT DB |
| Browser | Playwright `playwright-core` + installed Chrome channel |

Test accounts (local UAT only): `uat.owner@example.com`, `uat.viewer@example.com` (view-only).

## Journey results

Latest machine report: `evidence/2026-10-29T22-23-44-396Z_report.json`

| Step | Result |
|---|---|
| 0. Environment boot | PASS |
| Login + RTL shell | PASS |
| Navigation foundation pages | PASS |
| 1. Create season | PASS |
| 2. Create program | PASS |
| 3. Create group | PASS |
| 4. Create family | PASS |
| 5. Create pilgrim | PASS |
| 6. Enroll pilgrim in season | PASS |
| 7. Link program/group/family | PASS |
| 8. Open profile & verify relations | PASS |
| 9. Prevent duplicate enrollment | PASS (`SEASON_PILGRIM_ALREADY_REGISTERED`) |
| 10. Close season + block edits | PASS (API close + `SEASON_CLOSED` on write) |
| 11. Limited user RBAC | PASS (UI CTAs hidden + API 403) |
| 12. RTL + validation affordances | PASS |

Screenshots: `evidence/*_0*.png` / `*_1*.png` for the same stamp.

## Product fixes applied during UAT (journey blockers)

1. **Guest `/login` painted AppLayout** while router was still on START_LOCATION after anonymous `/me` 401 advanced `sessionGeneration`.
   - `frontend/src/App.vue` — guest chrome fallback from pathname
   - `frontend/src/modules/auth/sessionIsolation.ts` — `clearSession(..., { advance })`
   - `frontend/src/app/guards/authGuard.ts` — no generation advance on first anonymous `/me` failure
   - Regression: `sessionIsolation.test.ts` (+ authGuard suite green)

## Notes / residual risks

- Season **close** in the latest run was confirmed via API (`200`) and post-close write returned `SEASON_CLOSED`; UI close control was not always clicked (icon/row timing). Behavior under API lock is correct.
- Program re-assign may return `SEASON_PILGRIM_PROGRAM_UNCHANGED` when enroll already set the program — treated as linked.
- Viewer user must keep `tenant_id` set; `tenant_id = null` is treated as platform user by `User::isPlatformUser()`.

## Owner action

Please review screenshots + run the journey once interactively, then explicitly accept before status may move to `COMPLETE — ACCEPTED`.

# S08 — Merge Readiness Audit & Proposed PR Materials

> **Date:** 2026-11-08  
> **Branch:** `feature/s08-seasonal-templates` @ `707ca33`  
> **Base for proposed PR:** `develop` @ `e25dc8f`  
> **Owner Acceptance:** [S08-OWNER-ACCEPTANCE-2026-11-08.md](../../10-decisions/S08-OWNER-ACCEPTANCE-2026-11-08.md)  
> **This document:** inspection + PR draft only — **does not** open a PR, push, merge, or deploy

---

## 1. Divergence from `origin/develop`

| Metric | Value |
|---|---|
| Merge-base | `e25dc8f` (same as `origin/develop` tip at audit) |
| Commits ahead of develop | **12** |
| Commits behind develop | **0** |
| Files changed vs develop | **171** |
| Predicted merge conflicts | **None detected** via `git merge-tree --write-tree origin/develop HEAD` (exit 0 → tree `c0d7917…`) |

### Commits ahead

```text
707ca33 docs(s08): mark verification READY_FOR_OWNER_ACCEPTANCE after MySQL 8 CI
e0743b3 fix(s08): stabilize MySQL JSON fingerprint compare for safe-copy
80695b1 fix(ci): correct S08 MySQL schema asserts for snapshot_json
15658f1 ci(s08): enable feature-branch MySQL 8 CI for VG-02
607ff6d docs(s08): pin blocker-closure report to 6bed139
6bed139 feat(s08): close VG-03/04 blockers; keep MySQL 8 NO-GO
98be84f docs(s08): pin verification gate report to post-fix commit
2e3e360 fix(s08): unblock production build and record verification gate NO-GO
3515658 feat(s08): R6 season reminder rules, contract editor, Controls A/B/C
e0c7933 feat(s08): full-scope expansion of seasonal templates (matrix 2026-11-08.1)
a194841 merge(develop): integrate S06 into S08 feature branch
f472a4d feat(s08): implement seasonal templates and safe copy module
```

### S06 / S07 overlap

- No S06/S07 module trees rewritten for merge.
- S08 adds `SeasonProjectsSafeCopyAdapter` and related allowlist kinds only (integration, not S06 reimplementation).
- `develop` already contains merged S06/S07 at merge-base; S08 branch is **not behind**.

If `develop` moves before PR open, re-run divergence + `merge-tree` — do not treat this audit as eternal conflict-free proof.

---

## 2. Migrations

| Migration | Notes |
|---|---|
| `2026_11_07_320001_create_seasonal_templates_tables.php` | New on feature only — absent on develop |
| `2026_11_08_320002_s08_expand_templates_snapshots_and_optional_source.php` | `snapshot_json`; nullable `source_season_id` |
| `2026_11_08_320003_create_season_reminder_rules_table.php` | Composite FK `sea_rem_season_fk` |

- No duplicate `32000x` filenames on `origin/develop`.
- MySQL 8 migration/FK/JSON asserts **PASS** on CI @ `e0743b3`.
- **Do not** run production migrations under this audit.

---

## 3. Permissions / routes / schedule

| Area | Status |
|---|---|
| Permissions | `season_templates.*`, `season_copy.*`, `season_reminders.view/.manage` in `PermissionCatalog` |
| Routes | `/api/v1/season-templates`, `/api/v1/season-copy/*`, season reminder-rule routes |
| FE nav/router | Seasonal templates + reminder rules pages |
| Scheduler | `notifications:scan-season-reminders` daily |

No permission-name collision with develop catalog modules observed for these new codes.

---

## 4. Security controls still present

| Control | Present |
|---|---|
| Tenant scoping (`UsesTenantScope` / cross-tenant 404 tests) | Yes |
| Reminder-rule authorization policies | Yes |
| Safe-copy allowlist deny-by-default (`2026-11-08.2`) | Yes |
| Copied reminder rules forced **draft** | Yes |
| Contract placeholder guard (`CONTRACT_COUNTERPARTY_INCOMPLETE` / Control C) | Yes |
| Preview expiry + fingerprint/replay protection (`CanonicalJson`) | Yes |
| Scanner dedupe (Control B) | Yes |

---

## 5. CI evidence

| SHA | Run | Role |
|---|---|---|
| `e0743b3` | [42437115976](https://github.com/Abdulrahmanayman7/erp-hajj/actions/runs/42437115976) **success** | Verified implementation (MySQL 8.0.46 + S08 Pest + Control C) |
| `707ca33` | [42440689395](https://github.com/Abdulrahmanayman7/erp-hajj/actions/runs/42440689395) **success** | Docs-only tip; Frontend + Backend Laravel + Backend MySQL 8 (incl. S08 Pest + Control C) all success |

---

## 6. Proposed Pull Request (DO NOT CREATE)

**Base:** `develop`  
**Head:** `feature/s08-seasonal-templates`  

**Suggested title:**

```text
feat(s08): seasonal templates, safe copy, reminders and contract definitions
```

**Suggested body:** see [S08_PROPOSED_PR.md](S08_PROPOSED_PR.md).

### Merge checklist (for a future authorized PR)

- [ ] Tip CI green (or docs-only tip explicitly waived against `e0743b3` evidence)
- [ ] Reconfirm `git log HEAD..origin/develop` is empty (or re-merge develop first)
- [ ] Reconfirm `git merge-tree --write-tree origin/develop HEAD` clean
- [ ] Review migrations `320001`–`320003` on disposable MySQL 8
- [ ] Confirm Owner Acceptance cited; merge ≠ deploy
- [ ] No production `.env` / secrets in diff
- [ ] Stash `stash@{0}` left untouched

---

## 7. Recommendation

**GO to request Owner authorization to open a PR** (merge still separate).  
**NO-GO** for merge/deploy without those later acts.

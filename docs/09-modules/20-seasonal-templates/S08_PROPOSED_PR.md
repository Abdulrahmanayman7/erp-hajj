# Proposed PR — S08 Seasonal Templates (DO NOT PUBLISH)

> **Status:** Draft only — **not** created on GitHub  
> **Base:** `develop`  
> **Head:** `feature/s08-seasonal-templates`  
> **Owner Acceptance:** [S08-OWNER-ACCEPTANCE-2026-11-08.md](../../10-decisions/S08-OWNER-ACCEPTANCE-2026-11-08.md)  
> **Merge readiness:** [S08_MERGE_READINESS_AUDIT.md](S08_MERGE_READINESS_AUDIT.md)

---

## Title

```text
feat(s08): seasonal templates, safe copy, reminders and contract definitions
```

## Body (copy when PR creation is authorized)

```markdown
## Summary

- Implements Phase 2 **S08 — Seasonal Templates & Safe Copy** on `feature/s08-seasonal-templates`.
- Matrix **`2026-11-08.2`** — **10** allowlisted kinds; deny-by-default; no pilgrim/ops/PII cloning.
- Adds season reminder rules (R6), Arabic reminder management UI, contract-definition row editor, and contract placeholder lifecycle protection (Control C).
- Integrates safe-copy adapters for S06 planning kinds (`season_projects`, phases, task definitions) without copying budgets/PO links/progress.
- **Owner Accepted 2026-11-08** — see `docs/10-decisions/S08-OWNER-ACCEPTANCE-2026-11-08.md`.
- This PR does **not** authorize production deploy or production migrations.

## Safe-copy kinds (10)

1. `season_programs`
2. `season_pilgrim_groups`
3. `transport_entitlements`
4. `transport_season_vehicles`
5. `accommodation_season_facilities`
6. `contract_definitions`
7. `season_projects`
8. `season_project_phases`
9. `task_definitions`
10. `season_reminder_rules`

## R6 reminder rules

- Season-scoped `season_reminder_rules` with draft/active/paused/archived lifecycle.
- Triggers: absolute date + offset from season start.
- Recipients: permission_holders with allowlisted view permissions only.
- Delivery via `notifications:scan-season-reminders` (daily); inbox rows never copied.

## Arabic reminder management UI

- Route `/app/season-reminder-rules`, form drawer, nav link, Arabic strings.
- Permissions: `season_reminders.view` / `season_reminders.manage`.

## Contract definition row editor

- `ContractDefinitionRowsEditor` for template snapshot rows (D18/D19).

## Contract placeholder lifecycle protection

- Placeholder counterparty `من القالب`.
- Control C: blocks leaving draft until real counterparty; cancel from draft still allowed (`C17`/`C18`).

## S06 integration

- Safe-copy adapters for projects/phases/task definitions only.
- Budgets, PO links, owners, progress remain DENY.

## Security controls

- Tenant isolation (cross-tenant → 404).
- Reminder-rule authorization.
- Safe-copy allowlist + preview mandatory path.
- Copied reminder rules forced **draft**.
- Preview expiry / fingerprint / replay protection (`CanonicalJson`).
- Scanner dedupe (Control B).
- Contract placeholder guard (Control C).

## MySQL 8 evidence

- CI run: https://github.com/Abdulrahmanayman7/erp-hajj/actions/runs/42437115976
- SHA: `e0743b3`
- Engine: MySQL **8.0.46**
- SeasonalTemplates: **85 passed**
- Control C: **2 passed**
- Migrations/FK/JSON asserts: **PASS**

## Frontend / backend tests

- Vitest seasonal-templates + navigation: **115 passed**
- Production build: **PASS**
- Local filtered Pest suite also green (see verification gate report)

## Owner Acceptance

- Decision: **OWNER_ACCEPTED** (2026-11-08)
- Record: `docs/10-decisions/S08-OWNER-ACCEPTANCE-2026-11-08.md`
- Status: `OWNER_ACCEPTED / NOT_MERGED / NOT_DEPLOYED` until merge/deploy acts

## Accepted UAT exceptions

- **UAT-04:** Pest + API companion verify safe-copy execution; full browser-driven execution incomplete (Owner-accepted).
- **UAT-08:** API/CLI companion verifies reminder delivery/dedupe; full browser-driven verification not required (Owner-accepted).

## Migration considerations

- Adds `320001`, `320002`, `320003` (templates, snapshots/nullable source, reminder rules + composite FK).
- Run on disposable MySQL 8 before any production migration authorization.

## CI status

- Verified code SHA `e0743b3` CI **success** (run 42437115976).
- Tip docs SHA `707ca33` CI: **success** — run https://github.com/Abdulrahmanayman7/erp-hajj/actions/runs/42440689395

## Rollback considerations

- Feature-flagging is not used; rollback = revert merge commit / undeploy after separate ops act.
- Migrations are additive; down paths exist in migration files but production rollback needs separate Owner/ops authorization.

## Explicit deployment exclusion

- Merging this PR (if authorized) does **not** authorize production deployment, production DB access, or production migrations.
```

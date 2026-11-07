# S06 Part 1 — Implementation Evidence

> **Status:** `IMPLEMENTED — PENDING OWNER REVIEW`
> **Date:** 2026-11-07
> **Worktree:** `D:\erp-hajj-s06-season-projects` @ `feature/s06-season-projects` (`e71d7d9` + local Part 0/1 changes)
> **Does not:** claim Owner acceptance, Part 2 authorization, commit/push/merge/deploy

## Scope delivered

- Migrations `2026_11_07_300001`…`300005`
- Module `App\Modules\SeasonProjects` (enums, models, number generator, domain exception)
- Nullable Task extensions + `TaskDelayState`
- Factories: SeasonProject / Phase / BudgetPoLink
- Feature tests: `tests/Feature/SeasonProjects/SeasonProjectPart1SchemaTest.php`
- No public `season-project` routes
- No activation/completion/restore APIs
- No E-Q07–09 financial formulas

## Tests executed

| Suite | DB | Result |
|---|---|---|
| `SeasonProjectPart1SchemaTest` | sqlite `:memory:` (phpunit.xml forced) | **9 passed**, 1 skipped (rollback MySQL-only) |
| `TaskTest` (MVP regression) | sqlite `:memory:` | **15 passed** |
| MySQL 8 `erp_hajj_testing` via `phpunit.mysql.xml` | — | **NOT RUN** — MySQL/Docker unavailable in this environment |
| MariaDB | — | **NOT RUN** — same |

## Engineering notes

- Sequence column: `next_value` (repo convention)
- Project numbers unique including soft-deleted rows (never reused)
- Soft-delete: draft only; blocked with tasks or PO links
- SQLite cannot drop FK-backed task columns by name → rollback covered for MySQL path in migration `down()`; sqlite test skipped

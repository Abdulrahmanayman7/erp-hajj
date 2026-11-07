# Risks & Complaints — Test Plan (Executed through Part 6)

> **Status:** PART 6 — suites executed on SQLite `:memory:` / Vitest
> Aligns with [TESTING_STRATEGY.md](../../07-testing/TESTING_STRATEGY.md)
> **MySQL 8:** not claimed from these results.

## 1. Backend suites

| File | Focus |
|---|---|
| `RisksAndComplaintsPart1SchemaTest` | Tables, FKs, numbering, tenant fail-closed |
| `RiskApiPart2Test` | Risks CRUD/lifecycle/RBAC/isolation/SoD |
| `ComplaintApiPart3Test` | Complaints lifecycle/escalate dual-path/sensitive |
| `RisksComplaintsNotificationsPart4Test` | Recipients, `occ:{id}` dedupe, rollback, concurrency |
| `RisksComplaintsPart6GapClosureTest` | Timeline, capabilities, escalate paths, UAT scenarios |

## 2. Coverage checklist

| Area | Status |
|---|---|
| Endpoint 401/403/404/409/422 | Covered |
| Cross-tenant / cross-season | Covered |
| Risk matrix & lifecycle | Covered |
| Creator self-approval ban | Covered |
| Sensitive explicit-only | Covered |
| Escalate assignee path + escalate permission path | Covered |
| Optional links validation | Covered |
| Project unavailable without S06 | Covered |
| Audit writes + timeline read | Covered |
| Notification recipients | Covered |
| Occurrence retry dedupe | Covered |
| New occurrence → new notification | Covered |
| Transaction rollback of occurrence | Covered |

## 3. Frontend (Vitest)

| Area | Status |
|---|---|
| API season headers (risks/complaints/timeline/capabilities) | Covered |
| Sensitive close/reopen visibility | Covered |
| Assignee escalate without escalate permission | Covered |
| Timeline panel load/empty | Covered |
| Project unavailable field | Covered |
| Risk/complaint validation | Covered |
| Lifecycle action visibility | Covered |

## 4. Environments

- Local / CI Part 1–6: **SQLite `:memory:` only**.
- No shared MariaDB / production access.
- MySQL 8 dialect validation: **deferred to Staging**.

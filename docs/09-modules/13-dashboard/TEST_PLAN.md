# Dashboard — Test Plan

> **Status:** Specification complete — implementation pending (Sprint 017)
> **Last updated:** 2026-08-12
> Tooling: **Pest** (backend) · **Vitest** (frontend)
> No executable tests in this documentation sprint.

Failing security / cross-tenant / permission-omit tests block merge at implementation.

---

## Backend (Pest)

### TENANCY

- [ ] All KPI counts for tenant A exclude tenant B fixtures
- [ ] Attention / today lists never include foreign tenant entities
- [ ] Cross-tenant entity ids cannot appear in `href` payloads from A’s dashboard
- [ ] Request body/query `tenant_id` ignored (no effect)

### AUTHORIZATION

- [ ] Missing `dashboard.view` → 403
- [ ] Unauthenticated → 401
- [ ] User without `contracts.view` → response has no `contracts_*` KPI keys and no contract Attention items
- [ ] Same for tasks / meetings / decisions / inventory / assets
- [ ] User with only `dashboard.view` → empty `kpis` (except possibly notifications) + empty attention/today/work/resources modules
- [ ] No role-name branch coverage required (capability only)

### SELF-SERVICE / SCOPE

- [ ] `work.my_tasks` only when linked Employee exists
- [ ] `my_tasks` counts only assignee’s Tasks
- [ ] `my_custodies` only holder’s active custodies
- [ ] Holding assignee self-service semantics must not inflate tenant-wide KPIs beyond Policy list scope

### TASKS

- [ ] `tasks_open` matches open statuses
- [ ] `tasks_overdue` matches derived overdue
- [ ] `tasks_due_soon` uses Notifications due-soon days config
- [ ] `tasks_due_today` list only today’s due_date

### CONTRACTS

- [ ] `contracts_executing` / `expired` / `expiring_soon` match Contracts module rules + `expiring_soon_days`

### MEETINGS

- [ ] `meetings_today` uses tenant timezone calendar date
- [ ] `meetings_in_progress` correct
- [ ] Starting-soon Attention uses `meeting_starting_soon_minutes`
- [ ] Upcoming 7d list capped and horizon-correct

### DECISIONS

- [ ] `decisions_pending_approval` count
- [ ] `decisions_approved_open` count
- [ ] `decisions_with_open_tasks` requires both decisions.view + tasks.view; correct EXISTS semantics

### INVENTORY

- [ ] `inventory_low` / `inventory_out` count **balance rows**
- [ ] `inventory_attention` = low + out counts
- [ ] **No** `SUM(on_hand)` field exists in payload
- [ ] StockStateResolver parity with Inventory module

### ASSETS / CUSTODIES

- [ ] available / in_use / maintenance counts
- [ ] overdue / due-soon custody counts
- [ ] retired/lost not required in primary KPIs

### NOTIFICATIONS

- [ ] `unread_count` equals recipient unread COUNT
- [ ] Never returns another User’s unread total

### PERFORMANCE

- [ ] No N+1 on dashboard Action (assert query count ceiling practical for seeded fixture)
- [ ] Lists respect max caps (attention ≤15, today lists ≤8)

### SECURITY

- [ ] `href` values are allow-listed relative paths only
- [ ] No secrets / national ids in payload
- [ ] Fail closed on forced exception inside aggregator (500 envelope; no partial leak of other tenant)

### REGRESSION

- [ ] Existing module suites remain green after Dashboard wiring

---

## Frontend (Vitest)

- [ ] KPI cards render for present keys only
- [ ] Hidden modules: absent keys → no card
- [ ] Attention list rendering + severity classes
- [ ] Empty states copy
- [ ] Loading skeleton / error + retry
- [ ] Deep-link navigation uses `href`
- [ ] Mobile single-column class behavior (smoke)
- [ ] RTL logical classes present on layout root
- [ ] API client parses contract shape
- [ ] Refresh invalidates query
- [ ] Route meta requires `dashboard.view`
- [ ] No chart component imported

---

## Explicitly deferred

- E2E Playwright
- Cache invalidation tests (no cache)
- Audit widget tests

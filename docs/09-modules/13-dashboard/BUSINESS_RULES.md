# Dashboard — Business Rules

> **Status:** Implemented (Sprint 017)
> **Last updated:** 2026-08-12
> ADR: [ADR-0014](../../10-decisions/ADR-0014-PERMISSION-AWARE-DASHBOARD-AGGREGATION.md)

Dashboard is a **permission-aware read model**. It aggregates operational indicators from implemented modules. It must not invent domain rules that contradict owning modules.

---

## 1. Non-negotiable principles

1. **Read-only.** No Dashboard endpoint mutates Contracts, Tasks, Decisions, Meetings, Inventory, Assets, Documents, Users, or Notifications (except navigating away to those modules).
2. **Tenant-scoped.** All aggregates use `TenantContext` / `UsesTenantScope`. Never accept `tenant_id` from the client.
3. **Capability-driven.** No role-name checks (`tenant_owner`, `general_manager`, …). Use `dashboard.view` + module `*.view` (and Notifications recipient ownership).
4. **Backend authoritative.** Frontend hiding is UX-only; unauthorized section data must not be returned.
5. **Reuse domain semantics.** Overdue, expiring-soon, stock states, custody overdue, open Tasks — identical definitions to owning modules / Notifications scanners.
6. **Attention ≠ Notifications feed.** Attention items are computed from business SoRs. Do not build Attention by scanning `notifications` rows.

---

## 2. Access model

### 2.1 Page gate

- Authenticated + active tenant User + **`dashboard.view`** → may call `GET /api/v1/dashboard` and open `/app`.
- Missing `dashboard.view` → **403** `AUTHORIZATION_DENIED` (API) / app **403** page (UI). Do not invent a second home with fake KPIs.

### 2.2 Section gate

| Section key | Required capability |
|---|---|
| `kpis.tasks_*`, `attention` task items, `work.tasks`, `today.tasks` | `tasks.view` |
| `kpis.contracts_*`, contract attention/today | `contracts.view` |
| `kpis.meetings_*`, meeting attention/today | `meetings.view` |
| `kpis.decisions_*`, decision attention/work | `decisions.view` |
| `kpis.inventory_*`, inventory attention/resources | `inventory.view` |
| `kpis.assets_*`, `kpis.custodies_*`, custody attention/resources | `assets.view` |
| `notifications.unread_count` | Authenticated recipient (Notifications inbox rules — **no** `notifications.*` permission) |

If a capability is missing → **omit** that section/key entirely from the JSON (see [API.md](API.md)). Do not return zeros for inaccessible modules (zeros leak existence of operational pressure).

### 2.3 Audience

Prefer **permission-aware global tenant dashboard** (option A), not role-based layout presets (option B). Different Users see different sections because they hold different capabilities — not because the server switched a “GM template”.

---

## 3. Aggregation scope & self-service safety

### 3.1 Tenant-wide vs personal

| Mode | When | What |
|---|---|---|
| **Tenant-wide** | Actor holds the section’s `*.view` and the module’s **list Policy** for that actor is tenant-wide (current MVP for Tasks/Contracts/Meetings/Decisions/Inventory/Assets list with `*.view`) | KPIs + Attention + Today lists use tenant aggregates |
| **Personal overlay** | Actor has linked `Employee` (`/me` employee_id) **and** holds the relevant `*.view` | Optional blocks `my_tasks` / `my_custodies` scoped **only** to that Employee |

### 3.2 Forbidden

- Never use assignee/holder **self-service** alone to justify **tenant-wide** counts.
- Never expand personal “مهامي” into all-tenant open Tasks.
- If a future Change Request narrows list Policies to org-scoped rows, Dashboard aggregators **must** apply the same scope as `List*` Actions (single source of visibility).

### 3.3 Read-only role

User with **only** `dashboard.view` receives:

```json
{ "meta": { ... }, "kpis": {}, "attention": [], "today": {}, "work": {}, "resources": {}, "notifications": { "unread_count": N } }
```

(`notifications` still allowed — recipient-owned.) Empty UI explains that no operational modules are granted.

---

## 4. Time & timezone

1. Tenant timezone = `tenants.timezone` (editable via System Settings when implemented — [15-system-settings/](../15-system-settings/); ADR-0016). Fallback app default `Asia/Riyadh` only if blank.
2. **`today`** = calendar date in tenant timezone.
3. Contract/Task date comparisons use tenant “today” (same as Contracts expire / Tasks overdue / Notifications scanners).
4. Meeting `scheduled_at` stored UTC; “today” / “next 7 days” / “starting soon” convert using tenant timezone.
5. Custody `expected_return_at` compared to `now(tenant tz)` (same as Assets overdue filter / Notifications).

### Horizons (locked)

| Concept | Window |
|---|---|
| Today | Tenant calendar today |
| Task due soon | `[today, today + D]` where `D = config('notifications.task_due_soon_days', 3)` (same as Notifications) |
| Contract expiring soon | `executing` + `end_date` in `[today, today + N]` where `N = config('contracts.expiring_soon_days', 30)` |
| Custody expected return soon | active + `expected_return_at` in `(now, now + E days]` where `E = config('notifications.custody_expected_return_soon_days', 3)` |
| Meetings starting soon (Attention) | `scheduled` + `scheduled_at` in `(now, now + W minutes]` where `W = config('notifications.meeting_starting_soon_minutes', 60)` |
| Meetings upcoming list | `scheduled` + `scheduled_at` in `(now, end of today+7d]` (tenant tz) — **not** vague “upcoming forever” |

Do **not** invent a second expiry/due threshold for Dashboard.

---

## 5. KPI catalog (approved)

Top row shows **up to 6** KPI cards among those the viewer may see (order fixed; hide missing permissions — do not reorder by role).

| KPI key | Arabic label | Definition | Deep-link |
|---|---|---|---|
| `tasks_overdue` | المهام المتأخرة | Open Tasks with `due_date < today` | `/app/tasks?overdue=1` |
| `decisions_pending_approval` | قرارات بانتظار الموافقة | `status = pending_approval` | `/app/decisions?status=pending_approval` |
| `contracts_expiring_soon` | عقود تنتهي قريبًا | Expiring-soon definition §4 | `/app/contracts?expiring_soon=1` |
| `inventory_attention` | تنبيهات المخزون | Count of **balance rows** with derived state ∈ {`low`,`out_of_stock`} | `/app/inventory?stock_state=low` (UI may also offer out filter) |
| `custodies_overdue` | عُهد متأخرة | Active custodies with `expected_return_at < now` | `/app/assets` or custodies list `?overdue=1` (use existing Assets filters) |
| `meetings_today` | اجتماعات اليوم | `scheduled`\|`in_progress` with `scheduled_at` date = today | `/app/meetings` with today filter if supported; else date_from/date_to = today |

### Additional KPI values returned in payload (not all forced into top row)

| Key | Label | Definition | Permission |
|---|---|---|---|
| `tasks_open` | المهام المفتوحة | status ∈ {`draft`,`assigned`,`in_progress`} | `tasks.view` |
| `tasks_due_soon` | مهام مستحقة قريبًا | open + due in due-soon window **and not** already overdue | `tasks.view` |
| `contracts_executing` | عقود قيد التنفيذ | `status = executing` | `contracts.view` |
| `contracts_expired` | عقود منتهية | `status = expired` | `contracts.view` |
| `meetings_in_progress` | اجتماعات جارية | `status = in_progress` | `meetings.view` |
| `decisions_approved_open` | قرارات معتمدة (مفتوحة) | `status = approved` | `decisions.view` |
| `decisions_with_open_tasks` | قرارات بمهام مفتوحة | approved Decisions with ≥1 Task in open statuses | `decisions.view` **and** counted via Tasks under tenant scope (requires `tasks.view` to include this KPI — if missing `tasks.view`, omit this key) |
| `inventory_low` | أرصدة منخفضة | balance rows with state `low` | `inventory.view` |
| `inventory_out` | أرصدة نافدة | balance rows with state `out_of_stock` | `inventory.view` |
| `assets_available` | أصول متاحة | `status = available` | `assets.view` |
| `assets_in_use` | أصول قيد الاستخدام | `status = in_use` | `assets.view` |
| `assets_maintenance` | أصول في الصيانة | `status = maintenance` | `assets.view` |
| `custodies_due_soon` | عُهد يقترب موعد إرجاعها | soon window §4 | `assets.view` |

### Explicitly rejected KPIs

- Total stock quantity / sum of `on_hand` across items (cross-UOM).
- Total documents, active users, employee headcount, org unit count (vanity / HR analytics).
- Contract SAR totals as “finance”.
- Retired/lost asset counts as primary operational cards.
- Fake “decision progress %”.
- Recent audit activity (Audit viewer not shipped).

---

## 6. Cross-UOM safety (Inventory)

1. Inventory indicators are **counts of balance rows** (or distinct items/warehouses if a future CR adds them) — **never** `SUM(on_hand)`.
2. Use `inventory_balances` + `StockStateResolver` (same as Inventory UI / Notifications `STOCK_BELOW_MINIMUM`).
3. Do not aggregate from movement ledger history for Dashboard KPIs.

---

## 7. Attention Center — يحتاج انتباهك

Aggregated operational problems (not a notification list). Return a **prioritized list** (max **15** items) of typed summaries.

### Priority (lower number = higher urgency)

| Priority | Type | Severity | Source rule |
|---|---|---|---|
| 1 | `TASK_OVERDUE` | critical | Open + overdue |
| 2 | `CUSTODY_OVERDUE` | critical | Active + overdue |
| 3 | `CONTRACT_EXPIRED` | critical | `status = expired` (recent/relevant — include all expired or cap by newest 5 in list builder) |
| 4 | `INVENTORY_OUT` | critical | Balance `out_of_stock` |
| 5 | `DECISION_PENDING_APPROVAL` | warning | `pending_approval` |
| 6 | `CONTRACT_EXPIRING_SOON` | warning | Expiring soon |
| 7 | `TASK_DUE_SOON` | warning | Due soon (not overdue) |
| 8 | `CUSTODY_DUE_SOON` | warning | Due soon |
| 9 | `INVENTORY_LOW` | warning | Balance `low` |
| 10 | `MEETING_STARTING_SOON` | warning | Starting-soon window |
| 11 | `MEETING_TODAY` | info | Today (if not already starting-soon) |
| 12 | `MEETING_IN_PROGRESS` | info | In progress |

### Item shape (conceptual)

- `type`, `severity`, `title`, `subtitle` (plain text), `count` **or** single entity ref (`entity_type`,`entity_id`), `deep_link` path+query built server-side from **allow-listed** routes (never arbitrary URLs from DB).

### Ranking within same priority

Newest / soonest first (e.g. soonest `due_date` / `end_date` / `scheduled_at` / `expected_return_at`).

### Deduping Attention

- Prefer **grouped summaries** when count > 3 for the same type (e.g. “5 مهام متأخرة”) linking to filtered list — avoid dumping 50 row clones.
- Single-entity items allowed when count is small (≤3) for actionable clarity.

Only include Attention types whose section permission is present.

---

## 8. Today / Soon section

| Block | Content | Horizon |
|---|---|---|
| `meetings_today` | Compact list (max 8) | Today |
| `tasks_due_today` | Open Tasks with `due_date = today` (max 8) | Today |
| `contracts_expiring` | Expiring-soon contracts (max 8) | Config N days |
| `meetings_upcoming_7d` | Scheduled meetings in next 7 days excluding those already listed as “today” if desired (max 8) | 7 days |

Empty arrays allowed with UI empty copy.

---

## 9. Domain metric definitions (binding)

### 9.1 Tasks

- **Open** = `draft` \| `assigned` \| `in_progress` (same as `TaskStatus::openStatuses()`).
- **Overdue** = open AND `due_date` not null AND `due_date < today(tenant)`.
- Terminal `completed` / `cancelled` never overdue.
- **Due today** = open AND `due_date = today`.
- **Due soon** = open AND `due_date` in `[today, today+D]` AND not overdue… wait overdue is `< today`, so due soon includes today. Lock: due soon = open + due in `[today, today+D]` (includes today); KPI `tasks_due_soon` may still be shown separately from overdue. Overdue KPI excludes due-soon window by definition (`due_date < today`).
- **My tasks** (optional): open Tasks where `assigned_to_employee_id = actor.employee_id`.

### 9.2 Contracts

- **Executing** = `status = executing`.
- **Expiring soon** = executing + end_date in window (Contracts + Notifications).
- **Expired** = `status = expired`.
- Do not invent “active” as a separate status — use `executing` for “current operational contracts”.

### 9.3 Meetings

- **Today** = (`scheduled` \| `in_progress`) AND calendar date of `scheduled_at` = today.
- **In progress** = `status = in_progress`.
- **Starting soon** = `scheduled` AND within W minutes.
- **Upcoming 7d** = `scheduled` AND `scheduled_at` within next 7 days from now.

### 9.4 Decisions

- **Pending approval** = `pending_approval`.
- **Approved open** = `approved` (not closed).
- **With open tasks** = approved AND exists Task with `decision_id` in open statuses.
- Pending KPI is visible with `decisions.view` (same as list). Approving remains gated by `decisions.approve` on the Decisions module — Dashboard only counts.

### 9.5 Inventory

- Derive state per balance row via StockStateResolver.
- `inventory_low` / `inventory_out` / `inventory_attention` (= low + out counts or single combined card using sum of those **counts**, not quantities).

### 9.6 Assets / Custodies

- Asset status counts: `available`, `in_use`, `maintenance` only for primary metrics.
- Do not promote `retired` / `lost` / `damaged` into top KPIs (may appear later via CR).
- Custody overdue / due soon: active + `expected_return_at` rules aligned with Assets API `overdue` filter + Notifications.
- **My custodies** optional: active custodies for linked Employee.

---

## 10. Notifications integration

- Include `notifications.unread_count` via indexed COUNT (`read_at IS NULL`, recipient = actor) — same as Notifications API.
- Do **not** embed full recent notification list on Dashboard (bell owns that UX).
- Optional header chip linking to `/app/notifications`.

---

## 11. Documents / Employees / Finance / Charts

| Topic | Decision |
|---|---|
| Documents | **No** Dashboard KPI in Sprint 017 |
| Employees / Org units | **No** headcount widgets |
| Financial metrics | **Forbidden** |
| Charts | **None** — cards + lists only |

---

## 12. Freshness & caching

- Data computed **on request** (no Dashboard cache in MVP).
- Do not label the page “مباشر / live”.
- Client may offer manual refresh; TanStack Query default refetch-on-focus is acceptable; no 60s Dashboard poller required (Notifications keep their own poller).

---

## 13. Filters

- **No** Dashboard query filters in MVP (`organization_unit_id`, warehouse, employee, date range).
- Filtering happens after deep-link into module lists.

---

## 14. Error & audit

- Unexpected aggregation failure → fail the **whole** `GET /dashboard` with secure envelope (simplicity). Permission omissions are not errors.
- Routine Dashboard views are **not** audited.
- Correlation ID still propagates on the request per platform rules.

---

## 15. Performance rules

1. Prefer `COUNT(*)` / grouped counts / `EXISTS`.
2. Use tenant-leading indexes already defined on source tables.
3. Cap Attention/Today lists (max sizes above); never load full tables into memory.
4. No N+1; no movement ledger scans for stock KPIs.
5. Target: small constant number of aggregate queries per allowed section (implementers may batch).

---

## 16. Threat model (summary)

| Threat | Control |
|---|---|
| Cross-tenant aggregates | Tenant scope / context require |
| Module count leakage | Omit sections without `*.view` |
| Self-service → tenant-wide | §3 rules; personal overlays only |
| Forged filters | No filter inputs in MVP |
| Expensive DoS | Caps + COUNT/EXISTS + indexes; rate limits inherit auth |
| Cache key collision | No Dashboard cache MVP |
| Open redirects | Server allow-listed deep_link builders |
| Sensitive PII in lists | Compact labels only (numbers/titles); no national IDs |
| Pending decision leakage | Requires `decisions.view` |

Full matrix in [TEST_PLAN.md](TEST_PLAN.md).

---

## 17. TBD (non-blocking)

| Item | Note |
|---|---|
| Exact Assets UI query string for overdue custodies | Align with implemented Assets list filters at coding time |
| Whether `inventory_attention` KPI shows combined or only `low` | Recommendation: combined count with subtitle breaking low/out in resources section |
| Alias route `/app/dashboard` | Recommended redirect; confirm in router PR |

No blocking business TBD remains for Sprint 017 implementation start.

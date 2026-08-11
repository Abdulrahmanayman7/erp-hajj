# Notifications — Business Rules

> **Status:** Specification complete — implementation pending (Sprint 016)
> **Last updated:** 2026-08-11
> ADR: [ADR-0013](../../10-decisions/ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md)

## 1. What is a Notification?

An **in-app Notification** is a persisted, tenant-owned attention record addressed to a single **User**, produced by a documented domain rule (immediate lifecycle hook or scheduled scanner). It carries a stable `type`, plain-text Arabic `title`/`body`, optional entity pointer, optional severity, and read state (`read_at`).

It is **not**:
- an audit log row
- a chat/message thread
- an email/SMS payload (MVP)
- editable content after creation (except `read_at` / `updated_at`)

## 2. Recipient model (binding)

1. Recipient is always **`recipient_user_id` → `users.id`** (same tenant).
2. **Never** address an Employee as the notification recipient.
3. When a business event targets an Employee:
   - resolve `employees.user_id` under tenant scope;
   - if **null** → **do not create** an in-app notification for that person (no User auto-create);
   - optionally still notify other documented Users for that type (e.g. `created_by`).
4. Disabled / inactive Users (`users` disabled path from RBAC): **skip** creation; do not deliver to locked-out actors.
5. Platform users are not recipients of tenant business notifications.

## 3. Tenancy (binding)

1. Every notification row has `tenant_id` and uses `TenantOwned` + `UsesTenantScope`.
2. Recipient User must belong to the same tenant.
3. Referenced entity (when set) must belong to the same tenant at creation time.
4. `tenant_id` is never accepted from clients.
5. Cross-tenant list/show/read → **404** (no leakage).
6. Aligns with [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md) §12.

## 4. Persistence and immutability

1. Notifications are **persisted** in MySQL (`notifications` table) — SoR for the inbox.
2. After insert: **immutable** fields except `read_at` (and framework `updated_at`).
3. No PATCH of title/body/type/entity.
4. No user hard-delete in MVP.
5. Future retention/pruning job may hard-delete aged rows (separate from Audit Trail retention) — **not** in Sprint 016 implementation scope beyond documenting the policy.

### Retention (MVP policy)

| Item | Decision |
|---|---|
| User delete | **Forbidden** |
| Mark unread | **Forbidden** |
| Pruning | Deferred job; recommended future window **180 days** after `created_at` (TBD confirm at ops) |
| Audit coupling | None — pruning notifications does not prune audit |

## 5. Read / unread

| State | Rule |
|---|---|
| Unread | `read_at IS NULL` |
| Read | `read_at` set to server now |
| Mark one | Recipient-owned POST …/read; idempotent if already read |
| Mark all | Recipient-owned POST …/read-all; sets `read_at` on all unread for that User in tenant |
| Mark unread | **Out of MVP** |

## 6. Source architecture (binding)

```text
Domain Action / Scheduler rule
  → NotificationDispatcher (Notifications module)
      → NotificationRule for `type`
          → resolve recipients (Users)
          → compose plain-text title/body (server, Arabic)
          → compute dedupe_key (if applicable)
          → insert (ignore duplicate on unique conflict)
  → (future) DeliveryAdapterInterface::send(inAppAlreadyPersisted)
```

Rules:

1. Controllers / Vue must **not** invent notification rows.
2. Domain modules call a thin Notifications facade/dispatcher **or** emit a dedicated `Notify*` domain event handled inside Notifications — prefer **dispatcher invoked from Actions after successful commit** (or queued job enqueued after commit).
3. **Do not** reuse `AuthorizationSecurityEvent` as the notification bus. Audit remains separate; Actions may emit both audit and notification independently.
4. Payload must not include secrets, password hashes, tokens, or full entity dumps.

### Sync vs queue

| Path | Decision |
|---|---|
| Immediate lifecycle (assign, approve, schedule meeting, custody assign, …) | **Persist synchronously** after successful business commit when cheap (single/few recipients). Failures are **caught and logged** — **must not** roll back the business action. |
| Optional deferral | May enqueue `DispatchNotificationJob` after commit if fanout > N (config; recommended N=20) or for capability fanout. |
| Scheduled scanners (expiring/due/overdue/starting soon/low stock) | **Always queued/scheduled** commands iterating tenants via `runAsTenant()`. |
| Future email/SMS/push | **Always queued**; adapters consume already-persisted in-app rows or parallel channel records (future schema). |

## 7. Dedupe / idempotency

Scheduled and repeatable rules **must not** flood the inbox.

### Strategy

1. Column `dedupe_key` (nullable string).
2. Unique constraint: **`UNIQUE (tenant_id, dedupe_key)`** (MySQL allows multiple NULLs — use NULL only when dedupe not required).
3. Format (stable):

```text
{TYPE}:{entity_type}:{entity_id}:{recipient_user_id}:{bucket}
```

| Type class | Bucket |
|---|---|
| One-shot lifecycle (e.g. `TASK_ASSIGNED`) | Event occurrence id or `transition:{id}` / `custody:{id}` / `assign:{history_id}` so reassign gets a new key |
| Daily scheduled (`*_OVERDUE`, `*_EXPIRING_SOON`, `*_DUE_SOON`, `CUSTODY_*`) | `Y-m-d` in **tenant timezone** |
| Meeting starting soon | `Y-m-d-H` (hour bucket) or meeting id + date — prefer `{meeting_id}:{Y-m-d}` once per day while still soon |

4. Insert uses “create or ignore” on unique conflict (no update of existing unread/read row).
5. When the condition clears (e.g. no longer overdue), **stop generating**; do not auto-delete prior notifications.

## 8. Entity reference and deep-links

1. Store `entity_type` as **Documents-style morph alias** (never PHP FQCN): e.g. `contract`, `meeting`, `decision`, `task`, `asset`, `custody`, `inventory_item`, `warehouse`.
2. Store `entity_id` nullable bigint.
3. **Do not store `action_url`** in MVP (prevents open redirects / forged external URLs).
4. Frontend resolves route from `(entity_type, entity_id)` via a shared map (same spirit as Documents linkable routes).
5. List/show may still return the notification if the entity was later deleted or access revoked; deep-link navigation must fail **safely** (404 / 403 page) without leaking foreign data.
6. Title/body are **snapshots** composed at creation — safe summary only (numbers + short names already visible to recipient role context). Do not embed confidential free-text beyond what the recipient’s role would already see for that event.

## 9. Severity

MVP uses a small enum for UX (badge/icon), not scoring:

| Value | Use |
|---|---|
| `info` | Assignments, schedules, completions |
| `warning` | Due soon, expiring soon, expected return soon, low stock |
| `critical` | Overdue, expired, cancelled meeting (disruptive) |

## 10. Content safety

1. Plain text only — **no HTML**, no Markdown rendering as HTML.
2. Escape on output; strip control characters on write.
3. Length caps: title ≤ 255; body ≤ 1000.
4. Localization: Arabic-first server strings for MVP (tenant `locale` future).

## 11. Approved MVP type catalog

Only these types are in MVP. No speculative extras.

### Contracts

| Type | Trigger | Recipients | Severity | Dedupe |
|---|---|---|---|---|
| `CONTRACT_EXPIRING_SOON` | Daily job: `executing` + `end_date` in `[today, today+N]` (N = `config('contracts.expiring_soon_days')`, default **30**) | `created_by` User; **plus** linked `employee.user_id` if present | warning | daily bucket |
| `CONTRACT_EXPIRED` | After successful scheduler transition to `expired` | same as above | critical | one-shot `contract:{id}:expired` |

Stops: not `executing` / already `expired` respectively.

### Meetings

| Type | Trigger | Recipients | Severity | Dedupe |
|---|---|---|---|---|
| `MEETING_SCHEDULED` | Schedule action | Attendee Employees with `user_id`; + meeting `created_by` if not already included | info | one-shot `meeting:{id}:scheduled` |
| `MEETING_RESCHEDULED` | Reschedule | same | warning | `meeting:{id}:rescheduled:{scheduled_at_iso}` |
| `MEETING_CANCELLED` | Cancel | same | critical | one-shot `meeting:{id}:cancelled` |
| `MEETING_STARTING_SOON` | Job: status `scheduled` and `scheduled_at` within **W** minutes (config default **60**) of now | attendee Users | warning | daily bucket per meeting |

### Decisions

| Type | Trigger | Recipients | Severity | Dedupe |
|---|---|---|---|---|
| `DECISION_SUBMITTED` | Submit → `pending_approval` | Users in tenant who hold **`decisions.approve`** (capability fanout; chunked; exclude actor) | warning | one-shot `decision:{id}:submitted` |
| `DECISION_APPROVED` | Approve | `created_by`; issuer Employee User; responsible Employee User (dedupe set) | info | one-shot |
| `DECISION_RETURNED_TO_DRAFT` | Return | `created_by` | warning | one-shot per transition id |
| `DECISION_CLOSED` | Close | `created_by`; responsible Employee User if linked | info | one-shot |
| `DECISION_CANCELLED` | Cancel | `created_by` | info | one-shot |

**Fanout note:** `DECISION_SUBMITTED` is the only permission-based fanout in MVP. Query effective permission holders **within tenant** only; never Owners-by-role-name; never cross-tenant.

### Tasks

| Type | Trigger | Recipients | Severity | Dedupe |
|---|---|---|---|---|
| `TASK_ASSIGNED` | First assign | Assignee Employee’s User if linked | info | `task:{id}:assign:{assignment_history_id}` |
| `TASK_REASSIGNED` | Reassign | **New** assignee User if linked | info | same pattern |
| `TASK_COMPLETED` | Complete | `created_by` if ≠ completing actor | info | one-shot |
| `TASK_DUE_SOON` | Job: non-terminal + `due_date` in `[today, today+D]` (D default **3**) | assignee User if linked | warning | daily |
| `TASK_OVERDUE` | Job: derived overdue true | assignee User if linked | critical | daily |

Not in MVP delivery (hooks remain unused): `TASK_CREATED`, `TASK_STARTED`, `TASK_PROGRESS_UPDATED`, `TASK_CANCELLED` (noise / covered by list UX).

### Assets / Custodies

| Type | Trigger | Recipients | Severity | Dedupe |
|---|---|---|---|---|
| `CUSTODY_ASSIGNED` | Assign custody | Holder Employee’s User if linked | info | `custody:{id}:assigned` |
| `CUSTODY_RETURNED` | Return custody | Original `assigned_by` User (if still active) | info | `custody:{id}:returned` |
| `CUSTODY_EXPECTED_RETURN_SOON` | Job: active custody + `expected_return_at` within **E** days (default **3**) | holder User | warning | daily |
| `CUSTODY_OVERDUE` | Job: active + `expected_return_at` < now | holder User | critical | daily |

No notifications for maintenance/retire/lost beyond what custody return/lost already implies unless added later by CR.

### Inventory

| Type | Trigger | Recipients | Severity | Dedupe |
|---|---|---|---|---|
| `STOCK_BELOW_MINIMUM` | After outbound movement leaving `stock_state` ∈ {`low`,`out_of_stock`} **or** daily scanner | Warehouse `responsible_employee` → User if linked; if none → **skip** (no GM flood) | warning | daily per `(warehouse_id, item_id, recipient)` |

## 12. Scheduled rules summary

| Job (illustrative name) | Cadence | Tenant loop |
|---|---|---|
| `notifications:scan-contracts` | Daily | `runAsTenant` active tenants |
| `notifications:scan-tasks` | Daily | same |
| `notifications:scan-custodies` | Daily | same |
| `notifications:scan-meetings-soon` | Hourly | same |
| `notifications:scan-low-stock` | Daily | same |

Timezone: each tenant’s `timezone` (default `Asia/Riyadh`). Suspended tenants: business jobs **release** (MULTI_TENANCY); archived: **cancel**.

## 13. Audit relationship

| Action | Audit? |
|---|---|
| Notification row created | **No** (volume); rely on domain audit of source action |
| Mark read / read-all | **No** |
| Admin exceptional access (future) | Yes, if ever added |

## 14. Disabled User / no Employee / no User link

| Case | Behavior |
|---|---|
| Employee without User | Skip that recipient; other documented recipients may still notify |
| User without Employee | Still receives User-addressed types (e.g. `created_by`, approvers) |
| Disabled User | Skip |
| Empty recipient set | No rows; business action succeeds |

## 15. Explicit TBDs (non-blocking for spec)

| Item | Note |
|---|---|
| Exact pruning days (180?) | Ops decision at implementation/settings |
| Email channel | Future; adapter interface reserved in ADR-0013 |
| Per-user preferences | Out of MVP |
| `DECISION_SUBMITTED` fanout volume | Monitor; may switch to explicit approver list via CR |

## 16. Threat model (controls)

| Threat | Control |
|---|---|
| IDOR other User’s notification | Policy: recipient_user_id === actor.id + tenant |
| Cross-tenant | Tenant scope + 404 |
| Client create | No create endpoint |
| Unsafe URL | No stored action_url |
| HTML injection | Plain text + escape |
| Scheduler flood | dedupe_key unique |
| Stale entity | Snapshot text; deep-link 404 |
| Tenant context leak on jobs | Job middleware restore/clear |
| Disabled user spam | Skip disabled |
| Broad Owner flood | Forbidden by default; only documented capability fanout |

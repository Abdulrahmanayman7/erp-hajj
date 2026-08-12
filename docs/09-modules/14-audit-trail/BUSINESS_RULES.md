# Audit Trail — Business Rules

> **Status:** Implemented (Sprint 018)
> **Last updated:** 2026-08-12
> ADR: [ADR-0015](../../10-decisions/ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md)

## 1. What an audit record is

An **audit record** is an immutable, append-only historical fact about a **semantic** security or domain operation. It must remain understandable if the referenced business entity is later updated or deleted.

It is **independent** of mutable Sources of Truth (contracts, tasks, assets, …). Domain transition tables (`*_status_transitions`, `inventory_movements`, …) remain authoritative for their own histories; audit is the **cross-cutting accountability** view.

## 2. Event ownership

1. Business / Auth / Tenancy modules **own** the semantic event (`TASK_ASSIGNED`, `LOGIN_SUCCESS`, …) and the safe context payload.
2. Audit Trail **owns** persistence, sanitization, indexing, query, and UI labels.
3. Controllers must **not** write free-form audit strings.
4. Audit Trail must **not** infer meaning from raw Eloquent dirty diffs / generic observers.

```text
Task Action
  → AuthorizationSecurityEvent::TASK_ASSIGNED + safe context
  → AuditRecorder persists one audit_logs row
```

## 3. Append-only immutability

- No application `UPDATE` / `DELETE` of audit rows for normal or admin Users.
- No soft-edit UI.
- No “correct history” mutation: if a bug produced a wrong event, fix the code; optionally emit a **new** compensating semantic event if the business requires it.
- DB grants / app code must not expose update/delete routes (`audit_logs` has no update/delete API).

## 4. What gets audited (MVP principles)

Audit **high-value mutations** and **security-sensitive** operations:

- Create / update / delete of governed entities
- Lifecycle transitions (contracts, meetings, decisions, tasks, assets, stock movements, tenant lifecycle)
- Approvals, assignments, returns, stock adjust/transfer
- Document upload / link / unlink / archive / restore / delete / **download**
- RBAC: roles, permissions sync, user role assignment, enable/disable
- Auth: login success, security-relevant failures, logout, password-reset request/complete
- Exceptional platform access into tenant data
- Tenant settings changes

### Explicitly NOT audited (MVP)

| Excluded | Reason |
|---|---|
| `GET /api/v1/dashboard` | Routine read (ADR-0014) |
| Normal list/show of business modules | Noise |
| Notification list / unread-count / mark-read | Volume; Notifications ≠ Audit (ADR-0013) |
| Every Gate/`can()` check | Noise |
| Health / CSRF cookie | Infrastructure |
| Creating a notification row | Delivery, not accountability SoR |

## 5. Audit vs Notifications

| | Audit Trail | Notifications |
|---|---|---|
| Purpose | Historical accountability | Recipient attention / delivery |
| Audience | Holders of `audit_logs.view` | Recipient User only |
| Retention | Indefinite (MVP) | May prune later (180d recommendation) |
| Example | `TASK_ASSIGNED` row | Inbox row to assignee User |

One Action may emit **both** independently. Reading a notification must **not** remove or alter audit rows. Creating an audit row must **not** auto-create notifications.

## 6. Actor model

| `actor_type` | Meaning | `actor_user_id` |
|---|---|---|
| `user` | Authenticated User performed the action | Set to that User |
| `system` | Scheduler, queue worker, console without impersonated User | `NULL` |
| `platform` | Platform operator acting in `PlatformContext` (may also set user id) | Platform User id when known |

Rules:

1. Never invent a fake tenant User for system jobs.
2. Snapshot `actor_label` at write time (display name and/or email) so deleted Users still render.
3. `actor_user_id` FK: **`nullOnDelete`** — history survives User deletion.
4. UI shows **النظام** for `system`; platform actors show snapshot label + clear platform context badge when `context_type = platform`.

## 7. Tenant ownership & context

| Field | Rule |
|---|---|
| `tenant_id` | **Nullable hybrid** (see DATABASE_PRINCIPLES): tenant actions → current tenant; platform events that **affect** a tenant → **target** tenant id; pure platform ops may be `NULL` |
| `context_type` | `tenant` \| `platform` |
| Client `tenant_id` | **Never** accepted from query/body/headers |

Viewing under tenant auth: only rows where `tenant_id = current tenant`. Cross-tenant detail → **404**.

Queue/scheduler: must restore `TenantContext` via existing job middleware before recording tenant events.

Platform exceptional access (`platform_tenants.access_data`): always write into the **target** tenant’s trail with reason + correlation ID.

## 8. Before / after snapshots

1. Store **only changed, allow-listed** attributes for update-style events.
2. Do **not** dump entire Eloquent models.
3. Lifecycle events may store `{ "from_status": "...", "to_status": "..." }` in metadata and/or before/after.
4. Quantity movements may store balance before/after (already present in inventory Actions) — still no storage paths/secrets.

## 9. Sensitive field exclusions (binding)

Never persist (strip/redact before write and before API response):

| Category | Examples |
|---|---|
| Auth secrets | `password`, password hashes, `remember_token`, reset tokens, session IDs, CSRF tokens |
| Tokens | Sanctum PAT values, API keys, Authorization headers, cookies |
| Document storage | `storage_path`, `storage_disk`, `stored_filename`, file bytes |
| Other | encrypted secrets, private keys, raw request bodies, credit-card-like payloads |

Implementation: central `SensitiveFieldSanitizer` allow-list + denylist. Prefer **allow-list per event** for before/after keys.

## 10. Metadata conventions

- Structured JSON object with **stable snake_case keys**.
- Prefer small scalars/ids over prose blobs.
- Practical max encoded size: **64 KiB** after sanitization; oversize → truncate non-essential keys or fail the write (fail closed inside domain transaction).
- Do not copy full Form Request payloads.

Example:

```json
{
  "from_status": "draft",
  "to_status": "approved",
  "reason": "موافقة الإدارة",
  "employee_id": 12
}
```

## 11. Entity references & snapshots

| Column | Purpose |
|---|---|
| `entity_type` | Stable morph-style **alias** (never PHP FQCN): `contract`, `task`, `document`, `user`, `role`, … |
| `entity_id` | Numeric id at event time (nullable for non-entity events) |
| `entity_number` | Business number snapshot (`CNT-000001`, `TSK-000002`, …) when applicable |
| `entity_label` | Short human label snapshot (title/name) |

Deleted / inaccessible entities: UI shows snapshot only; deep-link omitted.

## 12. Correlation

- Reuse existing `CorrelationId` middleware value (mandatory on HTTP).
- Propagate into job payloads (existing tenancy rules).
- Related rows (e.g. stock transfer out/in + `STOCK_TRANSFERRED`) share the same `correlation_id`; optional `metadata.transfer_group_id` when domain already has it.
- Correlation ID is **not** a secret and **not** an authorization input.

## 13. IP / User-Agent

| Source | Capture |
|---|---|
| HTTP user-driven | Server-side `$request->ip()` / `userAgent()` (nullable truncated) |
| Jobs / console | `NULL` unless a trusted Request is passed |

Limits: `ip_address` string(45); `user_agent` string(512) truncated.

## 14. Source

`source`: `http` | `console` | `job` | `scheduler` — server-derived, never client-trusted.

## 15. Transaction & failure semantics

| Path | Rule |
|---|---|
| Domain mutation inside `DB::transaction` | Audit insert **in the same transaction**. Rollback ⇒ no false success audit. Audit insert failure ⇒ transaction fails ⇒ business fails. |
| Auth security events | Persist after auth outcome is decided; **do not** roll back a successful login/logout/reset solely because audit insert failed — log critical and continue (availability exception, ADR-0015). |
| Notifications | Independent; notification failure must not block audit (and vice versa per ADR-0013). |

Unauthorized sections are not an audit concern. **Zero rows means no events matched filters** — never silently convert write failures into “no audit”.

## 16. Retention

**Indefinite during MVP.** No automatic deletion/pruning job in Sprint 018. Future archival requires CR + ADR. Distinct from Notifications retention.

## 17. Integrity / tamper evidence

MVP: append-only table + authorization + no update/delete API. **No** hash chaining / blockchain.

## 18. Generic model observers

**Forbidden.** Explicit semantic events only (ADR-0015).

## 19. Timezone

- Store `created_at` in UTC (Laravel default).
- Display in **tenant timezone** (existing tenant settings / project convention).

## 20. Dashboard relationship

Audit is **not** a Dashboard Attention source. No Sprint 018 Dashboard audit KPI. Optional future deep-link from Dashboard is out of this sprint.

## 21. Documents downloads

`DOCUMENT_DOWNLOADED` **is** in Audit Trail. Metadata: `document_id`, `document_number`, size, mime, link alias/id — **never** storage path or bytes.

## 22. Event catalog (canonical MVP)

Stable machine codes (DB/API). Arabic labels live in frontend i18n / display map — **not** as DB primary type.

### Authentication (`AuthSecurityEvent` — keep)

| Code | Notes |
|---|---|
| `LOGIN_SUCCESS` | Session established |
| `LOGIN_FAILED` | Security-relevant failure |
| `LOGOUT` | |
| `PASSWORD_RESET_REQUESTED` | No token |
| `PASSWORD_RESET_COMPLETED` | No password values |
| `ACCOUNT_DISABLED_ACCESS_ATTEMPT` | |
| `TENANT_BLOCKED_ACCESS_ATTEMPT` | Include status code |

### Users & RBAC (`AuthorizationSecurityEvent` — keep)

`USER_CREATED`, `USER_UPDATED`, `USER_ENABLED`, `USER_DISABLED`, `USER_ROLES_CHANGED`,
`ROLE_CREATED`, `ROLE_UPDATED`, `ROLE_ACTIVATED`, `ROLE_DEACTIVATED`, `ROLE_DELETED`, `ROLE_PERMISSIONS_CHANGED`,
`PERMISSION_CATALOG_SYNCED`

### Organization

`ORGANIZATION_UNIT_CREATED`, `ORGANIZATION_UNIT_UPDATED`, `ORGANIZATION_UNIT_MOVED`, `ORGANIZATION_UNIT_ACTIVATED`, `ORGANIZATION_UNIT_DEACTIVATED`, `ORGANIZATION_UNIT_DELETED`, `ORGANIZATION_MANAGER_ASSIGNED`

### Employees / Positions

`EMPLOYEE_CREATED`, `EMPLOYEE_UPDATED`, `EMPLOYEE_ACTIVATED`, `EMPLOYEE_DEACTIVATED`, `EMPLOYEE_SUPERVISOR_CHANGED`, `EMPLOYEE_ORGANIZATION_CHANGED`, `EMPLOYEE_USER_LINKED`, `EMPLOYEE_USER_UNLINKED`,
`POSITION_CREATED`, `POSITION_UPDATED`, `POSITION_DELETED`

### Contracts (+ categories)

`CONTRACT_CREATED`, `CONTRACT_UPDATED`, `CONTRACT_DELETED`, `CONTRACT_SUBMITTED_REVIEW`, `CONTRACT_RETURNED_DRAFT`, `CONTRACT_APPROVED`, `CONTRACT_SIGNED`, `CONTRACT_EXECUTED`, `CONTRACT_CLOSED`, `CONTRACT_CANCELLED`, `CONTRACT_RENEWED`, `CONTRACT_EXPIRED`,
`CONTRACT_CATEGORY_CREATED`, `CONTRACT_CATEGORY_UPDATED`, `CONTRACT_CATEGORY_ACTIVATED`, `CONTRACT_CATEGORY_DEACTIVATED`, `CONTRACT_CATEGORY_DELETED`

### Meetings

`MEETING_CREATED` … `MEETING_CANCELLED`, attendee/minutes/agenda/recommendation events — exact constants already in `AuthorizationSecurityEvent` (keep names).

### Decisions

`DECISION_CREATED`, `DECISION_UPDATED`, `DECISION_DELETED`, `DECISION_SUBMITTED`, `DECISION_RETURNED_TO_DRAFT`, `DECISION_APPROVED`, `DECISION_CANCELLED`, `DECISION_CLOSED`

### Tasks

`TASK_CREATED`, `TASK_UPDATED`, `TASK_ASSIGNED`, `TASK_REASSIGNED`, `TASK_STARTED`, `TASK_PROGRESS_UPDATED`, `TASK_COMPLETED`, `TASK_CANCELLED`, `TASK_DELETED`

### Documents

`DOCUMENT_UPLOADED`, `DOCUMENT_UPDATED`, `DOCUMENT_LINKED`, `DOCUMENT_UNLINKED`, `DOCUMENT_DOWNLOADED`, `DOCUMENT_ARCHIVED`, `DOCUMENT_RESTORED`, `DOCUMENT_DELETED`,
`DOCUMENT_CATEGORY_CREATED`, `DOCUMENT_CATEGORY_UPDATED`, `DOCUMENT_CATEGORY_DELETED`

### Warehouses / Inventory

`WAREHOUSE_*`, `INVENTORY_CATEGORY_*`, `INVENTORY_ITEM_*`, `STOCK_RECEIVED`, `STOCK_ISSUED`, `STOCK_RETURNED`, `STOCK_TRANSFERRED`, `STOCK_ADJUSTED`

### Assets / Custodies

`ASSET_CREATED`, `ASSET_UPDATED`, `ASSET_DELETED`, `ASSET_CATEGORY_*`, `ASSET_SENT_TO_MAINTENANCE`, `ASSET_RESTORED`, `ASSET_RETIRED`, `ASSET_DECLARED_LOST`, `ASSET_ASSIGNED`, `ASSET_RETURNED`

### Tenancy / platform (planned codes — add when platform Actions implement; do not duplicate)

| Code | Status |
|---|---|
| `TENANT_CREATED` | Planned |
| `TENANT_UPDATED` | Planned |
| `TENANT_ACTIVATED` | Planned |
| `TENANT_SUSPENDED` | Planned |
| `TENANT_REACTIVATED` | Planned |
| `TENANT_ARCHIVED` | Planned |
| `TENANT_SETTINGS_UPDATED` | Planned |
| `PLATFORM_TENANT_DATA_ACCESSED` | Planned (exceptional access) |
| `PLATFORM_CONTEXT_DENIED` | Planned |

### Notifications

| Code | Decision |
|---|---|
| Notification create / read | **Not audit-worthy** in MVP |

### Dashboard

| Code | Decision |
|---|---|
| Dashboard GET | **Not audit-worthy** |

### Classification legend

| Class | Meaning |
|---|---|
| **keep** | Existing constant — persist as-is |
| **planned** | Documented; add constant when Action ships |
| **defer** | Out of MVP |
| **not audit-worthy** | Explicitly excluded |

**No rename** of existing stable codes in Sprint 018. Temporary sinks (`AuthorizationSecurityEvent` / `AuthSecurityEvent`) remain the emission API; `AuditRecorder` consumes them.

## 23. Existing hook migration strategy

| Current | Future |
|---|---|
| `AuthorizationSecurity::record(...)` | Unchanged call sites; listener → `AuditRecorder` + keep `LogAuthorizationSecurityEvent` |
| `AuthSecurity::record(...)` | Unchanged call sites; listener → `AuditRecorder` + keep `LogAuthSecurityEvent` |
| Context keys `ip`, `user_agent`, `correlation_id` | Map into columns; remainder → `metadata` / before/after after sanitization |
| Duplicate log + audit | Allowed: logs ≠ audit table (ops vs accountability). **One** DB row per event |

Mechanical mapping: `event.name` → `event_type`; sanitize `context`; derive actor/tenant from `TenantContext` / `Auth::user()` / explicit context keys.

## 24. TBDs (non-blocking for Sprint 018 view slice)

- Export format for reserved `audit_logs.export` (CSV vs Excel vs queued file) — **deferred**.
- Legal archival / PDPL purge of audit rows — future ADR.
- Whether every platform `access_data` entity read vs session-start is audited — follow tenancy docs when platform endpoints land.

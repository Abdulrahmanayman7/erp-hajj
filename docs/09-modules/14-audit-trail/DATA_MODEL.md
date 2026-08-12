# Audit Trail — Data Model

> **Status:** Implemented (Sprint 018)
> **Last updated:** 2026-08-12
> ADR: [ADR-0015](../../10-decisions/ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md)

## 1. Classification

| Table | Class | Justification |
|---|---|---|
| `audit_logs` | **Hybrid** | Nullable `tenant_id`: tenant actions always set it; pure platform rows may be `NULL`; platform actions **affecting** a tenant write the **target** tenant id so they appear in that tenant’s viewer. Single table keeps one history stream (DATABASE_PRINCIPLES). |

No other Audit tables in MVP. **No** soft deletes. **No** `updated_at`.

## 2. Table: `audit_logs`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | no | PK |
| `tenant_id` | BIGINT UNSIGNED | **yes** | FK → `tenants.id` **ON DELETE RESTRICT**; null = non-tenant/platform-only row |
| `context_type` | VARCHAR(16) | no | `tenant` \| `platform` |
| `actor_type` | VARCHAR(16) | no | `user` \| `system` \| `platform` |
| `actor_user_id` | BIGINT UNSIGNED | yes | FK → `users.id` **ON DELETE SET NULL** |
| `actor_label` | VARCHAR(255) | yes | Snapshot (name/email); survives user delete |
| `event_type` | VARCHAR(64) | no | Stable code e.g. `TASK_ASSIGNED` |
| `entity_type` | VARCHAR(64) | yes | Morph alias, never FQCN |
| `entity_id` | BIGINT UNSIGNED | yes | No FK to polymorphic targets |
| `entity_number` | VARCHAR(64) | yes | Snapshot business number |
| `entity_label` | VARCHAR(255) | yes | Snapshot title/name |
| `reason` | VARCHAR(500) | yes | Privileged / transition reason when supplied |
| `metadata` | JSON | yes | Structured safe extras |
| `before_values` | JSON | yes | Allow-listed prior attributes |
| `after_values` | JSON | yes | Allow-listed new attributes |
| `ip_address` | VARCHAR(45) | yes | |
| `user_agent` | VARCHAR(512) | yes | Truncated |
| `correlation_id` | VARCHAR(64) | no | Mandatory; from CorrelationId |
| `source` | VARCHAR(16) | no | `http` \| `console` \| `job` \| `scheduler` |
| `created_at` | TIMESTAMP | no | UTC; **no** `updated_at` |

### FK / delete behavior

| Relation | Behavior | Why |
|---|---|---|
| `tenant_id` → tenants | `RESTRICT` | Never cascade-destroy history with tenant purge accidents |
| `actor_user_id` → users | `SET NULL` | Keep row; UI uses `actor_label` |
| Entity morph | **No FK** | Entity delete must not erase or block on audit history |

### Application immutability

- Model: no public update/delete methods used by Actions; `$guarded` / non-fillable mass assignment; optional DB trigger not required for MVP.
- API: no write endpoints.

## 3. Indexes (tenant-leading where applicable)

| Index | Columns | Purpose |
|---|---|---|
| Primary | `id` | |
| `audit_logs_tenant_created_idx` | `(tenant_id, created_at)` | Default list |
| `audit_logs_tenant_event_created_idx` | `(tenant_id, event_type, created_at)` | Event filter |
| `audit_logs_tenant_actor_created_idx` | `(tenant_id, actor_user_id, created_at)` | Actor filter |
| `audit_logs_tenant_entity_created_idx` | `(tenant_id, entity_type, entity_id, created_at)` | Per-entity history panel |
| `audit_logs_tenant_correlation_idx` | `(tenant_id, correlation_id)` | Correlation lookup |

Do **not** add speculative indexes on JSON paths in MVP.

## 4. Scoping model

- **Write path:** `AuditRecorder` sets `tenant_id` / actor from server context — never from client.
- **Read path (tenant Users):** query `where tenant_id = current`. Hybrid null rows are **invisible** to tenants.
- Prefer a dedicated query scope / repository rather than forcing classic `UsesTenantScope` if null hybrid rows conflict with fail-closed scoping — document in implementation PR. Fail closed: no tenant context → no tenant audit reads.

## 5. Migration strategy

1. Migration applied: `2026_08_12_200000_create_audit_logs_table`.
2. Depends on: `tenants`, `users`.
3. No data backfill (historical log lines in `storage/logs` stay logs).
4. Listeners dual-write with existing log listeners.
5. Rollback: drop table only if empty / approved; losing audit is high risk — prefer forward fix.

## 6. Package decision

**Hand-rolled** `Core/Audit` — **do not** add `owen-it/laravel-auditing` or similar (ADR-0015). Aligns with BACKEND_STRUCTURE `Core/Audit`.

## 7. Volume / performance notes

- Expect high write volume relative to Notifications.
- List always paginated; encourage date_from/date_to on large tenants.
- List Resources omit large JSON by default; details endpoint loads `metadata` / before/after.
- Snapshots avoid N+1 entity eager-loads on list.

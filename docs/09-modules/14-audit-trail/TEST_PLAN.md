# Audit Trail — Test Plan

> **Status:** Implemented (Sprint 018)
> **Last updated:** 2026-08-12

No executable tests in the specification sprint. Implementation must cover the matrices below (Pest + Vitest).

## Backend (Pest)

### DATABASE

| ID | Case |
|---|---|
| DB1 | Migration creates `audit_logs` with documented columns |
| DB2 | Tenant-leading indexes exist |
| DB3 | No `updated_at`; append-only usage |
| DB4 | Actor FK nullOnDelete; entity has no destructive FK |

### TENANCY

| ID | Case |
|---|---|
| T1 | Tenant A list excludes Tenant B rows |
| T2 | Detail IDOR foreign id → 404 |
| T3 | Client cannot set `tenant_id` on write path |
| T4 | Job/scheduler with restored context writes correct `tenant_id` |
| T5 | Platform-affecting event stores **target** tenant id |

### AUTHORIZATION

| ID | Case |
|---|---|
| A1 | Unauthenticated → 401 |
| A2 | Authenticated without `audit_logs.view` → 403 |
| A3 | With `audit_logs.view` → 200 list/show |
| A4 | Auditor template can view when granted |
| A5 | No create/update/delete routes |

### ACTOR

| ID | Case |
|---|---|
| AC1 | User actor stores id + label |
| AC2 | System actor: `actor_type=system`, null user id |
| AC3 | Deleted User → row remains; label snapshot; FK null |

### ENTITY

| ID | Case |
|---|---|
| E1 | Snapshots `entity_number` / `entity_label` |
| E2 | After entity delete, audit row remains readable |
| E3 | `entity_type` is alias, not FQCN |

### EVENTS (representative keep set)

| ID | Module sample |
|---|---|
| EV1 | Auth `LOGIN_SUCCESS` |
| EV2 | `USER_ROLES_CHANGED` / `ROLE_PERMISSIONS_CHANGED` |
| EV3 | `CONTRACT_APPROVED` |
| EV4 | `MEETING_COMPLETED` |
| EV5 | `DECISION_APPROVED` |
| EV6 | `TASK_ASSIGNED` |
| EV7 | `DOCUMENT_DOWNLOADED` (no storage path) |
| EV8 | `STOCK_TRANSFERRED` (correlation shared) |
| EV9 | `ASSET_ASSIGNED` / `ASSET_RETURNED` |
| EV10 | Notification mark-read does **not** create audit row |
| EV11 | Dashboard GET does **not** create audit row |

### BEFORE/AFTER & SECRETS

| ID | Case |
|---|---|
| S1 | Password change / reset never stores password or token |
| S2 | Allow-listed changed fields only |
| S3 | Document download metadata excludes paths/bytes |

### METADATA

| ID | Case |
|---|---|
| M1 | Structured keys present |
| M2 | Oversized payload rejected or truncated per policy |

### IMMUTABILITY

| ID | Case |
|---|---|
| I1 | No PATCH/DELETE routes registered |
| I2 | Direct model update attempt not exposed via API |

### TRANSACTION

| ID | Case |
|---|---|
| X1 | Rolled-back domain mutation leaves **no** success audit row |
| X2 | Committed mutation produces exactly one audit row for the event |

### FILTERS / PERF

| ID | Case |
|---|---|
| F1 | event_type, actor, entity, date, correlation filters |
| F2 | Default sort newest first; pagination meta |
| F3 | List query does not N+1 load entities |

### SECURITY

| ID | Case |
|---|---|
| SEC1 | Actor spoof via payload ignored |
| SEC2 | Deep-link availability respects owning Policy |
| SEC3 | Secrets never in API JSON |

## Frontend (Vitest)

| ID | Case |
|---|---|
| FE1 | List renders Arabic event labels |
| FE2 | System actor shows النظام |
| FE3 | Sparse/missing optional fields tolerated |
| FE4 | Filters update query params/API |
| FE5 | Details before/after only changed fields |
| FE6 | Metadata structured render |
| FE7 | Deep link shown only when available |
| FE8 | Deleted target: snapshot, no broken link |
| FE9 | Loading / empty / error+retry |
| FE10 | Permission gate hides nav/route |
| FE11 | Mobile card layout smoke (where practical) |

## Cross-suite

Run Audit suite then full Pest/Vitest; do not weaken existing security tests.

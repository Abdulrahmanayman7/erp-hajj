# Audit Trail

> **Status:** Specification complete — implementation pending (Sprint 018). Event names aligned through Sprint 017 emitters; storage/viewer locked by ADR-0015.
> **Last updated:** 2026-08-12
> Module: [14-audit-trail/](../09-modules/14-audit-trail/) · ADR: [ADR-0015](../10-decisions/ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md)

## Purpose

Define what must be audited and the guarantees the audit trail provides. The audit trail is both an MVP module ([docs/09-modules/14-audit-trail/](../09-modules/14-audit-trail/)) and a cross-cutting requirement — **no module may bypass audit logging** for its critical operations.

Today’s emitters (`AuthorizationSecurityEvent`, `AuthSecurityEvent`) write application logs. Sprint 018 specifies persistence into `audit_logs` via `Core/Audit` without renaming stable event codes.

## Audit Record Fields (binding — Sprint 018)

| Field | Column / notes |
|---|---|
| Tenant | `tenant_id` nullable hybrid — tenant actions set it; platform→tenant writes **target** tenant; pure platform may be `NULL` |
| Context | `context_type`: `tenant` \| `platform` |
| Actor | `actor_type` (`user`\|`system`\|`platform`), `actor_user_id` (nullOnDelete), `actor_label` snapshot |
| Action | `event_type` — machine-readable stable code |
| Entity | `entity_type` alias, `entity_id`, snapshots `entity_number` / `entity_label` |
| Timestamp | `created_at` UTC (no `updated_at`) |
| IP / UA | nullable; server-captured; truncated |
| Source | `http` \| `console` \| `job` \| `scheduler` |
| Metadata | JSON structured extras |
| Before / after | JSON allow-listed changed attributes only |
| Reason | optional string for privileged/lifecycle |
| Correlation ID | **Mandatory** — see below |

Full schema: [14-audit-trail/DATA_MODEL.md](../09-modules/14-audit-trail/DATA_MODEL.md).

## Correlation ID (decided design)

Every incoming request receives a **correlation ID** assigned by early middleware:

- An **incoming** correlation ID (header) is accepted **only** if it passes strict validation (bounded length, safe character set — exact pattern fixed at implementation); otherwise a new **secure random ID** is generated. Never trust arbitrary client strings into logs (log-injection vector).
- The ID is **returned in the response headers** so clients and support can reference it.
- It is included in: application logs, audit records, queued job payloads (and therefore retries and failed jobs), exports, and exceptional platform-access records — one ID traces a request end-to-end across process boundaries.
- It is **not a secret**, must **not** contain tenant or user personal information, and is **never** an authorization input.

## Audited Events (minimum)

Canonical catalog and keep/planned/exclude rules: [14-audit-trail/BUSINESS_RULES.md](../09-modules/14-audit-trail/BUSINESS_RULES.md) § Event catalog.

Summary groups (exact codes already used in code where noted):

- Users / roles / permissions (`USER_*`, `ROLE_*`, `PERMISSION_CATALOG_SYNCED`)
- Organization / employees / positions (`ORGANIZATION_*`, `EMPLOYEE_*`, `POSITION_*`)
- Contracts / meetings / decisions / tasks (full lifecycle sets in `AuthorizationSecurityEvent`)
- Documents including **`DOCUMENT_DOWNLOADED`** (no storage paths/bytes)
- Warehouses / inventory / stock movements
- Assets / custodies (`ASSET_*`)
- Auth (`LOGIN_*`, `LOGOUT`, password-reset, account/tenant block attempts — `AuthSecurityEvent`)
- Tenant lifecycle / settings / exceptional platform access (planned codes when platform Actions ship)

Authoritative per-entity histories (`contract_status_transitions`, `inventory_movements`, …) remain domain SoRs; audit is the cross-cutting accountability stream.

### Notifications vs audit (ADR-0013)

In-app notification **create** and **mark-read** are **not** high-value audit events in MVP. Domain Actions that generate notifications still emit their domain audit events. Notifications are not a substitute for the audit trail.

### Dashboard vs audit (ADR-0014)

Routine `GET /dashboard` is **not** audited. Dashboard Attention is **not** fed from audit rows.

## Guarantees

- **Passwords, tokens, and secrets must never be stored in audit values** (central sanitizer + per-event allow-lists — ADR-0015).
- **Audit records are append-only** — never updated or deleted through the application; viewing requires `audit_logs.view`.
- Audit records include `tenant_id` (per nullable hybrid rules) and are tenant-scoped for viewing.
- Deleting a business record never deletes its audit history.
- Domain mutation audits share the business **DB transaction** (no false success after rollback). Auth path documents an availability exception (ADR-0015).
- **Retention:** indefinite during MVP (no pruning job in Sprint 018).
- **Export:** `audit_logs.export` reserved; format/endpoint **deferred**.
- **Integrity:** no cryptographic hash-chain in MVP.
- **No** generic Eloquent model observers for auditing.

## Viewing

- API: `GET /api/v1/audit-logs`, `GET /api/v1/audit-logs/{id}` — [14-audit-trail/API.md](../09-modules/14-audit-trail/API.md)
- UI: `/app/audit` — [14-audit-trail/UI.md](../09-modules/14-audit-trail/UI.md)

## Resolved (were TBD)

| Topic | Decision |
|---|---|
| Storage | Single `audit_logs` table; hand-rolled `Core/Audit` |
| Retention | Indefinite in MVP |
| Masked fields | Central denylist + allow-listed before/after (ADR-0015) |
| Export format | Deferred (permission reserved) |
| Observers | Forbidden |
| Hash-chain | Not in MVP |

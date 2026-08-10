# Audit Trail

> **Status:** Approved requirement (correlation ID design finalized; auth + RBAC + org + employees + contracts + meetings + decisions event names aligned through Sprint 011); storage design TBD
> **Last updated:** 2026-08-10

## Purpose

Define what must be audited and the guarantees the audit trail provides. The audit trail is both an MVP module ([docs/09-modules/14-audit-trail/](../09-modules/14-audit-trail/)) and a cross-cutting requirement — **no module may bypass audit logging**.

## Audit Record Fields

| Field | Notes |
|---|---|
| Tenant | `tenant_id` — **nullable**: always present for tenant actions; platform events may be `NULL`; platform access affecting a tenant **must** record the target tenant's id |
| User | Acting user ID |
| Context type | **Platform or tenant** — which execution context produced the record |
| Action | Machine-readable action name |
| Entity type | Affected entity class/type |
| Entity ID | Affected record |
| Timestamp | When |
| IP address | Request origin |
| Device / user agent | Client metadata |
| Route or source | Endpoint, console command, or job source |
| Old values | Before state for updates |
| New values | After state |
| Reason or comment | **Required** for privileged/exceptional access and lifecycle transitions (e.g. tenant suspension); where applicable elsewhere |
| Correlation ID | **Mandatory** — see the design below |

## Correlation ID (decided design)

Every incoming request receives a **correlation ID** assigned by early middleware:

- An **incoming** correlation ID (header) is accepted **only** if it passes strict validation (bounded length, safe character set — exact pattern fixed at implementation); otherwise a new **secure random ID** is generated. Never trust arbitrary client strings into logs (log-injection vector).
- The ID is **returned in the response headers** so clients and support can reference it.
- It is included in: application logs, audit records, queued job payloads (and therefore retries and failed jobs), exports, and exceptional platform-access records — one ID traces a request end-to-end across process boundaries.
- It is **not a secret**, must **not** contain tenant or user personal information, and is **never** an authorization input.

## Audited Events (minimum)

- User created, updated, enabled, disabled; user roles changed (`USER_*` — see Users & Authorization).
- Role created/updated/activated/deactivated/deleted; role permissions changed (`ROLE_*`); permission catalog synced (`PERMISSION_CATALOG_SYNCED`).
- Contract created/updated/deleted; lifecycle transitions (`CONTRACT_CREATED`, `CONTRACT_UPDATED`, `CONTRACT_DELETED`, `CONTRACT_SUBMITTED_REVIEW`, `CONTRACT_RETURNED_DRAFT`, `CONTRACT_APPROVED`, `CONTRACT_SIGNED` — **manual attestation only**, `CONTRACT_EXECUTED`, `CONTRACT_CLOSED`, `CONTRACT_CANCELLED`, `CONTRACT_RENEWED`, `CONTRACT_EXPIRED` — see [05-contracts/](../09-modules/05-contracts/)). Authoritative per-contract history remains `contract_status_transitions` (correlation ID when available).
- Meeting lifecycle and related records (`MEETING_CREATED`, `MEETING_UPDATED`, `MEETING_DELETED`, `MEETING_SCHEDULED`, `MEETING_RESCHEDULED`, `MEETING_STARTED`, `MEETING_COMPLETED`, `MEETING_CANCELLED`, attendee/minutes/agenda/recommendation events — implemented Sprint 010; see [06-meetings/API.md](../09-modules/06-meetings/API.md)). Authoritative per-meeting status history: `meeting_status_transitions`.
- Decision lifecycle (`DECISION_CREATED`, `DECISION_UPDATED`, `DECISION_SUBMITTED`, `DECISION_RETURNED_TO_DRAFT`, `DECISION_APPROVED`, `DECISION_CANCELLED`, `DECISION_CLOSED`, `DECISION_DELETED` — implemented Sprint 011; see [07-decisions/API.md](../09-modules/07-decisions/API.md)). Authoritative per-decision status history: `decision_status_transitions`.
- Task lifecycle (`TASK_CREATED`, `TASK_UPDATED`, `TASK_ASSIGNED`, `TASK_REASSIGNED`, `TASK_STARTED`, `TASK_PROGRESS_UPDATED`, `TASK_COMPLETED`, `TASK_CANCELLED`, `TASK_DELETED` — implemented Sprint 012; see [08-tasks/API.md](../09-modules/08-tasks/API.md)). Authoritative histories: `task_status_transitions`, `task_assignment_history`.
- Document lifecycle (`DOCUMENT_UPLOADED`, `DOCUMENT_UPDATED`, `DOCUMENT_LINKED`, `DOCUMENT_UNLINKED`, `DOCUMENT_DOWNLOADED`, `DOCUMENT_ARCHIVED`, `DOCUMENT_RESTORED`, `DOCUMENT_DELETED`, category events — specified Sprint 013; see [09-documents/API.md](../09-modules/09-documents/API.md)). All MVP downloads audited; payloads must not include storage paths or file bytes.
- Inventory transaction created.
- Asset assigned or returned (custody events).
- Tenant settings changed.
- Tenant lifecycle transitions (created/activated/suspended/reactivated/archived) with actor, old/new status, reason, correlation ID, context type.
- Login success; security-relevant login failure (including rejections due to tenant status or disabled account).
- Logout; password-reset requested; password-reset completed (Authentication module — see [01-authentication/BUSINESS_RULES.md](../09-modules/01-authentication/BUSINESS_RULES.md) for event names: `LOGIN_SUCCESS`, `LOGIN_FAILED`, `LOGOUT`, `PASSWORD_RESET_REQUESTED`, `PASSWORD_RESET_COMPLETED`, `ACCOUNT_DISABLED_ACCESS_ATTEMPT`, `TENANT_BLOCKED_ACCESS_ATTEMPT`).
- Organization unit created/updated/moved/activated/deactivated/deleted; manager assigned (`ORGANIZATION_UNIT_*`, `ORGANIZATION_MANAGER_ASSIGNED` — see [03-organization-structure/BUSINESS_RULES.md](../09-modules/03-organization-structure/BUSINESS_RULES.md)).
- Employee created/updated/activated/deactivated; supervisor changed; organization changed; user linked/unlinked (`EMPLOYEE_*` — see [04-employees-and-supervisors/BUSINESS_RULES.md](../09-modules/04-employees-and-supervisors/BUSINESS_RULES.md)); position lifecycle (`POSITION_*`).
- Platform Super Admin access to tenant data (always) — recorded with the **target** tenant's id.
- Unauthorized attempts to enter `PlatformContext`.

Each module lists its audit events in its `BUSINESS_RULES.md` / `ACCEPTANCE_CRITERIA.md`.

## Guarantees

- **Passwords, tokens, and secrets must never be stored in audit values** (masked field list per module: TBD).
- **Audit records are append-only** — never updated or deleted through the application; viewing requires `audit_logs.view`.
- Audit records include `tenant_id` (per the nullable rules above) and are tenant-scoped for viewing.
- Deleting a business record never deletes its audit history.

## TBD

- Storage design (single table vs. per-domain, package vs. hand-rolled): TBD.
- Retention period: TBD.
- Export format for `audit_logs.export`: TBD.
- Masked/sensitive field list per module: TBD.

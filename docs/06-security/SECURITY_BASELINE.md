# Security Baseline

> **Status:** Approved baseline; authentication through Audit Trail implemented (Sprint 018 / ADR-0015)
> **Last updated:** 2026-08-12

## Purpose

Define the minimum security requirements every feature must satisfy before merge.

## Baseline Requirements

### Tenant isolation — threat model and controls

Each control below names the attack it prevents. Implementation details are specified in [MULTI_TENANCY.md](../02-architecture/MULTI_TENANCY.md).

| Attack | Control |
|---|---|
| **IDOR** (user of tenant A requests `/api/v1/contracts/{id}` with tenant B's id) | Route model binding resolves through the automatic `TenantScope` — the foreign row is invisible, so the request yields `404` with zero per-endpoint code. |
| **Enumeration** (probing ids to learn what exists in other tenants) | Cross-tenant lookups return `404`, never `403` — a foreign id is indistinguishable from a nonexistent one. Per-tenant unique constraints prevent the "insert fails, so the value exists elsewhere" oracle. Sequential BIGINT ids are acceptable because isolation never depends on id secrecy. |
| **Mass assignment** (`tenant_id` injected into create/update payloads) | Two independent layers: `tenant_id` is never `$fillable`, and the `UsesTenantScope` trait force-overwrites it from `TenantContext` on create and throws if it is dirtied on update. |
| **Forgotten `where tenant_id`** (developer error) | Automatic global scope on every tenant-owned model; **fail-closed** — a scoped query with no tenant context throws instead of returning rows. Raw SQL against tenant-owned tables is a mandatory review-checklist item. |
| **Cross-tenant reference smuggling** (valid-looking `department_id` belonging to tenant B in a create payload) | All `exists`/`unique` validation rules on tenant-owned references are tenant-scoped. |
| **Route/session abuse by users of a non-active tenant** | `EnsureTenantIsActive` middleware rejects every protected request when the tenant is `pending`, `suspended`, or `archived` (`403` + stable code `TENANT_PENDING`/`TENANT_SUSPENDED`/`TENANT_ARCHIVED`) — lockout is immediate, not at session expiry. |
| **Queue confusion** (job executes against the wrong tenant on retry/another worker) | Tenant id is captured in the job payload at dispatch and re-established by job middleware; retries are deterministic. |
| **Cache poisoning across tenants** | All tenant cache keys carry the `tenant:{id}:` prefix through a single helper; unprefixed tenant cache access is forbidden. |
| **File path traversal / foreign file download** | Storage paths are built from `TenantContext`, never from client input; downloads pass policy checks on tenant-scoped file records; storage is private. |
| **Platform privilege abuse** (Super Admin reading tenant data “because they can”) | Platform users have no tenant context; scoping fails closed for them. There is **no public tenancy bypass** — platform operations run inside the narrowly scoped `PlatformContext`, and access to one tenant's data exists only through the explicit `platform_tenants.access_data` permission wrapped in `TenantContext::runAsTenant()`, **always audited** into the target tenant's own audit trail with the correlation ID. |
| **Tenant code abuse** (using a known/guessed `tenant_code` to reach tenant data) | `tenant_code` is an operational label only (logs, exports, storage diagnostics, future subdomains). No resolver, policy, or endpoint ever accepts it as an authorization input — knowing a code grants nothing. |
| **Untraceable actions** (no way to reconstruct who did what across request → job → export) | Mandatory request **correlation ID** (validated-or-regenerated per request, echoed in response headers) propagated to application logs, audit records, job payloads, and exports — see [AUDIT_TRAIL.md](AUDIT_TRAIL.md). |
| **Inventory race / negative stock** (two concurrent issues or forged balance PATCH) | Stock mutations only via domain actions under `SELECT … FOR UPDATE` on balance rows; no client PATCH of balances/movements; MVP rejects `on_hand < 0` (`INVENTORY_INSUFFICIENT_STOCK`); transfers lock by ascending `warehouse_id` — [ADR-0011](../10-decisions/ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md). |
| **Double asset custody** (two concurrent assigns) | Assign/return under Asset `FOR UPDATE`; re-check no active custody after lock; immutable returned rows — **implemented** Sprint 015 ([ADR-0012](../10-decisions/ADR-0012-ASSET-CUSTODY-AND-OWNERSHIP.md)). |
| **Notification IDOR / inbox leak** | Recipient-owned Policy (`recipient_user_id === actor`); tenant scope; no client create; no stored `action_url`; plain-text bodies — **implemented** Sprint 016 ([ADR-0013](../10-decisions/ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md)). |
| **Dashboard aggregate leakage** | `dashboard.view` page gate; omit sections without module `*.view`; never zero-fill forbidden modules; personal my-tasks/my-custodies never expand to unauthorized tenant totals; no cross-tenant aggregates — **implemented** Sprint 017 ([ADR-0014](../10-decisions/ADR-0014-PERMISSION-AWARE-DASHBOARD-AGGREGATION.md)). |
| **Audit history leakage / tampering** | `audit_logs.view` for reads; append-only (no update/delete API); semantic events + sanitizer (no secrets); tenant-scoped list/show → cross-tenant **404**; actor spoof / client `tenant_id` ignored — **implemented** Sprint 018 ([ADR-0015](../10-decisions/ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md)). |

### Authentication

- **Laravel Sanctum SPA cookie session** (HttpOnly cookie + CSRF via `/sanctum/csrf-cookie`). No Bearer login for the web app; Personal Access Tokens are future mobile/API scope.
- Login identifier: **email + password** only.
- **Rate limiting:** 5 attempts / minute on login and password-reset endpoints, keyed by normalized email + IP (reset may key IP and/or email). Successful login clears the login limiter. Responses must not reveal whether an email exists.
- **Password policy (MVP):** minimum 8 characters; letters and numbers required; symbols allowed; confirmation on reset. Compromised-password checking is future unless an approved package exists.
- **Password reset:** email-based, expiring one-time token, included in Sprint 005. Email verification and MFA are **out** of Sprint 005 (MFA = future enhancement only).
- **Account status:** `active` / `disabled` on `users` — disabled users cannot log in; existing sessions denied on the next protected request.
- **Tenant lifecycle:** pending/suspended/archived tenant users cannot establish an operational session; live sessions rejected next protected request (`TENANT_*` codes). See [01-authentication/BUSINESS_RULES.md](../09-modules/01-authentication/BUSINESS_RULES.md).
- Password hashing with Laravel's default strong hashing; never store plaintext passwords, hashes, reset tokens, or session IDs in audit values.
- Login success and security-relevant login failures are audited (including tenant/account blocks).

### Authorization

- Deny by default; every protected action passes **Policies/Gates** per the [PERMISSION_MODEL.md](PERMISSION_MODEL.md).
- No persona bypasses authorization; exceptional access is explicit and audited.
- Permission and role changes are audited.
- Never rely on frontend permission hiding — backend authorization is mandatory.
- Sprint 006 RBAC (**implemented**): multiple roles; permissions via roles only; global permission catalog; tenant-owned roles; self-escalation and last-Owner protections — [02-users-and-authorization/BUSINESS_RULES.md](../09-modules/02-users-and-authorization/BUSINESS_RULES.md).

### Input, output, and data

- All input validated via Form Requests; mass-assignment protection on all models.
- All output via API Resources — no attribute leakage (hashes, tokens, internal flags).
- Secure errors: no stack traces or internals in production responses.
- Database constraints back up application rules.
- Soft deletion where appropriate; audited records never lose audit history.

### Files and documents

- **Private file storage** — never store private files in public paths.
- **Authorized downloads only** (permission-checked, audited for sensitive documents).
- MIME and extension validation; file size limits; tenant-isolated storage paths.
- Malware scanning readiness (integration point planned; actual scanner TBD).

### Secrets and configuration

- No secrets in the repository — environment variables only.
- No production credentials created or stored at this stage.
- Never use production data locally.

### Operations

- HTTPS in production; secure headers.
- Backup and restore procedures (defined per environment — see [ENVIRONMENTS.md](../08-deployment/ENVIRONMENTS.md)).
- Audit logging per [AUDIT_TRAIL.md](AUDIT_TRAIL.md).

## Compliance Posture

No formal compliance certification is claimed. **Saudi PDPL (نظام حماية البيانات الشخصية) and contractual requirements must be considered during implementation and deployment** — including hosting location requirements (see [ENVIRONMENTS.md](../08-deployment/ENVIRONMENTS.md)).

## Rules

- Every PR completes the security checklist items (tenant isolation, permissions, audit) in the PR template.
- Security-relevant changes require review by the other developer — no self-merge.

## TBD

- Production mail provider (password-reset delivery).
- Malware scanner selection: TBD (Documents Sprint 013 enforces MIME/extension/size + private storage + authz; product scanner deferred — ADR-0010).
- Private document downloads: authorized streamed API only (`documents.download`); no public disk URLs (Sprint 013 implemented).
- Secure header set finalization: TBD at deployment.
- Whether disabled-account login should collapse into generic invalid-credentials for stronger anti-enumeration (current auth spec returns `AUTH_ACCOUNT_DISABLED` after successful password verify) — Change Request only.

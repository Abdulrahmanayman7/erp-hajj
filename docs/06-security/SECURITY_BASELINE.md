# Security Baseline

> **Status:** Approved baseline; implementation details TBD
> **Last updated:** 2026-08-06

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

### Authentication

- Laravel Sanctum; secure token handling.
- **Authentication rate limiting** on login and sensitive auth endpoints (values TBD).
- Password hashing with Laravel's default strong hashing; password policy details TBD.
- Login success and security-relevant login failures are audited.
- CSRF protection where applicable (SPA cookie mode).

### Authorization

- Deny by default; every protected action passes **Policies/Gates** per the [PERMISSION_MODEL.md](PERMISSION_MODEL.md).
- No persona bypasses authorization; exceptional access is explicit and audited.
- Permission and role changes are audited.
- Never rely on frontend permission hiding — backend authorization is mandatory.

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

- Password and lockout policy values: TBD.
- Rate limiting values: TBD.
- Malware scanner selection: TBD.
- Secure header set finalization: TBD at deployment.

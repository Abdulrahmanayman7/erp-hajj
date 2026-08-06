# Security Baseline

> **Status:** Approved baseline; implementation details TBD
> **Last updated:** 2026-08-06

## Purpose

Define the minimum security requirements every feature must satisfy before merge.

## Baseline Requirements

### Tenant isolation

- Cross-tenant access impossible; automatic scoping; `tenant_id` never trusted from payloads (see [MULTI_TENANCY.md](../02-architecture/MULTI_TENANCY.md)).
- Cross-tenant lookups return `404` — never reveal existence of another tenant's data.

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

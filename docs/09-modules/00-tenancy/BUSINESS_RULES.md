# Tenancy — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

## Identity and isolation

- A tenant represents an independent Hajj campaign company or organization. The first tenant is **رفيع** with the immutable `tenant_code` **`rafee`** (expected provisioning values in [DATA_MODEL.md](DATA_MODEL.md); the record is not created yet). The platform is never hardcoded to one tenant — every future tenant receives its own globally unique `tenant_code`.
- Cross-tenant access is impossible; all tenant-owned data is automatically scoped ([MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md)).
- `tenant_id` is never trusted from request payloads; tenant context comes only from the authenticated user. **Knowing a `tenant_code` never grants access** — it is an operational label, not an authorization mechanism.
- Each tenant user belongs to exactly one tenant in the MVP; platform users (Super Admin, Platform Support) belong to no tenant (`tenant_id = NULL`).
- A record never moves between tenants (`tenant_id` is immutable after creation). `tenant_code` is immutable after tenant creation; the display name may change without affecting it.

## Lifecycle

- Statuses: `pending` → `active` → `suspended` ⇄ `active`; any non-archived status → `archived` (terminal). **`archived → active` is forbidden** unless a future exceptional recovery policy is explicitly approved and audited. Full table in [DATA_MODEL.md](DATA_MODEL.md).
- A `pending` tenant's users cannot access the operational application (`403 TENANT_PENDING`).
- **Suspension is a full lockout (decided):** login rejected; existing authenticated sessions rejected on the next protected request; queued business jobs are held (released), not destroyed; maintenance jobs may still run. Data untouched; reactivation clears `suspended_at` and restores everything. Read-only suspension was rejected for MVP (permanent endpoint-classification cost for an unconfirmed need); introducing it later is a Change Request.
- **Tenants are never hard-deleted through normal application flows.** `archived` (+`archived_at`) is the explicit lifecycle mechanism; hard deletion and Laravel soft deletion are both rejected for the registry (reasons in [DATA_MODEL.md](DATA_MODEL.md)).
- Tenants are created, activated, suspended, and archived only by platform users holding the respective `platform_tenants.*` permissions, inside `PlatformContext`.
- **Every status transition records:** actor, timestamp, previous status, new status, reason, request correlation ID, and platform/tenant context type.

## Platform Super Admin

- Has **no implicit access to tenant business data**. Managing the tenant registry never exposes tenant business records.
- Exceptional access into a tenant's data requires `platform_tenants.access_data`, executes inside `TenantContext::runAsTenant()` for that single target tenant, and is **always audited into the target tenant's own audit trail** with the correlation ID.
- Cross-tenant platform operations iterate tenants explicitly and call `runAsTenant()` per tenant — there is no unrestricted all-tenant bypass.

## Audited events (this module)

- Tenant created / updated / activated / suspended / reactivated / archived (actor, old/new values, reason, correlation ID, context type).
- Tenant settings changed (old/new values).
- Every platform exceptional access to tenant data (always, no sampling).
- Login rejections caused by tenant status (`pending`/`suspended`/`archived`) — security-relevant login failures.
- Unauthorized attempts to enter `PlatformContext`.

## TBD

- **Provisioning/onboarding flow** (who creates the first Tenant Owner user; what triggers `pending → active`; credential delivery): unresolved — commercial onboarding not confirmed. **Recommended:** Super Admin creates tenant as `pending` + first Tenant Owner in one platform operation; activation is a separate audited transition; credentials out-of-band. **Impact:** one endpoint + one Action; no schema change.
- **Archived-tenant recovery policy**: forbidden today. **Recommended:** keep forbidden; if ever needed, define via ADR as a twice-confirmed, audited platform operation. **Impact:** none now.
- **Physical purge of archived tenant data** (PDPL/retention): unresolved — legal retention requirements not confirmed. **Recommended:** dedicated ADR + irreversible, twice-confirmed platform operation when required. **Impact:** significant (files, audit anonymization policy); isolated from MVP by the `archived` terminal state.

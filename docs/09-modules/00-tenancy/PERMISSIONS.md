# Tenancy — Permissions

> **Status:** Approved
> **Last updated:** 2026-08-06

## Tenant-level

| Permission | Purpose |
|---|---|
| `tenant_settings.view` | View own tenant's settings |
| `tenant_settings.update` | Update own tenant's settings (audited) |

## Platform-level (grantable only to platform users, `tenant_id = NULL`)

Follows the `module.action` convention with module name `platform_tenants`:

| Permission | Purpose | Which attack/abuse it gates |
|---|---|---|
| `platform_tenants.view` | List/inspect the tenant registry | Registry exposure to unauthorized platform staff |
| `platform_tenants.create` | Create a tenant (validates `tenant_code` pattern and global uniqueness) | Unauthorized tenant provisioning |
| `platform_tenants.update` | Update registry fields (name, contacts, notes — **never** `tenant_code`, which is immutable) | Unauthorized registry tampering |
| `platform_tenants.activate` | Activate a `pending` tenant or reactivate a `suspended` one | Unauthorized (re)activation bypassing a business decision |
| `platform_tenants.suspend` | Suspend a tenant (audited, with reason) | Denial-of-service against a paying tenant by a rogue/compromised platform account |
| `platform_tenants.archive` | Archive a tenant (terminal, audited, with reason) | Irreversible lifecycle action by unauthorized staff |
| `platform_tenants.access_data` | **Exceptional** read access into a tenant's business data via `TenantContext::runAsTenant()` inside `PlatformContext` — always audited into the target tenant's audit trail with the correlation ID | The core "platform privilege abuse" scenario: even the Super Admin role does not include this by default; it must be explicitly granted |

## Rules

- Platform permissions are **never grantable to tenant users**; assignment attempts are rejected and audited.
- Platform permissions authorize operations that execute inside `PlatformContext` only — holding one never bypasses tenant scoping (`TenantOwned` models still require `runAsTenant`).
- `platform_tenants.access_data` grants **read-only** exceptional access in the MVP; any write into tenant data by platform users is out of scope and would require a Change Request.
- Exceptional access is audited per event — every entity read under `access_data` produces an audit record in the target tenant.
- Knowing a `tenant_code` grants nothing: no permission check ever accepts a tenant code as an authorization input.

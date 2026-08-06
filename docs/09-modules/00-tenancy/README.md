# Module: Multi-Tenant Foundation (التأسيس متعدد المستأجرين)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Provide the tenancy foundation every other module depends on: the tenant (organization) entity, tenant lifecycle at platform level, automatic tenant scoping, and tenant context propagation.

## Scope

- Tenant registry (platform-level): create, activate, suspend tenants — performed by the Platform Super Admin.
- Automatic tenant scoping for all tenant-owned models (`Core/Tenancy`).
- Tenant context resolution after authentication; propagation to jobs, cache, storage paths, audit, notifications, exports.
- Tenant-level settings required by MVP modules.

## Out of scope

- Multi-organization membership for one user (future scope).
- Tenant self-signup/billing (TBD — commercial model not confirmed).

## References

- [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md) · [ADR-0003](../../10-decisions/ADR-0003-MULTI-TENANCY.md)

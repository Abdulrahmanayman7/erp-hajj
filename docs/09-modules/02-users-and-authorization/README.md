# Module: Users and Authorization (المستخدمون والأدوار والصلاحيات)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Manage tenant users and the dynamic role/permission system that governs all access in the platform.

## Scope

- Users management: create, view, update, disable, delete.
- Roles management: dynamic, tenant-defined roles.
- Permissions management: assign permissions to roles per the `module.action` catalog.
- Default role templates seeded from the personas (templates only, editable).

## Out of scope

- Multi-tenant membership for one user (future scope).
- Delegation/temporary permission grants: TBD, not approved.

## References

- [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md) · [USERS_AND_PERSONAS.md](../../01-business/USERS_AND_PERSONAS.md)

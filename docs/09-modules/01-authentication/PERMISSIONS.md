# Authentication — Permissions

> **Status:** Approved (Sprint 005)
> **Last updated:** 2026-08-08

Authentication is **identity**, not authorization.

## Sprint 005 rules

- Login, logout, `/auth/me`, forgot-password, and reset-password are **not** gated by `module.action` permissions.
- Any **active** user of an **active** tenant may authenticate (plus active platform users with no tenant).
- After authentication, all business access remains deny-by-default via Policies/Gates — **RBAC is not part of this sprint**.
- **`GET /api/v1/auth/me` must not fabricate a permissions array.** When the Users & Authorization module ships, `/auth/me` (or a dedicated endpoint) may be extended to return effective permissions for the frontend Permission Guard. Until then the SPA must not invent permission lists.

## Future (out of Sprint 005)

| Concern | Owner |
|---|---|
| Effective permissions on current-user payload | Users & Authorization module |
| Frontend Permission Guard | Shared UI + RBAC |
| Platform route permissions (`platform_tenants.*`) | Tenancy + Authorization |

## References

- [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md)
- [BUSINESS_RULES.md](BUSINESS_RULES.md)

# Module: Authentication (المصادقة)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Authenticate tenant users via Laravel Sanctum and establish the tenant context for every request. Authentication events are audited.

## Scope

- Login / logout via Sanctum.
- Current-user endpoint (profile + permissions for frontend Permission Guard).
- Rate limiting on authentication endpoints.
- Audit of login success and security-relevant login failures.

## Out of scope

- Two-factor authentication (not in MVP scope — requires Change Request).
- Social login, SSO (future scope).

## References

- [SECURITY_BASELINE.md](../../06-security/SECURITY_BASELINE.md) · [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md)

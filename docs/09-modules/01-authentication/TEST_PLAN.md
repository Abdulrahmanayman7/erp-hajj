# Authentication — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- Feature: login success; wrong password; nonexistent account (same generic error); disabled user; suspended tenant user.
- Rate limiting: repeated failures hit the limiter.
- Audit: success and security-relevant failure records exist with IP/user agent; no secrets in values.
- Logout: token/session invalidated; subsequent requests return `401`.
- `auth/me`: returns correct permissions; changes after role reassignment.
- Cross-tenant: authenticated user's tenant context matches their tenant only.
- E2E: full login → dashboard → logout flow in RTL.

# Authentication — Business Rules

> **Status:** Approved (stated rules); details TBD
> **Last updated:** 2026-08-06

- Authentication is via Laravel Sanctum; secure token handling throughout.
- Tenant context is established from the authenticated user — never from the request.
- Disabled users must not be able to authenticate.
- Users of a suspended tenant must not operate in the system (behavior detail TBD in tenancy module).
- Login endpoints are rate-limited (values TBD).
- Login success is audited; security-relevant login failures are audited.
- Passwords are hashed with Laravel's default strong hashing; never logged or stored in audit values.

## TBD

- Password policy (length, complexity): TBD.
- Account lockout policy after repeated failures: TBD.
- Password reset flow (email-based?): TBD — not explicitly specified; requires business confirmation.
- Session/token lifetime: TBD.
- Sanctum mode for the SPA (cookie vs. token): TBD.

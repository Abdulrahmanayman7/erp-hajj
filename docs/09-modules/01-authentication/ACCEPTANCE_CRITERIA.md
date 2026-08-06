# Authentication — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] A valid active user can log in and receives a working session/token.
- [ ] A disabled user cannot log in.
- [ ] A user of a suspended tenant cannot operate (behavior per tenancy decision).
- [ ] Login failures return a generic message; no account-existence disclosure.
- [ ] Login endpoints are rate-limited; exceeding the limit returns the standardized error.
- [ ] Login success creates an audit record with IP and user agent; security-relevant failures are audited.
- [ ] Logout invalidates the session/token.
- [ ] `auth/me` returns profile and effective permissions, tenant-scoped.
- [ ] No password, token, or secret ever appears in logs or audit values.

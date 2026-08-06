# Authentication — API (planned)

> **Status:** Planned contract — no endpoints exist yet; paths are proposals
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

```text
POST /api/v1/auth/login       # rate-limited; audited (success and security-relevant failure)
POST /api/v1/auth/logout      # invalidates current token/session
GET  /api/v1/auth/me          # current user profile + effective permissions
```

## Behavior

- Failed login returns the standardized error envelope without revealing whether the account exists.
- `401` for unauthenticated requests to protected endpoints.

## TBD

- Exact paths (proposals above must be confirmed at implementation).
- Password reset endpoints: TBD (flow not yet approved).
- Token refresh strategy (if token mode chosen): TBD.

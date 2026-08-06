# Dashboard — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

```text
GET /api/v1/dashboard        # dashboard.view — returns only the widgets the viewer may see
```

## Behavior

- Response contains widget blocks keyed by widget name; each computed tenant-scoped and filtered by the viewer's permissions.
- Heavy aggregates may be cached with tenant-scoped cache keys (per multi-tenancy rules).

## TBD

- Single endpoint vs. per-widget endpoints (payload size/caching trade-off): TBD at implementation.
- Widget payload shapes: TBD with final widget list.

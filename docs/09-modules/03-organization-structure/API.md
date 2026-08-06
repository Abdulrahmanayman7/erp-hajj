# Organizational Structure — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

## Organizational units

```text
GET    /api/v1/organization-units               # departments.view (tree or flat + parent_id)
POST   /api/v1/organization-units               # departments.create
GET    /api/v1/organization-units/{unit}        # departments.view
PATCH  /api/v1/organization-units/{unit}        # departments.update (includes moving parent)
DELETE /api/v1/organization-units/{unit}        # departments.delete (restricted)
```

## Positions

```text
GET    /api/v1/positions        # permission TBD
POST   /api/v1/positions
GET    /api/v1/positions/{position}
PATCH  /api/v1/positions/{position}
DELETE /api/v1/positions/{position}
```

## TBD

- Tree representation in responses (nested vs. flat with parent references): TBD.
- Position permissions naming: TBD (see PERMISSIONS.md).

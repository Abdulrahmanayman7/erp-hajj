# Assets and Custodies — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

## Assets

```text
GET    /api/v1/assets                    # assets.view; filters: category, status, warehouse, responsible person
POST   /api/v1/assets                    # assets.create
GET    /api/v1/assets/{asset}            # assets.view (includes custody history)
PATCH  /api/v1/assets/{asset}            # assets.update
DELETE /api/v1/assets/{asset}            # assets.delete
POST   /api/v1/assets/{asset}/assign     # assets.assign — creates custody (audited)
POST   /api/v1/assets/{asset}/return     # assets.return — closes active custody (audited)
POST   /api/v1/assets/{asset}/retire     # assets.retire (audited)
```

## Custodies

```text
GET /api/v1/custodies                    # assets.view; filters: employee, status, date range
GET /api/v1/custodies/{custody}          # assets.view
GET /api/v1/my-custodies                 # own-custody view (policy TBD)
```

## Behavior

- Assign fails (standardized error) if the asset is not Available or already has an active custody.
- Return payload includes condition at return and next status (Available/Maintenance/Retired).
- No update/delete endpoints for custody records — history is immutable (corrections: TBD controlled process).

# Contracts — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

## CRUD

```text
GET    /api/v1/contracts                  # contracts.view; filters: category, status, department, expiry window
POST   /api/v1/contracts                  # contracts.create (creates Draft)
GET    /api/v1/contracts/{contract}       # contracts.view (includes status + approval history)
PATCH  /api/v1/contracts/{contract}       # contracts.update
DELETE /api/v1/contracts/{contract}       # contracts.delete (restricted for approved/executed)
```

## Lifecycle transitions (action endpoints; audited; optional comment in body)

```text
POST /api/v1/contracts/{contract}/review    # contracts.review
POST /api/v1/contracts/{contract}/approve   # contracts.approve
POST /api/v1/contracts/{contract}/sign      # contracts.sign
POST /api/v1/contracts/{contract}/execute   # contracts.execute
POST /api/v1/contracts/{contract}/close     # contracts.close
POST /api/v1/contracts/{contract}/renew     # contracts.renew (mechanics TBD)
```

## Behavior

- Invalid transitions (wrong current status) return the standardized error with a machine-readable code.
- Idempotency for transitions: mechanism TBD per API standards.

## TBD

- Category management endpoints (tenant-configurable categories): TBD.
- Attachment endpoints (via documents module): cross-module contract TBD.

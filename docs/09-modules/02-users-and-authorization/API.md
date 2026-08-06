# Users and Authorization — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md): pagination, search, filters, sorting on lists; standardized envelope; `404` for cross-tenant.

## Users

```text
GET    /api/v1/users                    # users.view
POST   /api/v1/users                    # users.create
GET    /api/v1/users/{user}             # users.view
PATCH  /api/v1/users/{user}             # users.update
DELETE /api/v1/users/{user}             # users.delete
POST   /api/v1/users/{user}/disable     # users.disable (action endpoint, audited)
POST   /api/v1/users/{user}/enable      # users.disable (audited)
```

## Roles and permissions

```text
GET    /api/v1/roles                            # roles.view
POST   /api/v1/roles                            # roles.create
GET    /api/v1/roles/{role}                     # roles.view
PATCH  /api/v1/roles/{role}                     # roles.update
DELETE /api/v1/roles/{role}                     # roles.delete
PUT    /api/v1/roles/{role}/permissions         # roles.assign_permissions (audited)
GET    /api/v1/permissions                      # roles.view — catalog listing
```

## TBD

- Role assignment to users (dedicated endpoint vs. part of user update): TBD.
- Filters set per list endpoint: TBD.

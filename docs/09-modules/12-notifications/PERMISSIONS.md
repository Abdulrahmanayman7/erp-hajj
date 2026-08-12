# Notifications — Permissions

> **Status:** Implemented (Sprint 016)
> **Last updated:** 2026-08-12

## Catalog (MVP)

**No** `notifications.*` permissions are seeded for the recipient inbox.

| Capability | MVP decision |
|---|---|
| Own inbox list/show/read/read-all/unread-count | Authenticated **active** tenant User; Policy checks `recipient_user_id === actor.id` + same tenant |
| Create notification | System only |
| View another user’s notifications | **Forbidden** |
| Admin manage / resend | **Out of MVP** |

Rationale: inbox access is **row-level ownership**, analogous to “my custodies” scoping, not a broad module verb. Adding `notifications.view` would either be redundant for everyone or accidentally gate the bell for Employee personas.

If a future admin console is required, introduce `notifications.manage` via Change Request + PermissionCatalog — never role-name checks.

## Policy (`NotificationPolicy`)

| Ability | Rule |
|---|---|
| `viewAny` | Actor is authenticated tenant User (active) |
| `view` | Same tenant **and** `recipient_user_id === actor.id` |
| `markRead` | Same as `view` |
| `markReadAll` | Authenticated tenant User (scopes to self in Action) |

No role-name branches. Disabled users cannot authenticate to call these endpoints.

## Default role templates

No new grants. All personas with login already can use own inbox when Notifications is implemented.

## Frontend

Bell/panel gated only by authentication (session), not `can('notifications.view')`. Deep-links still subject to **target module** Policies (e.g. `tasks.view`).

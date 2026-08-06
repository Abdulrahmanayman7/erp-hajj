# Authentication — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

Authentication builds on the **User** entity (see [02-users-and-authorization/DATA_MODEL.md](../02-users-and-authorization/DATA_MODEL.md)) plus Sanctum's token storage.

| Concern | Notes |
|---|---|
| Credentials | Email/username (identifier field TBD) + hashed password on the User |
| Tokens | Laravel Sanctum personal access tokens / session (mode TBD) |
| Tenant link | User belongs to exactly one tenant (MVP) |
| Login audit | Recorded in the audit trail, including IP and user agent |

## TBD

- Login identifier (email vs. username vs. mobile): TBD.
- Remember-me behavior: TBD.

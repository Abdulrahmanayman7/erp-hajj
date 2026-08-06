# Audit Trail — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

## Audit Record (immutable, append-only)

| Field | Notes |
|---|---|
| Tenant | `tenant_id` (platform-level actions may use a null/platform marker — TBD) |
| User | Acting user |
| Action | Machine-readable action name |
| Entity type | Polymorphic type |
| Entity ID | Polymorphic ID |
| Timestamp | |
| IP address | |
| Device / user agent | |
| Route or source | Endpoint / command / job |
| Old values | JSON, sensitive fields masked |
| New values | JSON, sensitive fields masked |
| Reason / comment | Where applicable |
| Correlation ID | Where useful |

## Constraints

- Append-only: no application paths update or delete rows.
- Indexed for the viewing filters (tenant + entity, tenant + user, tenant + date).

## TBD

- Single table vs. partitioning/archiving strategy: TBD.
- Package (e.g. owen-it/laravel-auditing) vs. hand-rolled in `Core/Audit`: TBD — must be documented when chosen.

# Organizational Structure — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tables carry `tenant_id` with automatic scoping.

## Organizational Unit

| Field | Notes |
|---|---|
| Tenant | Owning tenant |
| Name | Unit name |
| Type | Department / Section / Unit (extensibility TBD) |
| Parent | Self-reference, nullable for top level; unlimited depth |
| Status | Active/inactive (TBD if needed) |
| Timestamps | Standard |

## Position

| Field | Notes |
|---|---|
| Tenant | Owning tenant |
| Title | Job title (المسمى الوظيفي) |
| Description | Optional |

Employees reference: primary unit (one), position (one), direct manager (one employee) — defined in [04-employees-and-supervisors/DATA_MODEL.md](../04-employees-and-supervisors/DATA_MODEL.md).

## TBD

- Hierarchy query strategy (adjacency list vs. nested set/path): implementation decision at design time.
- Unique name constraints (per tenant? per parent?): TBD.

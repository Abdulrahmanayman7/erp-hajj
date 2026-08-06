# Contracts — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tables carry `tenant_id`; contract number unique per tenant.

## Contract

| Field | Notes |
|---|---|
| Contract number | Unique per tenant (generation TBD) |
| Title | |
| Category | Configurable per tenant |
| First party | |
| Second party | |
| Value | Informational (no accounting) |
| Currency | |
| Start date / End date | ISO 8601 |
| Status | Draft / Review / Approved / Signed / Executing / Closed / Renewed (final list at design) |
| Responsible department | Organizational unit reference |
| Created by | User |
| Attachments | Via documents module (preserved) |
| Notes | |
| Soft delete | Preferred for approved/executed |

## Status history

| Field | Notes |
|---|---|
| Contract | |
| From status → to status | |
| Actor, timestamp, comment | Required per transition |

## Approval history

Approval-specific records (approver, timestamp, comment). Overlap with status history: design decision TBD.

## Contract category (per tenant)

Name + active flag; seeded with the initial examples.

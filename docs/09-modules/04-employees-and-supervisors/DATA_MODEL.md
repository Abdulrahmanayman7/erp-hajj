# Employees and Supervisors — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-09

All tables carry `tenant_id`; employee number unique per tenant.

**Depends on:** Sprint 007 `organization_units` (primary unit FK). **Owns:** `positions` catalog (deferred from Organization Structure).

## Employee

| Field | Notes |
|---|---|
| Employee number | Unique per tenant (generation TBD) |
| Full name | |
| National ID | **Sensitive** |
| Mobile number | Sensitivity TBD |
| Email | |
| Organizational unit | One primary unit → `organization_units.id` (membership / placement) |
| Position | One position → `positions` (this module) |
| Direct manager | One **employee** reference (MVP line/reporting manager) — **not** `organization_units.manager_user_id` |
| Employment date | |
| Status | List TBD |
| Attachments | Via documents module (polymorphic) |
| Contract reference | Optional link to a contract |
| Notes | |
| Supervisor flag/type | Marks supervisory classification |
| Timestamps | Standard |

## Supervisor data (for classified employees)

| Field | Notes |
|---|---|
| Experience | |
| Previous seasons | المواسم السابقة |
| Training | |
| Evaluation data | Structure TBD |

## TBD

- Supervisor data storage (columns vs. related table): design decision at implementation.
- Backup supervisor linkage: TBD (not approved for MVP).

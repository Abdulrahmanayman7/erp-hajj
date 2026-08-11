# Employees and Supervisors — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tables carry `tenant_id`; employee number unique per tenant.

## Employee

| Field | Notes |
|---|---|
| Employee number | Unique per tenant (generation TBD) |
| Full name | |
| National ID | **Sensitive** |
| Mobile number | Sensitivity TBD |
| Email | |
| Organizational unit | One primary unit |
| Position | One position |
| Direct manager | One employee reference (MVP) |
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

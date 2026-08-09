# Employees and Supervisors — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-09

- Employee is the primary staff entity; supervisor is a classification on the employee — **no duplicate identity records**.
- An employee has one primary organizational unit, one position, and one direct manager in the MVP.
- **Distinct from organization unit manager:** `organization_units.manager_user_id` is the user responsible for managing that **organizational unit**. Employee **direct manager** (line/HR reporting) is a separate Employees-module relationship and must not be conflated with unit manager. See [03-organization-structure/BUSINESS_RULES.md](../03-organization-structure/BUSINESS_RULES.md).
- Sensitive fields (e.g. national ID) require `employees.view_sensitive_data`.
- Employee creation and updates are audited.
- An employee may reference a contract (contract reference field).
- Employee attachments are handled through the documents module (polymorphic link).

## TBD

- Which exact fields are classified sensitive (national ID confirmed; mobile? salary-related fields are out of scope): TBD.
- Employee number generation (manual vs. auto per tenant): TBD.
- Employee statuses list (active, on leave, terminated...): TBD.
- Backup Supervisor behavior: TBD (future vision, not approved for MVP).
- Whether every employee must have a user account: TBD.

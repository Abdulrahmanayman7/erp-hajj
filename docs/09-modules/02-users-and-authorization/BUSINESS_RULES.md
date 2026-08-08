# Users and Authorization — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- Roles and permissions are **dynamic**; personas are default templates only — never hardcoded as the only roles.
- Every user belongs to exactly one tenant (MVP).
- All users pass Policies/Gates; **no user — including the Tenant Owner — bypasses authorization automatically**.
- Exceptional access must be explicit and audited.
- Disabling a user blocks authentication without deleting history.
- User create/update/delete/disable and every role or permission change are **audited**.
- A user may be linked to an employee record (linkage rules in the employees module; TBD whether every user requires an employee record).

## TBD

- Default role templates and their permission sets: TBD.
- Whether a user can hold multiple roles simultaneously: TBD (recommended yes; confirm).
- Self-service profile editing scope: TBD.
- User deletion vs. permanent disable policy: TBD.

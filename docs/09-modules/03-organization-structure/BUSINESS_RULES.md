# Organizational Structure — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- Organizational units support parent-child relationships; the number of hierarchy levels is **not hardcoded**.
- Recommended unit types: Department, Section, Unit (list must remain extensible: TBD whether tenant-configurable).
- An employee has **one primary organizational unit**, **one position**, and **one direct manager** in the MVP.
- Multiple assignments are future scope unless approved later.
- Deleting a unit that has employees or children must be restricted (reassignment or blocking: TBD).
- Unit create/update/delete are audited.

## TBD

- Whether unit types are a fixed enum or tenant-configurable list: TBD.
- Circular-reference prevention approach (application vs. DB): implementation detail, decide at design.
- Unit managers (is a unit linked to a managing position/employee?): TBD — not explicitly specified.

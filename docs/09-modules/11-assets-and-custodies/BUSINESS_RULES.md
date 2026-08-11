# Assets and Custodies — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- An asset is an individually tracked resource; a custody is the handover of an asset to an employee or supervisor.
- **An asset cannot be actively assigned to more than one person at the same time.**
- Custody assignment records: asset, receiver, assignment date, and expected return date when applicable, plus assigned-by and condition at assignment.
- Return records: actual return date, returned-to, condition at return.
- **Custody history is immutable** — old custody records are never overwritten; corrections only through controlled correction permissions (permission name TBD).
- Asset statuses: **Available, Assigned, Maintenance, Damaged, Retired, Lost.**
- Assignment requires the asset to be Available; returning moves it to Available/Maintenance/Retired per the return outcome.
- Asset assignment and return are audited; retirement (`assets.retire`) is audited.

## TBD

- Custody correction permission and process: TBD.
- Damaged/Lost handling flow (who declares, liability): TBD.
- Asset code generation: TBD.
- Whether custody requires receiver acknowledgment: TBD.

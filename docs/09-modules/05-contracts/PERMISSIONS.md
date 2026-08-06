# Contracts — Permissions

> **Status:** Approved
> **Last updated:** 2026-08-06

| Permission | Purpose |
|---|---|
| `contracts.view` | List/view contracts |
| `contracts.create` | Create draft contracts |
| `contracts.update` | Update contract data (allowed statuses TBD) |
| `contracts.review` | Perform the Review transition |
| `contracts.approve` | Perform the Approval transition |
| `contracts.sign` | Perform the Signature transition |
| `contracts.execute` | Perform the Execution transition |
| `contracts.close` | Perform the Closure transition |
| `contracts.renew` | Perform the Renewal transition |
| `contracts.delete` | Delete contracts (restricted for approved/executed) |

## Rules

- Every transition permission is checked by policy on its action endpoint; all transitions are audited.

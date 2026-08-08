# Assets and Custodies — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A cannot see or assign tenant B assets (`404`); assigning to a tenant B employee rejected.
- Lifecycle: available → assign → return → available/maintenance/retired; assign on non-available asset rejected.
- Double custody: second assign while active custody exists is rejected (race condition test included).
- Immutability: custody records not updatable/deletable via API after return.
- History: repeated assign/return cycles preserve all rows in order.
- Permissions: matrix for all seven `assets.*` permissions.
- Audit: assign, return, retire records with actor and conditions.
- Frontend: action visibility per status; custody timeline rendering.
- E2E: register asset → assign custody → return (maintenance) → reassign after maintenance (status change flow per decided rules).

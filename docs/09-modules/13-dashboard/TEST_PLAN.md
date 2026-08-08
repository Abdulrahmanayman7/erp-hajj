# Dashboard — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** dashboard values computed for tenant A never include tenant B data (verified with parallel fixtures).
- Permission awareness: user lacking `contracts.view` receives no contract widgets; lacking `dashboard.view` gets `403`.
- Correctness: each widget's number matches seeded module data (e.g. 3 overdue tasks seeded → widget shows 3).
- Caching: cached aggregates are tenant-keyed (tenant A cache never served to B).
- Frontend: permission-driven widget rendering; loading/empty/error states; RTL grid.
- E2E: login → dashboard → navigate from a widget to its module list.

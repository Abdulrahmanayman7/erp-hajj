# Assets and Custodies — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] Assets CRUD works, tenant-scoped; asset codes unique per tenant.
- [ ] The six asset statuses behave per the lifecycle; assignment only from Available.
- [ ] An asset can never have two active custodies simultaneously.
- [ ] Custody assignment records asset, receiver, dates, assigned-by, condition.
- [ ] Return records actual date, returned-to, condition; asset moves to the chosen next status.
- [ ] Custody history is complete and immutable — old records never overwritten.
- [ ] Assign, return, and retire are audited.
- [ ] Employees can view their own custodies (per decided policy).
- [ ] Cross-tenant access returns `404`; custody receiver must belong to the same tenant.

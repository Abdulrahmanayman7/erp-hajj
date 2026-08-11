# Decisions — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] Decisions CRUD works, tenant-scoped; decision numbers unique per tenant.
- [ ] A decision can be created from a completed meeting or independently.
- [ ] A decision can generate multiple tasks; related tasks visible on the decision.
- [ ] Closing is blocked while required tasks are incomplete; completing one task never auto-closes the decision.
- [ ] Approval and closure require their permissions and are audited.
- [ ] Statuses New/Approved/In Progress/Completed/Blocked/Cancelled behave per decided semantics (TBD items resolved first).
- [ ] Cross-tenant access returns `404`.

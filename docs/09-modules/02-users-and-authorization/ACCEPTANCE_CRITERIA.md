# Users and Authorization — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] Users CRUD works within the tenant only; cross-tenant access returns `404`.
- [ ] Disabled users cannot authenticate; disable/enable is audited.
- [ ] Roles are dynamic: create/update/delete roles and assign permissions from the catalog.
- [ ] Permission changes take effect on the next authorization check and are audited.
- [ ] Tenant Owner without a given permission is denied that action (no automatic bypass).
- [ ] A user cannot escalate their own permissions.
- [ ] User create/update/delete produce audit records with old/new values (passwords never included).
- [ ] All list endpoints support pagination, search, filters, and sorting.
- [ ] UI hides unauthorized actions but backend still enforces (verified by direct API calls).

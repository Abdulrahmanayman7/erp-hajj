## Summary

<!-- What does this PR do and why? -->

## Related Issue

<!-- Link the issue: Closes #NN -->

## Scope Confirmation

- [ ] This change maps to an approved MVP module/workflow (`docs/00-project/MVP_SCOPE.md`)
- [ ] No features or requirements were added beyond the agreed scope

## Database Changes

- [ ] No database changes
- [ ] Migrations included — described below, with tenant-scoping impact stated

<!-- Describe schema changes and their tenant-scoping impact -->

## API Changes

- [ ] No API changes
- [ ] Endpoints added/changed under `/api/v1` — listed below, following `docs/04-api/API_STANDARDS.md`

<!-- List endpoints and note any contract changes -->

## UI Changes

- [ ] No UI changes
- [ ] UI added/changed — verified correct in Arabic RTL with right-side sidebar

## Multi-Tenant Isolation Check

- [ ] All new/changed tenant-owned data is automatically tenant-scoped
- [ ] Cross-tenant access is impossible (lookups return `404` for other tenants' records)
- [ ] Tenant-isolation tests included for new/changed data access

## Permissions Check

- [ ] Every new/changed protected action is authorized via a Policy/permission
- [ ] Policy tests included

## Audit Log Check

- [ ] All critical operations in this change write audit records
- [ ] Audit tests included
- [ ] Not applicable — no critical operations touched (justify below)

## Tests Executed

<!-- List the test suites/commands run and their results -->

## Documentation Updated

- [ ] Relevant docs in `docs/` updated (or no doc impact — justify)
- [ ] "Last updated" headers refreshed on edited docs

## Screenshots

<!-- For UI changes: RTL screenshots. Otherwise write N/A -->

## Reviewer Checklist

- [ ] Scope respected — nothing outside the approved MVP
- [ ] Controllers thin; validation in Form Requests; use cases in Actions; responses via API Resources
- [ ] Tenant isolation verified (scoping + tests)
- [ ] Authorization verified (policies + tests)
- [ ] Audit coverage verified for critical operations
- [ ] Tests are meaningful and pass
- [ ] Docs updated where behavior or decisions changed

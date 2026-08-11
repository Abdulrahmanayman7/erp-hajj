# Organizational Structure — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] Units can be created at any depth with parent-child relationships; no hardcoded level limit.
- [ ] Unit types Department/Section/Unit supported.
- [ ] Moving a unit updates the hierarchy correctly; circular references are impossible.
- [ ] Deleting a unit with employees or children is restricted per the decided rule.
- [ ] Positions CRUD works.
- [ ] All operations are tenant-scoped; cross-tenant access returns `404`.
- [ ] Unit create/update/delete are audited.
- [ ] Permission matrix (`departments.*`) enforced on API regardless of UI.

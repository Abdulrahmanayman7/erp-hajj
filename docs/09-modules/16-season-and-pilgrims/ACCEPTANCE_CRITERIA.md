# Season and Pilgrim Foundation — Acceptance Criteria

> **Status:** Documentation accepted for Phase 2 alignment. Implementation **not** accepted.
> **Last updated:** 2026-10-04

## This documentation sprint

- [x] F1–F10 recorded without extra business rules.
- [x] Unspecified fields, permissions, APIs, and pages marked **TBD**.
- [x] No migration, model, route, or `PermissionCatalog` change is required by this module’s docs.
- [x] Family Group is foundation-owned and season-specific.
- [x] Accommodation current-place columns are forbidden on Pilgrim.

## Future implementation (blocked until TBD columns and verbs are decided)

- [ ] Tenant-scoped Season with at most one Active, history retained when Closed.
- [ ] Pilgrim and SeasonPilgrim with uniqueness `(tenant, season, pilgrim)`.
- [ ] Snapshot allow-list only (list itself still TBD).
- [ ] Delete vs cancel per F7.
- [ ] Family Group optional membership, primary member, audited moves.
- [ ] SeasonPilgrim status limited to Registered, Active, Completed, Cancelled.
- [ ] Policies, audit, cross-tenant 404 tests, Arabic RTL UI.
- [ ] Transportation and Accommodation reference SeasonPilgrim and do not write these rows.

Blocking TBDs before code: [DATA_MODEL.md](DATA_MODEL.md) field list, [PERMISSIONS.md](PERMISSIONS.md) verbs, definition of operational reference, snapshot allow-list.

# Module: Season and Pilgrim Foundation (الموسم والحجاج)

> **Status:** Backend foundation implemented (Phase 2 sprint 1, 2026-10-05): Seasons, Pilgrims, identity documents, SeasonPilgrims, identity snapshots, programs, family groups, pilgrim groups, group supervisors, Season Context. **Frontend not implemented.**
> **Last updated:** 2026-10-05
> Binding: [ADR-0017](../../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md) · [CHANGE_REQUEST_PHASE2_FOUNDATION.md](../../00-project/CHANGE_REQUEST_PHASE2_FOUNDATION.md)

## Purpose

Own the permanent-versus-seasonal identity boundary for Phase 2:

- **Season** under an existing Tenant (not a new Tenant per Hajj year).
- **Pilgrim** as the stable identity inside the Tenant.
- **SeasonPilgrim** as that pilgrim’s single participation in one Season.
- **Family Group** as a season-specific grouping of SeasonPilgrims.

Transportation and Accommodation consume these references. They do not own them.

## Scope (this specification)

| In scope (approved rules) | Not in this documentation sprint |
|---|---|
| F1–F10 invariants in [BUSINESS_RULES.md](BUSINESS_RULES.md) | Migrations, models, routes, UI, permission seeding |
| Ownership boundaries for stable vs seasonal data | Final column lists (TBD) |
| Lifecycle graphs that are explicitly approved | Permission verb catalog (TBD) |

## Out of scope

- Pilgrim mobile application.
- Visa, finance, payment, document, accommodation, or transportation status on `SeasonPilgrim.status`.
- Full seasonal cloning automation (copying an entire prior season). Whether any copy helper exists later is **TBD**.
- Government integrations.
- Owning trips, rooms, beds, or occupancy.

## Personas

Existing templates in [USERS_AND_PERSONAS.md](../../01-business/USERS_AND_PERSONAS.md) only. Which roles operate seasons and pilgrims is **TBD**. Tenant Owner does not bypass Policies. Pilgrims are business records, not a new login persona, unless a later change request says otherwise (**TBD**).

## Module placement

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/SeasonPilgrims/` (implemented) |
| Frontend | `frontend/src/modules/season-pilgrims/` (not started) |
| API | `/api/v1` — see [API.md](API.md) |
| Tests | `backend/tests/Feature/SeasonPilgrims/` |
| Docs | `docs/09-modules/16-season-and-pilgrims/` |

## Dependencies

- **Requires:** Tenancy, Auth, Users/RBAC, Audit. Organization units and Employees are referenced only if a later rule assigns a supervisor. That link shape is **TBD**.
- **Must not:** duplicate User or Employee identity.

## Documents

| File | Role |
|---|---|
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | F1–F10 |
| [DATA_MODEL.md](DATA_MODEL.md) | Classification and invariants; columns TBD |
| [API.md](API.md) | Resource groups; payloads TBD |
| [PERMISSIONS.md](PERMISSIONS.md) | Namespaces; verbs not seeded |
| [UI.md](UI.md) | Pages TBD |
| [TEST_PLAN.md](TEST_PLAN.md) | Required cases when code exists |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | DoD |

## References

- [17-transportation-and-fleet/](../17-transportation-and-fleet/)
- [18-accommodation-and-camps/](../18-accommodation-and-camps/)

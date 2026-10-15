# Season and Pilgrim Foundation — UI

> **Status:** Not designed. **TBD.**
> **Last updated:** 2026-10-04

When a later sprint builds UI it must follow [FRONTEND_STRUCTURE.md](../../02-architecture/FRONTEND_STRUCTURE.md) and the existing RTL shell (right sidebar, Arabic, TanStack Query, `can()` for visibility only).

## Pages

**TBD.** Expected areas, not a wireframe:

- Season list and lifecycle actions (Draft / Active / Closed), including historical seasons.
- Pilgrim identity maintenance (current data only).
- Season participation for the selected season, including cancellation and mistaken delete where permitted.
- Family Groups for that season, optional membership, primary member, move member.

No pilgrim mobile app. No navigation entry is added in this documentation sprint (`useAppNavigation.ts` unchanged).

## States

Loading, empty, and error states are required when pages exist. Copy is **TBD**.

## Out of scope for UI

- Showing accommodation current room as a column owned by this module. A later read-only embed may call the Accommodation read model. Layout **TBD**.
- Transportation dispatch screens.

# Accommodation and Camps — UI

> **Status:** Not designed. **TBD.**
> **Last updated:** 2026-10-04

No sidebar or router changes in this sprint.

When built, use the existing Arabic RTL shell, TanStack Query, and `can()` for visibility only.

## Likely surfaces (not wireframes)

- Place setup: capacity-based vs position-based; audience Male / Female / Family.
- Planning: demand vs capacity; draft and confirmed assignments; allocation queue.
- Operational stay: assignment interval, arrival, check-in, check-out, relocation (bulk entry point **TBD**).
- Timeline for one SeasonPilgrim’s accommodation (distinct from the audit viewer).
- Current state labels matching the read model (assigned / arrived / checked-in / checked-out).
- Later: offline pending/conflict UI; QR scan. Not in the first UI slice unless a sprint explicitly includes them.

Combined relocate + check-in may be one screen action but must show or record both events.

Do not auto-check-in from a transportation screen.

Notification settings UI, if any, configures business events only. It does not add SMS/push.

Copy, routes, and empty/loading/error layouts **TBD**.

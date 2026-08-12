# Module: Notifications (الإشعارات)

> **Status:** Implemented (Sprint 016)
> **Last updated:** 2026-08-12
> ADR: [ADR-0013](../../10-decisions/ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md)

## Purpose

Deliver **in-app notifications required by MVP modules** so authenticated Users receive timely, tenant-isolated attention signals (assignment, approval, expiry, overdue). Notifications are **not** an audit clone and **not** a marketing/broadcast channel.

## Scope (MVP)

- Persisted **in-app** notification inbox (database SoR).
- System-generated rows only (domain hooks + scheduled jobs).
- Recipient-owned access (list / show / mark read / mark all read / unread count).
- Topbar bell + dropdown panel + full page `/app/notifications`.
- Stable type catalog for Contracts, Meetings, Decisions, Tasks, Assets/Custodies, and Inventory low-stock.
- Dedupe for scheduled/idempotent types.
- Architecture ready for future delivery adapters (email/SMS/push) **without** rewriting domain modules.

## Out of scope (MVP)

- Email, SMS, WhatsApp, FCM, Web Push, mobile push, third-party providers.
- User preferences / mute rules / digest digests.
- Admin “view everyone’s notifications” console.
- Client-created notifications; broadcast to all tenant users by default.
- WebSockets / Reverb / Pusher realtime.
- User hard-delete of notifications; mark-unread.
- Notification templates CMS; HTML bodies.
- Coupling to Audit Trail retention.

## Personas

| Persona | Use |
|---|---|
| Any authenticated tenant User | Own inbox, bell badge, deep-links |
| Domain actors (managers, assignees) | Receive targeted event notifications via linked User |
| Platform Super Admin | No business inbox access in MVP |

## Documentation set

| Doc | Contents |
|---|---|
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Entity, recipients, catalog, sync/queue, dedupe, retention |
| [DATA_MODEL.md](DATA_MODEL.md) | `notifications` table, indexes, migration order |
| [API.md](API.md) | Endpoints, filters, errors |
| [PERMISSIONS.md](PERMISSIONS.md) | Recipient-owned access (no manage catalog in MVP) |
| [UI.md](UI.md) | Bell, panel, full page, mobile, polling |
| [TEST_PLAN.md](TEST_PLAN.md) | Pest + Vitest matrices |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | DoD |

## Implementation placement (future)

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/Notifications/` |
| Frontend | `frontend/src/modules/notifications/` + topbar bell in app shell |
| Config | `config/notifications.php` (thresholds, severity map, polling defaults) |

## Dependencies

- Tenancy, Auth, Users/RBAC (recipient User).
- Employees (User↔Employee for assignee/holder resolution — never auto-create Users).
- Contracts, Meetings, Decisions, Tasks, Assets/Custodies, Inventory (event sources).
- Queue + scheduler (tenant context per MULTI_TENANCY §9 / §12).

## Explicit non-goals reminders

- Notifications ≠ Audit Trail.
- Do not notify every `AuthorizationSecurityEvent`.
- Do not fan out every event to Tenant Owner / GM by default.

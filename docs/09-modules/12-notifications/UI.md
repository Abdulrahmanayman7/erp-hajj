# Notifications — UI

> **Status:** Implemented (Sprint 016)
> **Last updated:** 2026-08-12
> Shell: existing AppTopbar / AppSidebar / RTL — **do not redesign** the shell.

## Placement

| Item | Value |
|---|---|
| Frontend module | `frontend/src/modules/notifications/` |
| Topbar | **Bell** control in `AppTopbar` (icon already present in scaffold — wire real data) |
| Arabic label | الإشعارات |
| Full page | `/app/notifications` (required — dropdown is not enough for history/filters) |
| Sidebar | Optional secondary link الإشعارات under system/account group **or** reachable only via bell “عرض الكل” — **prefer** topbar + full page; sidebar entry optional if cluttered |

## Topbar bell

1. Bell button with accessible label الإشعارات.
2. **Badge** = unread count from `GET /notifications/unread-count` (hide when 0).
3. Click opens **dropdown/panel** (desktop) or **full-screen sheet** (mobile).
4. Panel shows latest N (e.g. 10) notifications; footer link عرض الكل → `/app/notifications`.
5. Actions in panel: mark one read (on click or explicit), mark all read.

## Notification row

- Severity icon/color (info/warning/critical).
- Title (bold if unread).
- Short body (truncate).
- Relative time (Arabic locale) + optional absolute tooltip.
- Unread dot/indicator.
- Click: mark read (if unread) + navigate via entity map; if no entity, mark read only.

## Full page `/app/notifications`

- Header الإشعارات.
- Filters: unread only, type, severity.
- Paginated list (desktop table or stacked rows; mobile cards).
- Mark all read CTA.
- Empty / loading / error states.
- No delete control.

## Deep-link map (frontend)

| `entity_type` | Route |
|---|---|
| `contract` | `/app/contracts/:id` |
| `meeting` | `/app/meetings/:id` |
| `decision` | `/app/decisions/:id` |
| `task` | `/app/tasks/:id` |
| `asset` | `/app/assets/:id` |
| `custody` | `/app/assets/:assetId` if known, else assets list / my-custodies — **prefer** include asset id in body/title; if only custody id, route to `/app/my-custodies` or asset details when API adds compact `meta` — MVP: navigate to `/app/assets` search by number in title **or** store entity_type `asset` on custody events pointing at asset id (recommended) |
| `inventory_item` | `/app/inventory/items/:id` |
| `warehouse` | `/app/warehouses/:id` |

**Recommended for custody types:** set `entity_type=asset`, `entity_id=asset_id` so deep-link is always valid.

## Real-time

**MVP = polling**, not websockets:

- TanStack Query `refetchInterval` ~ **60s** for unread-count + open panel list.
- Refetch on window focus.
- Invalidate after mark-read / read-all mutations.

## Mobile

- Bell always reachable in topbar.
- Panel as bottom/full-screen sheet; large tap targets.
- No cramped desktop-only dropdown on small screens.
- RTL preserved.

## Accessibility

- Badge announced for screen readers when count changes (polite).
- Keyboard operable panel.

## Explicit non-goals

- Toast spam for every notification (optional subtle toast only if product asks later).
- HTML rich content.
- Preference center.

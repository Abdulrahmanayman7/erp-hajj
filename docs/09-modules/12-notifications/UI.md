# Notifications — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Planned surface

- **Header notification bell** — unread indicator, dropdown with recent notifications (placement per app shell design; TBD noted in frontend structure).
- **Notifications list page** — own notifications with filters (read/unread, type); links to related entities.

## Rules

- Arabic-first content; RTL layout.
- Clicking a notification navigates to the related entity (permission failures handled gracefully — `404` page if access was revoked).

## TBD

- Bell vs. page-only presentation: TBD.
- Real-time updates (polling vs. websockets): TBD — polling is the default assumption; do not add websockets without approval.

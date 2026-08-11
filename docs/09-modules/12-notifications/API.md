# Notifications — API (planned)

> **Status:** Planned contract — no endpoints exist yet; proposals pending TBD decisions
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

```text
GET  /api/v1/notifications                       # own notifications; filters: read/unread, type, date
POST /api/v1/notifications/{notification}/read   # mark as read (proposal)
POST /api/v1/notifications/read-all              # proposal
```

## Behavior

- Responses contain only the authenticated user's notifications within their tenant.
- Generation is internal (module events + queued jobs) — no public "send notification" endpoint in the MVP.

## TBD

- Endpoints depend on read/unread model decision: TBD.
- Unread count endpoint for the header bell: TBD with UI design.

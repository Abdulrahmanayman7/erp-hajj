# Notifications — Acceptance Criteria

> **Status:** Implemented (Sprint 016)
> **Last updated:** 2026-08-12

## Functional

- [x] In-app notifications persist for documented MVP types only.
- [x] Recipient is always a same-tenant User; Employee-without-User skips quietly.
- [x] Users list/show/mark-read/mark-all-read/unread-count **only** their own rows.
- [x] No cross-tenant delivery or IDOR leakage (404).
- [x] No client create/delete/mark-unread.
- [x] Scheduled types honor tenant timezone + dedupe_key uniqueness.
- [x] Contract expiring/expired, task due/overdue, custody overdue/soon, meeting starting soon, low-stock (responsible only) behave per BUSINESS_RULES.
- [x] Immediate hooks fire after successful domain actions without failing those actions on notification errors.
- [x] Deep-links use entity_type + entity_id (no stored external URLs).
- [x] Topbar bell + badge + panel + `/app/notifications` page (Arabic RTL).
- [x] Polling/refetch — no WebSockets requirement.

## Security / quality

- [x] Pest matrix in TEST_PLAN green (especially tenancy, IDOR, dedupe).
- [x] Vitest bell/panel coverage green; `tsc` + build pass.
- [x] Plain-text content only.
- [x] Docs/ADR-0013 match implementation.

## Explicitly not required for MVP acceptance

- [ ] Email/SMS/push delivery
- [ ] User preferences
- [ ] Admin inbox browser
- [ ] Realtime websockets

# Notifications — Acceptance Criteria

> **Status:** Specification complete — implementation pending (Sprint 016)
> **Last updated:** 2026-08-11

## Functional

- [ ] In-app notifications persist for documented MVP types only.
- [ ] Recipient is always a same-tenant User; Employee-without-User skips quietly.
- [ ] Users list/show/mark-read/mark-all-read/unread-count **only** their own rows.
- [ ] No cross-tenant delivery or IDOR leakage (404).
- [ ] No client create/delete/mark-unread.
- [ ] Scheduled types honor tenant timezone + dedupe_key uniqueness.
- [ ] Contract expiring/expired, task due/overdue, custody overdue/soon, meeting starting soon, low-stock (responsible only) behave per BUSINESS_RULES.
- [ ] Immediate hooks fire after successful domain actions without failing those actions on notification errors.
- [ ] Deep-links use entity_type + entity_id (no stored external URLs).
- [ ] Topbar bell + badge + panel + `/app/notifications` page (Arabic RTL).
- [ ] Polling/refetch — no WebSockets requirement.

## Security / quality

- [ ] Pest matrix in TEST_PLAN green (especially tenancy, IDOR, dedupe).
- [ ] Vitest bell/panel coverage green; `tsc` + build pass.
- [ ] Plain-text content only.
- [ ] Docs/ADR-0013 match implementation.

## Explicitly not required for MVP acceptance

- [ ] Email/SMS/push delivery
- [ ] User preferences
- [ ] Admin inbox browser
- [ ] Realtime websockets

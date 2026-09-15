# [Change Request] PWA + Web Push background notifications

> Status: **Awaiting approval** — no design or code for this change until Decision is Approved.
> Filed: 2026-09-15 (repo draft; open as GitHub Issue when `gh` is available)
> Template: `.github/ISSUE_TEMPLATE/change-request.md`

## Requested Change

Add Progressive Web App (PWA) installability (web app manifest, iOS/Android home-screen icons, Add to Home Screen) and **Web Push** so tenant users can receive notifications while the browser/app is in the background or the device screen is locked — beyond the current in-app inbox + polling model.

## Type

- [x] New feature outside current MVP scope
- [ ] Change to an approved requirement or workflow
- [x] Change to an architecture decision (requires ADR update/supersede)
- [ ] Removal/descoping

## Justification

Operators want a mobile-app-like presence on iPhone/Android (icon + push) without building native apps in this phase. Current MVP notifications are in-app only (ADR-0013); push is explicitly out of MVP in `docs/00-project/MVP_KNOWN_LIMITATIONS.md`.

## Impact Assessment

- Affected modules: Notifications (12), Frontend shell, possibly Authentication (permission prompts)
- Affected documents: `MVP_SCOPE.md`, `OUT_OF_SCOPE.md` / known limitations, `docs/09-modules/12-notifications/*`, **ADR-0013** (supersede or amend delivery model)
- Database / API / UI impact:
  - Tenant-scoped push subscription storage (`tenant_id`, `user_id`, endpoint, keys)
  - VAPID / Web Push config on Hostinger (or equivalent)
  - Service worker + manifest + icon assets
  - `DeliveryAdapter` implementation for Web Push alongside in-app persist
- Tenant isolation, permissions, or audit impact:
  - Subscriptions must be tenant-scoped; fail closed without tenant context
  - Push payloads must not leak cross-tenant data or secrets
  - Consent / browser permission UX; no persona authorization bypass

## Decision

- [ ] Approved
- [ ] Rejected
- [ ] Deferred to roadmap

**Decided by:** — **Date:** —

## Notes for implementers (after approval only)

1. Do not start PWA/push code until this CR is marked Approved.
2. Update or supersede ADR-0013 before coding delivery.
3. Hostinger requires HTTPS, VAPID keys in env (never committed), and queue worker for push fanout.

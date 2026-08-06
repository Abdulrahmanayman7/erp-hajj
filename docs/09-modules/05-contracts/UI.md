# Contracts — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Pages

- **Contracts list** — Data Table with filters (category, status, department, nearing expiry); status badges consistent with the design system.
- **Contract details** — data, parties, dates/value, attachments, **status timeline** (Timeline component), approval history, audit history panel.
- **Create/edit form** — category selector, department selector, parties, dates, attachments upload.
- **Transition actions** — buttons per permission (review/approve/sign/execute/close/renew) with Confirmation Dialog + optional comment field.

## Rules

- Transition buttons appear only for the valid next stages and only with the matching permission (UX; backend enforces).
- Expiring contracts are visually flagged (threshold TBD).

## TBD

- Category management UI location (contracts settings vs. tenant settings): TBD.

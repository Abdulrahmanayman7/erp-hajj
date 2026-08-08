# Assets and Custodies — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Pages

- **Assets list** — Data Table with filters (category, status, warehouse); status badges per the six statuses.
- **Asset details** — data, current status, current holder (if assigned), **custody history timeline** (immutable), attachments, audit history panel.
- **Assign custody form** — receiver (User/employee Selector), expected return date, condition at assignment; only for Available assets.
- **Return custody form** — condition at return, next status (Available/Maintenance/Retired).
- **My custodies** — employee's own custody list (per own-custody policy TBD).

## Rules

- Assign action visible only for Available assets with `assets.assign`; return only for active custody with `assets.return`.
- Confirmation Dialog on assign/return/retire.

## TBD

- Custody handover print/acknowledgment form: TBD.

# Users and Authorization — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Pages

- **Users list** — Data Table with search/filters (status, role), pagination; create/edit in drawer or page (TBD).
- **User details** — profile, roles, status, linked employee, audit history panel.
- **Roles list** — roles with user counts.
- **Role editor** — role details + permission matrix grouped by module (checkboxes per `module.action`).

## Rules

- Permission Guard hides/disables actions the viewer lacks (`users.*`, `roles.*`) — UX only.
- Disable/enable and delete use Confirmation Dialog.
- Role permission changes show a clear confirmation (they affect access immediately).

## TBD

- Drawer vs. full page for forms: TBD by design system usage.
- Bulk actions on users: TBD (not approved — do not build without approval).

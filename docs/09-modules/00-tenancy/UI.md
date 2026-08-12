# Tenancy — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Tenant-level

- **Tenant settings page** — `/app/settings` (الإعدادات), permission-gated (`tenant_settings.*`). Full UX: [15-system-settings/UI.md](../15-system-settings/UI.md). Optional audit history via Audit module filters after `TENANT_SETTINGS_UPDATED` ships.

## Platform-level

- Super Admin tenant management screens (list, create, activate/suspend): scope and design **TBD** — may be a separate platform area outside the tenant app shell.

## Rules

- All UI follows the shared design system (RTL, right sidebar) — see [DESIGN_GUIDELINES.md](../../05-ui-ux/DESIGN_GUIDELINES.md).

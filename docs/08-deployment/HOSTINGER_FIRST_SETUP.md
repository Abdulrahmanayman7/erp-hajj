# Hostinger — First Setup & Platform Tenants

Production-safe path to bring up ERP Hajj without seeders/SQL/Tinker for operational tenants.

## Prerequisites

1. Deploy backend + frontend (existing Hostinger runbook).
2. Run migrations (includes `2026_09_17_100000_create_platform_rbac_tables`).
3. `CACHE_STORE` / cache driver must support atomic locks (`database`, `redis`, or `file` — not `array` in production). Required for First Setup race protection.
4. Configure mail (`MAIL_*` and/or per-tenant SMTP later) so owner invites can send; if invite fails after provision, the tenant remains and UI offers manual password / retry paths.

## Exact sequence (fresh deployment)

1. Open the app URL → guest flow detects `GET /api/v1/platform/setup/status` → `available: true`.
2. Complete **First Setup** (`/setup`): create Platform Administrator (name, email, password).
3. Setup closes automatically (criterion = active platform user with an active platform role — **not** “tenant exists”).
4. Log in as Platform Admin → redirected to `/platform/tenants`.
5. **إضافة منشأة** wizard:
   - Tenant data (`tenant_code`, name, contacts, timezone, status `active`/`pending`)
   - Owner (name, email, invite or temporary password)
   - Initial settings present on domain (`mail_from_*`, timezone) — **no** currency/trade_name
   - Review → Create
6. `ProvisionTenant` runs in one DB transaction: Tenant → catalog sync → default tenant roles → Owner user → `tenant_owner` → audit. Invite is attempted **after** commit.
7. Owner logs in → manages tenant under `/app` (RBAC already provisioned).

## After first deployment

No seeder, SQL, SSH, or `.env` per-tenant edits are required to create additional operational tenants — always use Platform → إدارة المنشآت.

## Manual one-time steps

| Step | When |
|---|---|
| Run migrations once after deploy | Always |
| Complete `/setup` once | Only when no platform admin exists |
| Optional: configure server `MAIL_*` | If you want invites without per-tenant SMTP yet |

Do **not** rely on Demo/Tenant seeders for production First Setup.

## Lifecycle (Platform UI)

- Primary actions: **Activate** / **Suspend** (reason required for suspend).
- **Archive** is available (API + secondary UI) and is **terminal** (`archived → active` forbidden).
- No hard delete of tenants.

## Ownership transfer

Platform Details → نقل الملكية: pick another **active same-tenant user**. Platform users cannot become Tenant Owner. Previous owner is not deleted.

## Organization Tree

Tenant users with `organization_units.view` open شجرة المنشأة from the topbar (next to tenant name) or `/app/organization-tree`. Platform Admin does **not** get this via `platform_tenants.view`.

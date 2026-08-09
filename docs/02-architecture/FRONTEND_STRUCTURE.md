# Frontend Structure

> **Status:** Approved — Auth (005) + Users/Roles RBAC UI (006) + Organization UI (007) implemented
> **Last updated:** 2026-08-09

## Purpose

Define the structure of the Vue 3 frontend so both developers organize code identically. The shell is scaffolded; module folders are created as modules are implemented.

## Stack

Vue 3, TypeScript, Vite, Pinia, Vue Router, TanStack Query, Tailwind CSS, vue-i18n, Vitest. Arabic RTL, right-side navigation sidebar, responsive desktop-first design.

## Conceptual Structure

```text
src/
├── app/
│   ├── router/
│   ├── providers/
│   ├── layouts/          # app shell: RTL layout, right sidebar, header
│   └── guards/           # auth/permission route guards
├── modules/
│   ├── auth/
│   ├── dashboard/
│   ├── users/
│   ├── roles/
│   ├── organization/             # Sprint 007 — implemented
│   ├── employees/                # Sprint 008 — specified; not implemented
│   ├── contracts/
│   ├── meetings/
│   ├── decisions/
│   ├── tasks/
│   ├── documents/
│   ├── warehouses/
│   ├── inventory/
│   ├── assets/
│   └── custodies/
├── shared/
│   ├── api/              # http client, envelope handling
│   ├── components/       # generic shared components (see DESIGN_GUIDELINES.md)
│   ├── composables/
│   ├── constants/
│   ├── types/
│   ├── utils/
│   ├── validation/
│   └── styles/
└── main.ts
```

A module may contain: pages, components, api, queries, mutations, types, validation, routes, permissions, tests.

### Auth module (Sprint 005 — implemented)

`src/modules/auth/` owns login/forgot/reset pages, auth API client, TanStack Query current-user query + auth mutations, and auth routes. Route guards live under `src/app/guards/`.

**State rule:** current user = TanStack Query server state; avoid a duplicated global Pinia auth store unless guards need a thin coordination flag (documented in [01-authentication/UI.md](../09-modules/01-authentication/UI.md)).

### Users & Roles (Sprint 006 — implemented)

- `src/modules/users/` — list, drawer create/edit, role assignment UI.
- `src/modules/roles/` — role list + permission matrix (catalog read-only).
- Shared `can()` / `PermissionGuard` from current-user query permissions (no Pinia permission store).
- Sidebar: إدارة النظام → المستخدمون / الأدوار والصلاحيات (permission-aware).
- Authenticated but unauthorized → `/app/403` (not login).
- Full UI contract: [02-users-and-authorization/UI.md](../09-modules/02-users-and-authorization/UI.md).

### Organization (Sprint 007 — implemented)

- `src/modules/organization/` — route `/app/organization`; sidebar **الهيكل التنظيمي** (`organization_units.view`).
- Tree + details panel; create/edit Drawer; confirmations for move/deactivate/delete via `AppConfirmDialog`.
- Full UI contract: [03-organization-structure/UI.md](../09-modules/03-organization-structure/UI.md).

### Employees (Sprint 008 — specified, not implemented)

- `src/modules/employees/` — route `/app/employees`; sidebar **الموظفون** (`employees.view`).
- List + drawer; supervisor/user-link actions; positions catalog UX.
- Full UI contract: [04-employees-and-supervisors/UI.md](../09-modules/04-employees-and-supervisors/UI.md).

## Rules

- **No Axios calls directly inside presentation components** — data access lives in the module `api/` layer, consumed via TanStack Query.
- **No backend business rules duplicated in the frontend.**
- **Server state belongs in TanStack Query**; small client state may use Pinia or local state.
- **Frontend permissions are UX only** (hide/disable); backend authorization is mandatory.
- Generic components belong in `shared/`; module-specific components stay in the module.
- **RTL must be supported everywhere**; the sidebar is on the right.
- TypeScript throughout; typed API payloads per module; avoid `any`.

## Decisions Made at Scaffolding

- **i18n:** `vue-i18n` (Composition API mode), Arabic default locale, messages in `src/locales/`.
- **Sanctum mode:** SPA cookie session (HttpOnly cookie + CSRF); the shared API client sends `credentials: 'include'`.
- **Path alias:** `@/` → `src/`.

## Auth UI decisions (Sprint 005 — implemented)

- Guest routes: `/login`, `/forgot-password`, `/reset-password`.
- Authenticated shell after login: temporary welcome / system status — **no fake dashboard KPIs**.
- CSRF: `GET /sanctum/csrf-cookie` before mutating auth calls; handle `401`/`419`/`403`/`429` per [01-authentication/UI.md](../09-modules/01-authentication/UI.md).
- No localStorage auth tokens; no fabricated permissions on the client.

## TBD

- Notifications UI placement (header bell vs. page): TBD.

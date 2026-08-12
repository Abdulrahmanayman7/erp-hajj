# Frontend Structure

> **Status:** Approved — Auth (005) + Users/Roles RBAC UI (006) + Organization UI (007) + Employees (008) + Contracts (009) + Meetings (010) + Decisions (011) + Tasks (012) + Documents (013) + Inventory (014) + Assets (015) + Notifications (016) implemented
> **Last updated:** 2026-08-11

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
│   ├── employees/                # Sprint 008 — implemented
│   ├── contracts/                # Sprint 009 — implemented
│   ├── meetings/                 # Sprint 010 — implemented
│   ├── decisions/                # Sprint 011 — implemented
│   ├── tasks/                    # Sprint 012 — implemented
│   ├── documents/                # Sprint 013 — implemented
│   ├── inventory/                # Sprint 014 — implemented (warehouses + stock UX)
│   └── assets/                   # Sprint 015 — implemented (assets + custody UX)
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

### Employees (Sprint 008 — implemented)

- `src/modules/employees/` — route `/app/employees`; sidebar **الموظفون** (`employees.view`).
- List + drawer; supervisor/user-link actions; positions catalog UX.
- Full UI contract: [04-employees-and-supervisors/UI.md](../09-modules/04-employees-and-supervisors/UI.md).

### Contracts (Sprint 009 — implemented)

- `src/modules/contracts/` — route `/app/contracts` (+ details `/app/contracts/:id`); sidebar **العقود** (`contracts.view`).
- List + drawer; lifecycle confirms; categories drawer; transition timeline on details.
- Full UI contract: [05-contracts/UI.md](../09-modules/05-contracts/UI.md).

### Meetings (Sprint 010 — implemented)

- `src/modules/meetings/` — routes `/app/meetings` (+ `/app/meetings/:id`); sidebar **الاجتماعات** (`meetings.view`).
- List + drawer; details with lifecycle, attendees, agenda, minutes, recommendations; transition timeline.
- Full UI contract: [06-meetings/UI.md](../09-modules/06-meetings/UI.md).
- Sprint 011 adds Meetings details CTA **إنشاء قرار** for eligible final recommendations (`decisions.create`) — see [07-decisions/UI.md](../09-modules/07-decisions/UI.md).

### Decisions (Sprint 011 — implemented)

- `src/modules/decisions/` — routes `/app/decisions` (+ `/app/decisions/:id`); sidebar **القرارات** (`decisions.view`).
- List + drawer; details with source recommendation/meeting + status timeline; lifecycle confirms.
- Full UI contract: [07-decisions/UI.md](../09-modules/07-decisions/UI.md).
- Sprint 012 adds Decision details **المهام المرتبطة** + إنشاء مهمة + close-gate UX — see [08-tasks/UI.md](../09-modules/08-tasks/UI.md).

### Tasks (Sprint 012 — implemented)

- `src/modules/tasks/` — routes `/app/tasks` (+ `/app/tasks/:id`); sidebar **المهام** (`tasks.view`).
- List + drawer; مهامي filter; overdue badge; lifecycle/assign/complete; Decision-linked create.
- Auth `/me` exposes optional `employee_id` for assignee self-service UX (ADR-0009).
- Full UI contract: [08-tasks/UI.md](../09-modules/08-tasks/UI.md).

### Documents (Sprint 013 — implemented)

- `src/modules/documents/` — routes `/app/documents` (+ `/app/documents/:id`); sidebar **الوثائق** (`documents.view`).
- Upload drawer; categories; entity **المستندات** widgets on Contracts/Meetings/Decisions/Tasks/Employees/Organization Units.
- Full UI contract: [09-documents/UI.md](../09-modules/09-documents/UI.md); ADR-0010.

### Warehouses & Inventory (Sprint 014 — implemented)

- `src/modules/inventory/` — routes `/app/warehouses`, `/app/inventory`, `/app/inventory/items`, `/app/inventory/movements`; sidebar **المستودعات** + **المخزون**.
- Stock quantity never in a generic editable form — dedicated receipt/issue/return/transfer/adjust dialogs.
- Full UI contract: [10-warehouses-and-inventory/UI.md](../09-modules/10-warehouses-and-inventory/UI.md); ADR-0011.

### Assets & Custodies (Sprint 015 — implemented)

- `src/modules/assets/` — routes `/app/assets`, `/app/assets/:id`, `/app/my-custodies`; sidebar **الأصول** (`assets.view`) + **عُهَدي**.
- No generic status dropdown — assign/return/maintenance/retire/lost action dialogs.
- Full UI contract: [11-assets-and-custodies/UI.md](../09-modules/11-assets-and-custodies/UI.md); ADR-0012.

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

## Notifications UI (Sprint 016 — implemented)

- Topbar **bell** + unread badge + dropdown/panel; full page `/app/notifications` (الإشعارات).
- Module folder: `frontend/src/modules/notifications/`.
- MVP realtime = TanStack polling/refetch — **no** WebSockets/Reverb requirement (ADR-0013).

## TBD

- (none for notifications placement — locked above)

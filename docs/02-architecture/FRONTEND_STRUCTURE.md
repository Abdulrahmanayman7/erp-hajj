# Frontend Structure

> **Status:** Approved (conceptual — Vue is not installed yet)
> **Last updated:** 2026-08-06

## Purpose

Define the conceptual structure of the Vue 3 frontend so both developers organize code identically. It becomes binding when the frontend is scaffolded.

## Stack

Vue 3, TypeScript, Vite, Pinia, Vue Router, TanStack Query, Tailwind CSS. Arabic RTL, right-side navigation sidebar, responsive desktop-first design.

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
│   ├── organization-structure/
│   ├── employees/
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

## Rules

- **No Axios calls directly inside presentation components** — data access lives in the module `api/` layer, consumed via TanStack Query.
- **No backend business rules duplicated in the frontend.**
- **Server state belongs in TanStack Query**; small client state may use Pinia or local state.
- **Frontend permissions are UX only** (hide/disable); backend authorization is mandatory.
- Generic components belong in `shared/`; module-specific components stay in the module.
- **RTL must be supported everywhere**; the sidebar is on the right.
- TypeScript throughout; typed API payloads per module; avoid `any`.

## TBD

- i18n library choice: TBD.
- Sanctum session mode (cookie vs. token) and its effect on guards: TBD with authentication implementation.
- Notifications UI placement (header bell vs. page): TBD.

# Frontend — ERP Hajj

> **Status:** Scaffolded — application shell and system status page only. No business UI, no authentication UI.
> **Last updated:** 2026-08-06

Vue 3 + TypeScript SPA (Vite) with Pinia, Vue Router, TanStack Query, Tailwind CSS, and vue-i18n. Arabic is the default locale, RTL is enabled, and the sidebar is on the right.

## Setup (native)

```bash
cd frontend
npm install
cp .env.example .env   # set VITE_API_URL (default: http://localhost:8000)
npm run dev
```

## Scripts

| Command | Purpose |
|---|---|
| `npm run dev` | Vite dev server |
| `npm run build` | Type-check + production build |
| `npm run test` | Vitest |
| `npm run type-check` | vue-tsc |

## Structure

Follows [docs/02-architecture/FRONTEND_STRUCTURE.md](../docs/02-architecture/FRONTEND_STRUCTURE.md): `src/app/` (router, providers, layouts), `src/modules/` (currently only `system/` with the status page), `src/shared/` (API client in `shared/api/`, styles), `src/locales/` (Arabic).

## Rules

- All data access through `shared/api` + module `api/` layers, consumed via TanStack Query — no fetch/Axios in presentation components.
- Logical CSS utilities only (`ms-*`, `me-*`, `start-*`, `end-*`) — never physical left/right utilities.
- See `.cursor/rules/02-frontend-standards.mdc` and the docs before adding anything.

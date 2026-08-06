# ERP Hajj

> **Status:** Foundation phase — repository structure and documentation only. **No application code exists yet; Laravel and Vue are not installed.**
> **Last updated:** 2026-08-06

## Project Purpose

ERP Hajj is a **multi-tenant Arabic enterprise web platform** for managing and governing **Hajj campaign companies** (شركات وحملات الحج). Each tenant is an independent campaign company with strictly isolated data.

The system connects executive management, department managers, supervisors, and employees around: users, roles and permissions, organizational structure, contracts, meetings, decisions, tasks, documents, warehouses, inventory, custodies (عُهد), assets, notifications, administrative indicators, and audit logs.

Delivery is **web only** for now; the REST API (`/api/v1`) is designed for reuse by Android and iOS applications in future phases.

## Current MVP

The MVP scope is fixed — see [docs/00-project/MVP_SCOPE.md](docs/00-project/MVP_SCOPE.md):

Authentication · Administrative dashboard · Users · Roles · Permissions · Organizational structure · Employees · Supervisors · Contracts · Meetings · Decisions · Tasks and assignments · Documents and archiving · Warehouses · Basic inventory · Custodies · Assets · MVP notifications · Audit trail · Multi-tenant foundation · Required system settings

Core workflows:

1. Meeting → Recommendation → Decision → Task(s) → Responsible → Execution → Measurement → Closure
2. Contract Draft → Review → Approval → Signature → Execution → Closure or Renewal
3. Asset → Available → Assigned as Custody → In Use → Returned → Available / Maintenance / Retired
4. Inventory: Addition → Storage → Transfer/Issue/Return → Updated Balance

Everything else (mobile apps, pilgrims, transportation, finance, AI, integrations...) is future scope — see [OUT_OF_SCOPE.md](docs/00-project/OUT_OF_SCOPE.md).

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+, Laravel 12, Sanctum, MySQL, Redis (when required), Laravel Queue, REST API `/api/v1`, Policies/Gates, Form Requests, API Resources |
| Frontend | Vue 3, TypeScript, Vite, Pinia, Vue Router, TanStack Query, Tailwind CSS |
| UI | Arabic RTL first, right-side sidebar, responsive desktop-first, light theme, deep green + gold accent |
| Architecture | Monorepo, modular monolith, API-first, multi-tenant (single DB, shared schema, `tenant_id`) |

## Repository Structure

```text
erp-hajj/
├── backend/          # Laravel application (not installed yet)
├── frontend/         # Vue application (not installed yet)
├── docs/             # Source-of-truth documentation
│   ├── 00-project/   # Vision, scope, roadmap, glossary, team & git workflow
│   ├── 01-business/  # Personas, core workflows, business rules
│   ├── 02-architecture/
│   ├── 03-database/
│   ├── 04-api/
│   ├── 05-ui-ux/
│   ├── 06-security/  # Baseline, permission model, audit trail
│   ├── 07-testing/
│   ├── 08-deployment/
│   ├── 09-modules/   # Per-module documentation (15 modules)
│   └── 10-decisions/ # ADRs
├── .cursor/rules/    # AI assistant rules enforcing project standards
├── .github/          # PR template, issue templates, workflows
├── scripts/
├── README.md
├── CHANGELOG.md
└── .editorconfig
```

## Local Setup

> **Placeholder.** Nothing is installed yet. Setup instructions will be added when the backend and frontend are scaffolded.

Planned prerequisites: PHP 8.2+, Composer, Node.js LTS, MySQL, Redis (when required). Local approach (Docker vs. native): TBD — see [ENVIRONMENTS.md](docs/08-deployment/ENVIRONMENTS.md).

## Git Workflow

Full details in [TEAM_AND_GIT_WORKFLOW.md](docs/00-project/TEAM_AND_GIT_WORKFLOW.md):

- Permanent branches: **`main`** (stable releases only, no direct push) and **`develop`** (approved integration).
- Short-lived branches: `feature|fix|chore|docs|refactor|test/<issue-number>-<short-name>`.
- Flow: Issue → Branch → Implementation → Local testing → PR into `develop` → review by the other developer → merge (squash) → delete branch.
- Releases: `develop` → `release/<version>` → `main` + tag → merge back.
- **Conventional Commits** required (`feat`, `fix`, `docs`, `test`, ...).
- A developer cannot approve their own PR.

## Documentation Entry Points

- Project: [PROJECT_VISION.md](docs/00-project/PROJECT_VISION.md) · [MVP_SCOPE.md](docs/00-project/MVP_SCOPE.md) · [OUT_OF_SCOPE.md](docs/00-project/OUT_OF_SCOPE.md) · [GLOSSARY.md](docs/00-project/GLOSSARY.md)
- Business: [USERS_AND_PERSONAS.md](docs/01-business/USERS_AND_PERSONAS.md) · [CORE_WORKFLOWS.md](docs/01-business/CORE_WORKFLOWS.md) · [BUSINESS_RULES.md](docs/01-business/BUSINESS_RULES.md)
- Architecture: [ARCHITECTURE.md](docs/02-architecture/ARCHITECTURE.md) · [MULTI_TENANCY.md](docs/02-architecture/MULTI_TENANCY.md) · [BACKEND_STRUCTURE.md](docs/02-architecture/BACKEND_STRUCTURE.md) · [FRONTEND_STRUCTURE.md](docs/02-architecture/FRONTEND_STRUCTURE.md)
- Standards: [API_STANDARDS.md](docs/04-api/API_STANDARDS.md) · [DATABASE_PRINCIPLES.md](docs/03-database/DATABASE_PRINCIPLES.md) · [PERMISSION_MODEL.md](docs/06-security/PERMISSION_MODEL.md) · [SECURITY_BASELINE.md](docs/06-security/SECURITY_BASELINE.md) · [AUDIT_TRAIL.md](docs/06-security/AUDIT_TRAIL.md) · [TESTING_STRATEGY.md](docs/07-testing/TESTING_STRATEGY.md)
- Modules: [docs/09-modules/](docs/09-modules/) — per-module business rules, permissions, API, data model, UI, acceptance criteria, test plans
- Decisions: [docs/10-decisions/](docs/10-decisions/)

## Contribution Rules

- **Scope is fixed.** No features outside the MVP; no invented requirements. Scope changes go through a Change Request issue.
- Read the relevant module docs in `docs/09-modules/` before implementing.
- Follow the layering: thin controllers, Form Requests, Actions, Services (reusable only), Policies, API Resources; repository pattern only where it provides real value.
- **Tenant isolation is non-negotiable** — automatic scoping and cross-tenant tests on every tenant-owned module.
- Every critical operation is audited; no module bypasses permissions or audit.
- Arabic RTL first in all UI work.
- Update the relevant `docs/` files in the same PR when behavior or decisions change; unresolved points are marked **TBD**.

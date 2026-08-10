# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Sprint 012 — Tasks & Assignments specification** (documentation only — no application code): Task entity with `TSK-######` sequences; nullable `decision_id` (Tasks own FK; standalone allowed); single Employee assignee + assignment history; lifecycle `draft` → `assigned` → `in_progress` → `completed` (+ cancel); derived overdue; progress % + required completion_notes; Decision close gate when open linked Tasks exist; limited assignee self-service via User↔Employee; permissions `tasks.*`; Decision details Tasks section UX; ADR-0009; full module docs under `docs/09-modules/08-tasks/`.

- **Sprint 011 — Decisions vertical slice:** tenant-owned `decisions` (+ `decision_number_sequences`) + append-only `decision_status_transitions` (correlation ID); concurrency-safe `DEC-######` via `SELECT … FOR UPDATE`; standalone and final-recommendation conversion decisions (unique `source_recommendation_id`); controlled draft → pending approval → approved → closed lifecycle, with early cancel and return-to-draft; draft-only hard delete; Policies + seeded `decisions.*` catalog + role templates; APIs under `/api/v1/decisions`; Vue `decisions` module (list, drawer, details, lifecycle timeline/actions); Meetings **إنشاء قرار** conversion affordance; Pest + Vitest coverage.

- **Sprint 011 — Decisions specification** (superseded by implementation above).

- **Sprint 010 — Meetings vertical slice:** tenant-owned `meetings` (+ `meeting_number_sequences`) + append-only `meeting_status_transitions` (correlation ID); concurrency-safe `MTG-######` via `SELECT … FOR UPDATE`; employee-only attendees + attendance statuses; structured `meeting_agenda_items`; `minutes_body` text; first-class `meeting_recommendations` (`draft`/`final`, ADR-0007); Workflow 1 lifecycle action endpoints (schedule/reschedule/start/complete/cancel); draft-only hard delete; Documents/Notifications/Decisions/Tasks deferred; Policies + `meetings.*` catalog + role templates; APIs under `/api/v1/meetings` (+ attendees/agenda/recommendations nested); Vue `meetings` module (list, drawer, details, lifecycle/minutes/attendees/agenda/recommendations UX); sidebar الاجتماعات; Pest + Vitest green.

- **Sprint 010 — Meetings specification** (superseded by implementation above).

- **Sprint 009 — Contracts vertical slice:** tenant-owned `contract_categories` (ADR-0006, active/inactive) + `contracts` (+ `contract_number_sequences`) + authoritative append-only `contract_status_transitions` (correlation ID); concurrency-safe `CTR-######` via `SELECT … FOR UPDATE`; counterparty free-text + optional employee/org links; Workflow 2 lifecycle action endpoints; **manual sign attestation** (not e-sign); **one-child transactional renew** (`CONTRACT_ALREADY_RENEWED`); expiry command + expiring-soon filter; draft-only hard delete; informational value/SAR; attachments deferred; notification hooks only; Policies + `contracts.*` catalog + role templates; APIs under `/api/v1/contracts` and `/api/v1/contract-categories`; Vue `contracts` module (list, drawer, lifecycle actions, categories manager, details timeline); sidebar العقود; Pest + Vitest green.

- **Sprint 009 — Contracts specification** (superseded by implementation above).

- **Sprint 008 — Employees + Supervisors vertical slice:** tenant-owned `positions` + `employees` (+ `employee_number_sequences`); `Employee`/`Position` models with `TenantOwned` + `UsesTenantScope`; concurrency-safe `EMP-######` via `SELECT … FOR UPDATE`; optional 1:1 User link; required org unit; optional position; `supervisor_id` with cycle prevention; activate/deactivate (no DELETE employee); Policies + `employees.*`/`positions.*` catalog + role templates; APIs under `/api/v1/employees` and `/api/v1/positions`; audit events via `AuthorizationSecurityEvent`; Vue `employees` module (list, drawer, supervisor/user dialogs, positions manager); sidebar الموظفون; Pest (16) + Vitest green. No payroll/HR/national ID/contracts/tasks.

- **Sprint 008 — Employees + Supervisors specification** (superseded by implementation above).

- **Sprint 007 — Organization Structure vertical slice:** tenant-owned `organization_units` (adjacency list; ADR-0004); types `department|section|unit`; status active/inactive; immutable tenant-scoped codes; optional unit manager (`manager_user_id`); app max depth via `config/organization.php`; cycle/depth validation; Actions + Policy + APIs (`/api/v1/organization-units` tree/flat, move, activate/deactivate, delete); `organization_units.*` catalog + role templates; Vue `organization` module (tree + details, drawer, move/lifecycle confirms); sidebar الهيكل التنظيمي; Pest + Vitest green. No Employees/positions/user membership; no soft deletes.

- **Sprint 007 — Organization Structure specification** (superseded by implementation above).

- **Sprint 006 hardening:** operational `php artisan tenant:bootstrap-owner` (interactive, no password argv, reuses catalog sync + role provisioning, last-owner verification); `AUTHORIZATION_DENIED` maps only Policy/`AuthorizationException` denials (no longer collapses unrelated 403s); Pest coverage for bootstrap + 403 mapping regressions.

- **Sprint 006 — Users & Authorization (RBAC) vertical slice:** global `permissions` catalog + tenant-owned `roles` + pivots `user_roles` / `role_permissions`; hand-rolled `App\Core\Authorization` (effective permissions + `TenantCache`, GrantAuthority subset rule, TenantOwnerGuard); Policies for users/roles; APIs `/api/v1/users|roles|permissions`; `/auth/me` additive `roles` + sorted `permissions`; idempotent catalog sync + tenant role provisioning (`RbacSeeder`); Vue modules `users`/`roles` with drawer UX, permission matrix, `can()`/`PermissionGuard`, sidebar, `/app/403`; Pest + Vitest green. No Spatie; no direct user permissions; no employee fields; no hard-delete users.

- **Sprint 006 — Users & Authorization (RBAC) specification** (superseded by implementation above).

- Authentication vertical slice (Sprint 005): `users.status` (`active`/`disabled`); Sanctum SPA cookie login/logout/`/auth/me`; forgot/reset password with frontend reset URLs; login rate limit 5/min; remember-me; tenant lifecycle gates; `EnsureUserIsActive`; correlation ID middleware; `AuthSecurityEvent` sink (log) until Audit module; Vue auth module (`/login`, `/forgot-password`, `/reset-password`, `/access-blocked`, `/app` shell); TanStack Query current-user + guards; Pest + Vitest suites green.
- Authentication module **specification** for Sprint 005 (superseded by implementation above).
- Tenant Foundation core implementation (backend): migrations for `tenants`, `users.tenant_id`, and `tenant_settings` with the documented indexes, uniques, and `ON DELETE RESTRICT` FKs; first tenant رفيع/`rafee` seeded (`ar` / `Asia/Riyadh` / `active`); `TenantStatus` enum with the documented transition matrix; `Tenant` and `TenantSetting` models (immutable lowercase `tenant_code`, no soft deletes); `TenantContext`/`PlatformContext` scoped singletons with `runAsTenant` restoration and the five documented exceptions; `TenantResolver` + `AuthenticatedUserTenantResolver`; `ResolveTenantContext` and `EnsureTenantIsActive` middleware with stable error codes (`TENANT_CONTEXT_MISSING`/`INVALID`, `TENANT_PENDING`/`SUSPENDED`/`ARCHIVED`); fail-closed `TenantScope` + `TenantOwned` + `UsesTenantScope` (auto tenant_id, forge-proof, immutable); `TenantExists`/`TenantUnique` validation rules; queue propagation (`TenantAware` trait + `RestoreTenantContext` job middleware with release/cancel behavior); `TenantCache` (`tenant:{id}:*` / `platform:*` namespaces with version-stamp invalidation); `TenantStorage` (`tenants/{id}/{documents,contracts,employees,assets,exports,temp}`); uniform API 404 envelope (enumeration defense); and 99 passing Pest tests covering contexts, resolver, middleware, scoping, binding, validation, jobs, cache, and storage.
- Engineering standards set: `docs/02-architecture/ENGINEERING_PRINCIPLES.md`, `CODING_STANDARDS.md`, `MODULE_TEMPLATE.md`, `REVIEW_CHECKLIST.md`, and `docs/00-project/DEFINITION_OF_DONE.md`; Cursor rules updated to enforce them.
- Tenant Foundation implementation specification (documentation only — no code): tenant identity (`tenant_code`, first tenant رفيع/`rafee`), four-status lifecycle (`pending`/`active`/`suspended`/`archived`) with an explicit transition graph, `TenantContext` + `PlatformContext` (no public tenancy bypass), `TenantResolver` abstraction, `ResolveTenantContext`/`EnsureTenantIsActive` middleware separation with stable error codes, `TenantOwned`/`UsesTenantScope` model mechanism, tenant-scoped validation builders, queue/scheduler/cache/storage/notification/export isolation, mandatory correlation ID, and a complete Pest test matrix.

- Backend scaffolding: Laravel 12 installed in `backend/` with Sanctum (SPA cookie mode, `statefulApi()` + CORS restricted to the SPA origin), Pest replacing PHPUnit, and Pint. Standardized response envelope helper (`App\Core\Shared\ApiResponse`), first endpoint `GET /api/v1/health` (`App\Core\Health\HealthController`), and Pest feature tests for the envelope.
- Frontend scaffolding: Vue 3 + TypeScript (Vite) installed in `frontend/` with Pinia, Vue Router, TanStack Query, Tailwind CSS 4, vue-i18n (Arabic default), and Vitest. RTL app shell with right-side sidebar and header, system status page consuming the health endpoint, and a shared API client (`src/shared/api/http.ts`) implementing the standardized envelope with tests.
- Continuous integration: `.github/workflows/ci.yml` running backend (composer validate, Pint, Pest) and frontend (Vitest, type-check, build) jobs with dependency caching on push/PR to `develop` and `main`.

### Changed

- Scaffolding decisions recorded in docs: native local development (no Docker), Sanctum SPA cookie session, vue-i18n as the i18n library, single `routes/api.php` with `v1` prefix, PSR-4 under `App\Core`/`App\Modules`.

- Documentation populated and refined to implementation-ready state: MVP scope expanded to the approved 21-module list; out-of-scope list completed from the long-term vision; eight default personas documented; four core workflows documented (governance chain with recommendation and closure stages, contract lifecycle, asset/custody lifecycle, inventory transactions).
- Multi-tenancy strategy decided and ADR-0003 accepted: single application, single database, shared schema with `tenant_id`, automatic scoping, tenant context from authenticated user only.
- API standards finalized: standardized success/error envelope (`success`, `message`, `data`, `meta` / `errors`, `code`), action endpoints for workflow transitions.
- Backend structure defined (Core/Tenancy-Auth-Audit-Shared-Support + 16 modules); frontend structure defined (app/modules/shared layout).
- Design guidelines finalized: light theme, deep green primary, gold accent, shared component catalog.
- Team and Git workflow documented: main/develop branching, Conventional Commits, issue and PR quality requirements, two-developer ownership model.
- Cursor rules updated with implementation guardrails and completion-report requirement.

### Added

- Permission model catalog (`docs/06-security/PERMISSION_MODEL.md`) using `module.action` naming.
- Team and Git workflow document (`docs/00-project/TEAM_AND_GIT_WORKFLOW.md`).
- Per-module documentation for 15 modules under `docs/09-modules/` (README, business rules, permissions, API, data model, UI, acceptance criteria, test plan per module).
- Repository foundation: monorepo directory structure (`backend/`, `frontend/`, `docs/`, `.cursor/rules/`, `.github/`, `scripts/`).
- Project documentation set under `docs/` (vision, MVP scope, out of scope, roadmap, glossary, business workflows and rules, architecture, multi-tenancy, database principles, API standards, design guidelines, security baseline, audit trail, testing strategy, environments).
- Architecture Decision Records: ADR-0001 (monorepo), ADR-0002 (modular monolith), ADR-0003 (multi-tenancy from day one).
- Cursor rules enforcing project context, backend/frontend standards, multi-tenancy, security, testing, documentation, and scope control.
- GitHub pull request template and issue templates (Feature, Bug, Technical Task, Change Request).
- Root `README.md`, `CHANGELOG.md`, and `.editorconfig`.

> Note: Tenant Foundation (004), Authentication (005), Users & Authorization / RBAC (006), Organization Structure (007), Employees + Supervisors (008), Contracts (009), and Meetings (010) are implemented.

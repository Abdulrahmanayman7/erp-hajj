# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Pull-to-Refresh (mobile/tablet):** global gesture on `AppLayout` / `PlatformLayout` — pull at top of the shell scroller to soft-refresh active TanStack Query data (no full page reload); progressive Arabic indicator; blocked during drawers/modals/inputs.

- **Local performance dataset dump:** `php artisan performance:seed --confirm-perf --rebuild --dump-sql=erp_hajj_perf/erp_hajj_perf.sql` writes an isolated `erp_hajj_perf` database plus a gitignored `.sql` dump (medium profile: ~75k tasks, ~150k movements, ~500k audit rows). Docs: `docs/07-testing/PERFORMANCE_DATASET.md`.

- **Form phone, number, and Enter:** phone fields use a country-code picker (default +966) stored as one E.164 string; numeric fields no longer show spinner arrows; opening a create/edit form autofocuses the first field; Enter submits the nearest form (textarea stays newline).

- **Create/edit drawers (mobile + tablet):** bottom sheet with max ~92vh on phones (no full-page stretch under the bottom nav); floating inset side panel on tablet; inner form scroll + sticky actions; drawer z-index above bottom nav.

- **Responsive UX QA (Phase 1.5):** tablet filters use sheet (not compressed desktop toolbar); mobile header lean (title + notifications + avatar); Quick Action Escape + safe-area; Dashboard greeting/KPI density polish; Tasks apply-count CTA; touch targets ≥44px on shell chrome.
- **Visual design rebuild (Phase 1.6):** semantic surface/radius/shadow/typography tokens; calm AppShell (light tablet rail, native bottom nav, brand mobile topbar); Dashboard intro + status + compact KPI rebuild; Tasks segmented control + mobile card hierarchy + filter sheet polish. No additional module migration.
- **AppShell viewport height fix:** lock `html/body/#app` height chain so sidebar and footer fill the viewport instead of leaving empty space under chrome on detail pages.

- **PWA install experience hardening:** global RAFEEA icons (transparent `any` + brand-green maskable), improved `manifest.webmanifest` (standalone/RTL), iOS meta, locked mobile viewport + 16px input anti-focus-zoom, standalone detection (`html.is-standalone`), `usePwaInstall` + soft install card (More sheet / Platform mobile), safer shell SW (`erp-hajj-shell-v2`: network-first navigations, cache-first hashed assets, never cache `/api/*`). Docs: `docs/08-deployment/PWA.md`.

- **Platform Tenant Management (no-code provisioning):** Platform RBAC (`platform_roles` / `platform_role_permissions` / `platform_user_roles`); First Setup (`/setup` + `/api/v1/platform/setup*`); `ProvisionTenant` + lifecycle activate/suspend/archive; Ownership V1 transfer; Platform UI wizard; Organization Tree projection (`GET /api/v1/organization-tree` + topbar shortcut). Docs: `docs/08-deployment/HOSTINGER_FIRST_SETUP.md`.

- **Tenant SMTP configuration (Technical Settings):** nullable `tenants.mail_*` SMTP columns (`mail_password` encrypted); Settings UI for host/port/encryption/username/password + From identity; `POST /api/v1/tenant-settings/test-email`; isolated per-send mailer via `MailManager::build()` (no global `Config::set`); invitations and password-reset use tenant SMTP when complete, else server fallback; `log`/`array` are not deliverable. ADR-0016 §6 amended.

- **Tenant email sender settings (Technical):** `tenants.mail_from_address` / `mail_from_name` via Settings API group `technical`; UI section «إعدادات البريد الإلكتروني»; invite/password-reset `MailMessage->from()` resolves tenant values with fallback to `config('mail.from.*')`.

- **Change Request draft (awaiting approval):** PWA + Web Push background notifications — see `docs/00-project/CHANGE_REQUEST_PWA_WEB_PUSH.md`. No PWA/push code until approved.
- **User create invite feedback:** API returns `invite_sent` + `invite_code` (`INVITE_MAILER_UNAVAILABLE` | `INVITE_SEND_FAILED`); UI forces manual temporary password when invite mail is unavailable; Hostinger SMTP ops note in `docs/08-deployment/HOSTINGER_SMTP.md`.
- **App chrome:** topbar back + soft refresh; press-and-hold 5s for hard reload; persistent tablet sidebar expand/collapse edge handle.
- **Responsive lists:** card layouts through `lg` breakpoint; tables from `lg+`.
- **Login:** tighter mobile/tablet viewport fit without page scroll (short-height compact styles).
- **Permissions catalog:** Arabic `description` strings for Role Permissions UI.

### Fixed

- **Hostinger co-located routing:** `frontend/public/.htaccess` (and `deploy/hostinger/public/.htaccess`) permanently prefer `DirectoryIndex index.html index.php`, route `/api/*` and `/sanctum/*` to Laravel `index.php`, fall other non-file routes to Vue `index.html`, and keep PWA Cache-Control headers.

- **Entity details mobile layout:** detail pages (assets, tasks, meetings, contracts, decisions, documents, warehouses, inventory items) keep lifecycle actions above the summary on phones, use single-column field grids until tablet, convert nested tables to card stacks, and clip horizontal shell overflow so pages like `/app/assets/:id` no longer scroll sideways.

- **Meeting schedule calendar:** date/time popover stacks above lifecycle dialogs (`z-490` vs dialog `z-460`) so the Gregorian picker is no longer visible behind the modal.

- **Decisions create/edit employee fields:** issuer (`جهة الإصدار`) and responsible (`المسؤول عن المتابعة`) now have distinct labels and empty states; they no longer look like a duplicated employee picker.

- **Asset create/edit employee:** optional employee picker on the asset drawer assigns custody via existing `POST /assets/{id}/assign` after save (not an Asset field). In-use holder is read-only.

- **Task completion timestamp:** outcome `completed_at` (and task timeline times) render as Gregorian Arabic 12-hour time in the tenant timezone instead of raw UTC ISO.

- **Topbar refresh on mobile/tablet:** tap soft-refreshes queries; press-and-hold 5s hard-reloads. Touch `pointercancel` no longer aborts the hold (iOS long-press).

- **Decisions list layout:** desktop filters split into a compact toolbar + labeled date-range row so date fields no longer stretch full-width; Gregorian date popover stays a fixed calendar width instead of matching a stretched trigger.

- **Mobile chrome / datetime picker:** 12-hour time with صباحاً/مساءً in `AppDateTimeInput`; PWA `theme_color` and standalone body match the light surface so the iOS home-indicator band is not brand-green; mobile/tablet brand mark crops the padded logo and shows the رفيع wordmark; tablet nav rail shows labels, a larger gold-ring logo, and a 96px professional column; compact 49px bottom nav so icons sit in the tab row instead of floating above extra white padding.

- **Local CORS localhost vs 127.0.0.1:** browsers treat these as distinct origins. CORS now allows `FRONTEND_URL`, optional `CORS_ALLOWED_ORIGINS`, and the loopback twin of `FRONTEND_URL`, so Vite at `http://localhost:5173` works when the API was configured with `http://127.0.0.1:5173` (and the reverse). Still never `*` with credentialed cookies.

### Changed

- **PWA update strategy:** centralized `PWA_ASSET_VERSION` (`4`) with `?v=` cache-busting on manifest/icon/HTML links; shell cache `erp-hajj-shell-v4`; `updateViaCache: "none"` + visibility `registration.update()`; SW never caches manifest/icons; Hostinger/Vercel Cache-Control for `index.html` / `sw.js` / manifest / hashed assets. Docs clarify OS launcher icons may still require uninstall/reinstall. In-app `rafeea-logo.png` unchanged.

- **PWA install icon:** global install/home-screen icons regenerated from `frontend/src/assets/brand/rafeea-app-icon.png` (any + maskable + apple-touch + favicon). In-app UI logo (`rafeea-logo.png`) unchanged. Service worker cache bumped to `erp-hajj-shell-v3`.

- **Dashboard overview KPIs:** overview cards (`نظرة عامة`) show value and label only — decorative icons removed.

- **RC 0.1.0 P1 closure attempt (2026-08-13):** Settings timezone Cairo↔Riyadh live API PASS; Chrome route matrix mostly PASS (host resource failures on some desktop navigations); Firefox Playwright launch FAIL; decision-close gate PASS; sustained queue worker ran with empty job queue (insufficient for fanout PASS). Staging/host smoke, host PHP limits, and Product/Owner sign-off remain **OPEN** — **not release-ready**.

- **RC 0.1.0 final validation (2026-08-13):** local Edge browser/responsive/role UAT; disposable MariaDB backup+restore (DB + private document blob); queue `--once` + scanners; frontend `nanoid` advisory cleared via lockfile bump. Staging/host smoke, Firefox, complete Chrome UAT, and Product/Owner sign-off remain open — **not release-ready**.

- **RC 0.1.0 UAT validation (2026-08-13):** disposable MariaDB `erp_hajj_release_test` fresh migrate + idempotent double seed; full Pest 382×2; Vitest 215; composer/npm audits clean; scanners exercised; TenantScope SQL-quote assertion made driver-agnostic; `phpunit.xml` forces SQLite DB env so shell MySQL credentials cannot override Pest. Interactive browser UAT + backup restore drill + production smoke remain open — release **not** tagged.

### Added

- **Sprint 020 — System Settings vertical slice:** typed tenant settings (ADR-0016) over existing `tenants` columns (`name`, contacts, `timezone`; `locale` read-only `ar`); `GET|PATCH /api/v1/tenant-settings`; `TenantSettingsResolver`; Policies/Gates on existing `tenant_settings.view|update`; audit `TENANT_SETTINGS_UPDATED`; Vue `/app/settings` (الإعدادات); no new migration / no KV product keys / no thresholds/currency/logo; Pest `TenantSettingsTest` 17/17; full Pest 382; Vitest 215; `tsc`/build green.

- **Sprint 020 — System Settings specification** (superseded by implementation above).

- **Sprint 019 — MVP Integration & Release Hardening:** architecture/tenant/RBAC release audit; health endpoint no longer leaks `APP_ENV`; CORS exposes `X-Correlation-ID`; `.env.example` documents session secure cookies, document disk, queue/scheduler, PHP upload limits; `frontend/vercel.json` SPA rewrites; CI advisory `composer audit` / `npm audit`; PositionFactory uniqueness hardening; deployment docs (`DEPLOYMENT`, `BACKUP_AND_RECOVERY`, `MVP_RELEASE_CHECKLIST`), `MVP_UAT_CHECKLIST`, `MVP_KNOWN_LIMITATIONS`. Distinguishes: MVP code-ready (004–018) · UAT pending · production deployment pending. System settings API/UI remains P1 incomplete for full 21-module scope.

- **Sprint 018 — Audit Trail vertical slice:** hybrid append-only `audit_logs` (ADR-0015); `Core/Audit` recorder/sanitizer dual-writes from existing `AuthorizationSecurityEvent` / `AuthSecurityEvent` (log sinks kept); `GET /api/v1/audit-logs` (+ show) with `audit_logs.view`; Owner/GM/Auditor grants; Vue `modules/audit` (`/app/audit`); no export/pruning/hash-chain/observers; Pest `AuditTest` 13/13; full Pest 365; Vitest 208; `tsc`/build green.

- **Sprint 018 — Audit Trail specification** (superseded by implementation above).

- **Sprint 017 — Dashboard vertical slice:** permission-aware operational read model (ADR-0014); `GET /api/v1/dashboard` with Gate `viewDashboard` (`dashboard.view` + per-section `*.view`); sparse omit-unauthorized KPIs/Attention/Today/Work/Resources; unread notifications recipient-owned; no migrations/cache/charts/cross-UOM sums; Vue `modules/dashboard` replaces `/app` home (+ `/app/dashboard` redirect); Pest `DashboardTest` 11/11; full Pest 352; Vitest 199; `tsc`/build green.

- **Sprint 017 — Dashboard specification** (superseded by implementation above).

- **Sprint 016 — Notifications vertical slice:** tenant-owned `notifications` (immutable except `read_at`; `UNIQUE(tenant_id, dedupe_key)`); recipient = User only (Employee→User skip if missing/disabled); curated types (contracts/meetings/decisions/tasks/custody/`STOCK_BELOW_MINIMUM`); `NotificationDispatcher` after-commit + fanout queue (`DispatchNotificationsJob` + `TenantAware`); scanners `notifications:scan-*` + schedule; domain hooks in Contracts/Meetings/Decisions/Tasks/Assets/Inventory; recipient-owned APIs (`list`/`unread-count`/`show`/`read`/`read-all`) with **no** `notifications.*` PermissionCatalog entries and **no** `action_url`; Vue `notifications` module (bell + `/app/notifications`, deep-link map, ~60s polling); Pest `NotificationTest` 13/13; full Pest 341; Vitest 192; `tsc`/build green. No email/SMS/push/WebSockets/preferences/mark-unread.

- **Sprint 016 — Notifications specification** (superseded by implementation above).
- **Sprint 015 — Assets & Custodies vertical slice:** tenant-owned `asset_categories` + `assets` (+ `asset_number_sequences`) + append-only `asset_status_transitions` + `asset_custodies` (+ custody sequences); concurrency-safe `AST-######` / `CUS-######` via Asset `SELECT … FOR UPDATE`; statuses `available|in_use|maintenance|damaged|retired|lost`; single active custody; assign/return with `next_status`; optional home warehouse + org unit metadata; no inventory auto-post / `inventory_item_id`; Documents morph aliases `asset` / `custody` + delete guard; Policies + seeded `assets.view|create|update|delete|assign|return|retire` via `PermissionCatalog`; holder self-view (ADR-0012); APIs under `/api/v1/assets`, `/asset-categories`, `/asset-custodies`, `/my-custodies`; Vue `assets` module (list, details, assign/return/lifecycle dialogs, categories, عُهَدي); Pest `AssetTest` 20/20 + Vitest assets 15/15 + `tsc`/build green. No Procurement, maintenance work-orders, depreciation, or custody void/correction.

- **Sprint 015 — Assets & Custodies specification** (superseded by implementation above).

- **Sprint 014 — Warehouses & Inventory vertical slice:** tenant-owned `warehouses` (+ `warehouse_number_sequences`) + `inventory_categories` + `inventory_items` (+ item sequences) + materialized `inventory_balances` + append-only `inventory_movements` (+ movement sequences); concurrency-safe `WH-######` / `ITM-######` / `MOV-######` via `SELECT … FOR UPDATE`; stock actions receipt/opening/issue/return/atomic transfer/adjustment; no negative stock; balance locker with deterministic transfer lock order (ADR-0011); Documents morph aliases `warehouse` / `inventory_item`; Policies + seeded `warehouses.*` / `inventory.*` (+ `manage_items`); APIs under `/api/v1/warehouses`, `/inventory-categories`, `/inventory-items`, `/inventory/balances|movements|receipts|issues|returns|transfers|adjustments`; Vue `inventory` module (warehouses, balances, items, movements, stock dialogs); Pest 30 + Vitest 13 green. No Procurement, multi-UOM, lot/serial, multi-step transfers, Assets/Custodies auto-posting.

- **Sprint 014 — Warehouses & Inventory specification** (superseded by implementation above).

- **Sprint 013 — Documents & Archiving vertical slice:** tenant-owned `documents` (+ `document_number_sequences`, `document_categories`); concurrency-safe `DOC-######` via `SELECT … FOR UPDATE`; private storage via `TenantStorage::DOCUMENTS` (`tenants/{id}/documents/{uuid}.ext`); original filename metadata only; finfo MIME/extension allow-list + 20 MiB; SHA-256 checksum (duplicates allowed); optional single morph link (Contract/Meeting/Decision/Task/Employee/Organization Unit); lifecycle `active`/`archived` + hard delete; host hard-delete guards (`DOCUMENT_ENTITY_IN_USE`); Policies + seeded `documents.*` catalog + role templates; APIs `/api/v1/documents` (+ download/archive/restore) and `/api/v1/document-categories`; Vue `documents` module (list, upload drawer, details, categories, host **المستندات**); Pest + Vitest green. No versioning, multi-link pivot, confidentiality levels, malware scanner, OCR, or public URLs (ADR-0010).

- **Sprint 013 — Documents & Archiving specification** (superseded by implementation above).

- **Sprint 012 — Tasks & Assignments vertical slice:** tenant-owned `tasks` (+ `task_number_sequences`) + append-only `task_status_transitions` / `task_assignment_history`; concurrency-safe `TSK-######` via `SELECT … FOR UPDATE`; standalone and Decision-linked Tasks (`decision_id` nullable; create only when Decision `approved`); single Employee assignee; lifecycle draft → assigned → in_progress → completed (+ cancel); derived overdue; progress 0–100; required `completion_notes` on complete; Decision close gate `DECISION_CLOSE_NOT_ALLOWED` when open linked Tasks exist; assignee self-service (view/start/progress/complete) via User↔Employee + `tasks.view` (ADR-0009); Policies + seeded `tasks.*` catalog + role templates; APIs under `/api/v1/tasks`; Vue `tasks` module (list, drawer, details, lifecycle, Decision **المهام المرتبطة**); Auth `/me` `employee_id`; Pest + Vitest green. No Documents/Notifications/Dashboard KPIs.

- **Sprint 012 — Tasks & Assignments specification** (superseded by implementation above).

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

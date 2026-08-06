# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

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

> Note: no application code exists yet. Laravel and Vue are not installed; no database migrations have been created.

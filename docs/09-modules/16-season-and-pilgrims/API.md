# Season and Pilgrim Foundation — API

> **Status:** Implemented (backend). Base `/api/v1`, inside `auth:sanctum` + `user.active` + `tenant.active`.
> **Last updated:** 2026-10-05

Binding: [API_STANDARDS.md](../../04-api/API_STANDARDS.md). Envelope, Form Requests, API Resources, Policies. `tenant_id` and `season_id` are never accepted from payloads.

## Season Context

Season-scoped routes use the `season.context` middleware:

- Header `X-Season-Id` (config `season_pilgrims.season_context_header`) selects a season. It is resolved **through the tenant-scoped Season query**, so another tenant's id is indistinguishable from a nonexistent one → `404`.
- No header → the tenant's Active season. No Active season → `422 SEASON_CONTEXT_MISSING`. Malformed header → `422 SEASON_CONTEXT_INVALID`.
- A bound season-owned record (`{season_pilgrim}`, `{season_program}`, …) that belongs to a different season than the context → `404`.
- Draft can be selected explicitly (preparation). Closed can be selected and read; every write returns `422 SEASON_CLOSED`, except `POST /season-pilgrims/{id}/snapshot/correct` (permission + mandatory reason + audit; the season stays Closed).
- Context is cleared after each request. Queued jobs use the `SeasonAware` trait (captures tenant + season at dispatch; restored by job middleware).

## Endpoints

### Tenant-level

| Method | Path | Permission |
|---|---|---|
| GET/POST | `/seasons` | `seasons.view` / `seasons.create` |
| GET/PATCH/DELETE | `/seasons/{season}` | `seasons.view` / `seasons.update` / `seasons.delete` |
| POST | `/seasons/{season}/activate` · `/close` · `/reopen` (reason) | `seasons.activate` / `seasons.close` / `seasons.reopen` |
| GET | `/seasons/{season}/closing-readiness` | `seasons.view` |
| GET/POST | `/pilgrims` (`?search=` name or exact normalized document number) | `pilgrims.view` / `pilgrims.create` |
| GET/PATCH/DELETE | `/pilgrims/{pilgrim}` | `pilgrims.view` / `pilgrims.update` / `pilgrims.delete` |
| POST | `/pilgrims/{pilgrim}/identity-documents` | `pilgrims.manage_identity_documents` |
| PATCH/DELETE | `/pilgrim-identity-documents/{identity_document}` | `pilgrims.manage_identity_documents` |

### Season-scoped (`season.context`)

| Method | Path | Permission |
|---|---|---|
| GET | `/season-context` | `seasons.view` |
| GET/POST | `/season-pilgrims` | `season_pilgrims.view` / `season_pilgrims.register` |
| GET/DELETE | `/season-pilgrims/{season_pilgrim}` | `season_pilgrims.view` / `season_pilgrims.delete` |
| POST | `/season-pilgrims/{id}/activate` · `/complete` · `/cancel` (reason) | `season_pilgrims.activate` / `.complete` / `.cancel` |
| POST | `/season-pilgrims/{id}/snapshot/refresh` · `/snapshot/correct` (reason) | `season_pilgrims.refresh_snapshot` / `.correct_snapshot` |
| PUT | `/season-pilgrims/{id}/program` | `season_pilgrims.change_program` |
| PUT | `/season-pilgrims/{id}/pilgrim-group` | `season_pilgrim_groups.assign_members` |
| PUT | `/season-pilgrims/{id}/family-group` | `season_family_groups.assign_members` |
| GET/POST, GET/PATCH/DELETE | `/season-programs`, `/season-programs/{season_program}`; POST `…/activate` · `…/deactivate` | `season_programs.view` / `.manage` / `.delete` |
| GET/POST, GET/PATCH/DELETE | `/season-family-groups`, `/season-family-groups/{family_group}`; PUT `…/primary-member` | `season_family_groups.view` / `.manage` |
| GET/POST, GET/PATCH/DELETE | `/season-pilgrim-groups`, `/season-pilgrim-groups/{pilgrim_group}` | `season_pilgrim_groups.view` / `.manage` |
| POST | `/season-pilgrim-groups/{pilgrim_group}/supervisors`; `…/supervisors/{supervisor}/end` | `season_pilgrim_groups.manage_supervisors` |

## Error behavior

| Case | HTTP |
|---|---|
| Unauthenticated | 401 |
| Missing permission | 403 |
| Other tenant's id / other season's record / other tenant's season header | 404 |
| Validation / invariant failure | 422 with a stable `code` |

Stable codes (selection): `SEASON_ACTIVE_EXISTS`, `SEASON_CODE_TAKEN`, `SEASON_HAJJ_YEAR_TAKEN`, `SEASON_INVALID_TRANSITION`, `SEASON_CLOSED`, `SEASON_NOT_ACTIVE`, `SEASON_DELETE_FORBIDDEN`, `SEASON_CLOSING_BLOCKED`, `SEASON_CONTEXT_MISSING`, `SEASON_CONTEXT_INVALID`, `PILGRIM_IDENTITY_DOCUMENT_DUPLICATE`, `PILGRIM_IN_USE`, `SEASON_PILGRIM_ALREADY_REGISTERED`, `SEASON_PILGRIM_INVALID_TRANSITION`, `SEASON_PILGRIM_TERMINAL`, `SEASON_PILGRIM_ACTIVATION_REQUIREMENTS_NOT_MET`, `SEASON_PILGRIM_GROUP_LOCKED`, `SEASON_PILGRIM_SNAPSHOT_LOCKED`, `SEASON_PILGRIM_SNAPSHOT_NOT_LOCKED`, `SEASON_PILGRIM_DELETE_FORBIDDEN`, `SEASON_PROGRAM_INACTIVE`, `SEASON_PROGRAM_IN_USE`, `SEASON_FAMILY_MEMBER_INVALID`, `PILGRIM_GROUP_PRIMARY_SUPERVISOR_REQUIRED`, `PILGRIM_GROUP_SUPERVISOR_DUPLICATE`, `SEASON_RESOURCE_REFERENCED`.

## Cross-domain

No endpoint writes transportation movements or accommodation assignments. Future modules integrate through container-tagged contracts only:

- `season_pilgrims.closing_readiness` → `SeasonClosingReadinessContributor` (OK / WARNING / BLOCKING; BLOCKING prevents close).
- `season_pilgrims.activation_requirements` → `SeasonPilgrimActivationRequirement`.
- `season_pilgrims.operational_reference_checkers` → `SeasonPilgrimOperationalReferenceChecker` (blocks hard delete).
- `season_pilgrims.deletion_reference_checkers` → `SeasonResourceDeletionChecker` (blocks hard delete of a Season, Program, Pilgrim Group, or Family Group with `422 SEASON_RESOURCE_REFERENCED`; never blocks deactivation).
- Event `SeasonPilgrimCancelled` (dispatched after commit; scalar payload; Transportation listens — ADR-0018).

Registered contributors: Transportation (ADR-0018).

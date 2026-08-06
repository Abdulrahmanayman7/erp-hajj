# Coding Standards

> **Status:** Approved
> **Last updated:** 2026-08-06

Binding naming and style rules for both developers and Cursor. Formatting is enforced by tooling (Pint for PHP; the frontend build/type-check pipeline for TS); this document covers what tooling cannot decide.

## PHP / Laravel

### Language rules

- PHP 8.2+; **declare parameter, return, and property types everywhere** (no untyped signatures in new code).
- **Strict comparison** (`===` / `!==`) always; no loose `==` in new code.
- **Native PHP enums** for closed value sets (statuses, classifications) — e.g. `TenantStatus`; string-backed when persisted.
- Constructor property promotion where it improves clarity; `readonly` where state must not change.
- No `mixed` unless genuinely unavoidable and justified in review.

### Naming

| Artifact | Convention | Example |
|---|---|---|
| Controller | Singular resource + `Controller`; invokable for single actions | `ContractController`, `HealthController` |
| Action | Imperative verb phrase, one use case | `CreateContract`, `SuspendTenant` |
| Form Request | Verb + resource + `Request` | `StoreContractRequest`, `UpdateTenantSettingsRequest` |
| API Resource | Resource + `Resource` | `ContractResource` |
| Policy | Model + `Policy` | `ContractPolicy` |
| Exception | Condition + `Exception` | `MissingTenantContextException`, `InvalidTenantContextException` |
| Job | Verb phrase | `GenerateContractExport` |
| Event / Listener | Past-tense fact / `Handle…` or intent | `TenantSuspended` / `ReleaseTenantJobs` |
| Enum | Singular noun | `TenantStatus` |
| Test (Pest) | `it('describes observable behavior', …)` — behavior, not implementation | `it('returns 404 for another tenant\'s contract')` |
| API error code | `SCREAMING_SNAKE_CASE`, stable, documented in the owning module's `API.md` | `TENANT_SUSPENDED`, `INVALID_TENANT_TRANSITION` |

- Namespaces follow the structure in [BACKEND_STRUCTURE.md](BACKEND_STRUCTURE.md): `App\Core\...`, `App\Modules\<Module>\...`.
- Database: plural snake_case tables; singular snake_case columns; FK `<singular>_id`.

## Vue / TypeScript

### Language rules

- **Composition API with `<script setup lang="ts">`** — no Options API in new code.
- **Typed props and emits** (`defineProps<…>()` / `defineEmits<…>()`); avoid `any`; handle the API envelope types from `shared/api`.
- **Import alias:** `@/` → `src/` — no deep relative chains (`../../..`).

### Naming

| Artifact | Convention | Example |
|---|---|---|
| Component file | PascalCase, responsibility-named | `SystemStatusPage.vue`, `AppLayout.vue` |
| Composable | `use` + capability | `useTenantSettings` |
| Query hook | `use` + resource + `Query` | `useHealthQuery` |
| Mutation hook | `use` + verb + resource + `Mutation` | `useUpdateTenantSettingsMutation` |
| Pinia store | `use` + domain + `Store` | `useAuthStore` |
| Module folders | kebab-case under `src/modules/` | `tenant-settings/` |
| Non-component TS files | camelCase | `http.ts`, `useHealthQuery.ts` |

### Component responsibility limits

- One component = one responsibility; pages compose components, components do not fetch (data access via module `api/` + query hooks).
- Extract when a component accumulates unrelated concerns or becomes hard to test — *recommendation:* consider splitting past ~200 lines of template+script (not a hard numeric rule).

## Hygiene (both stacks)

- **No dead code, no commented-out code, no debug statements** (`dd`, `dump`, `console.log`, `debugger`) in commits.
- No arbitrary package installation — every new dependency is justified in the PR ([ENGINEERING_PRINCIPLES.md](ENGINEERING_PRINCIPLES.md) §10, [REVIEW_CHECKLIST.md](REVIEW_CHECKLIST.md)).
- File/directory naming follows the structures in `BACKEND_STRUCTURE.md` / `FRONTEND_STRUCTURE.md`.
- **Conventional Commits** required (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:` …) — see [TEAM_AND_GIT_WORKFLOW.md](../00-project/TEAM_AND_GIT_WORKFLOW.md).

## Tooling gates (must pass before PR)

- Backend: `./vendor/bin/pint --test`, `php artisan test`, `composer validate --strict`.
- Frontend: `npm run type-check`, `npm run test`, `npm run build`.
- CI (`.github/workflows/ci.yml`) runs the same gates; a red pipeline blocks merge.

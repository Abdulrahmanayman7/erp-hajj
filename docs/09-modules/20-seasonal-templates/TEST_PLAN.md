# S08 — Test Plan (Proposal)

> **Status:** `PROPOSED` · Tests are part of DoD when coding is authorized  
> Tooling: Pest (backend) · Vitest (frontend) per project standards

## 1. Unit

| Area | Cases |
|---|---|
| Matrix gate | APPROVED kind passes; DENY/CANDIDATE rejected |
| Date mapping | DT-01 delta; missing anchor excludes |
| Hash canonicalize | stable ordering; sensitive keys stripped |
| Conflict classifier | MANUAL_MODIFIED, DUPLICATE_KEY |

## 2. Feature / API

| Case | Expect |
|---|---|
| Preview success | 200 + counts + no PII keys in JSON |
| Execute without preview | 422/409 preview required |
| Execute stale hash | 409 `SEASON_COPY_PREVIEW_STALE` |
| Target not draft | 422/409 `SEASON_COPY_TARGET_NOT_DRAFT` |
| Source closed allowed | preview OK |
| Cross-tenant source/target/template | **404** |
| 401 unauthenticated | all endpoints |
| 403 missing permission | each verb |
| Rerun idempotent | no duplicate natural keys |
| Rollback | adapter forced failure → zero target inserts + failed run row |
| Conflict unresolved | execute rejected |
| Template approve pins matrix | |

## 3. Isolation & RBAC

- Explicit **cross-tenant attack** suite (mandatory).
- Role matrix: supervisor can view templates only; cannot preview/execute.
- Operations Manager cannot execute (once mapping fixed).

## 4. PII exclusion

- Fixtures with pilgrims/documents/season_pilgrims present.
- Preview/execute selection attempting pilgrim kinds → excluded/denied.
- Response body scanners assert absence of document numbers / full names from pilgrim fixtures.

## 5. Preview invalidation

- Mutate target season name after preview → execute stale.
- Mutate source program after preview → stale.
- Expire TTL → stale/expired.

## 6. Frontend Vitest

- Wizard validation; permission visibility of Execute CTA; stale banner; empty APPROVED kinds state.

## 7. Non-goals for S08 tests

- S06 project copy.
- Performance soak at millions of rows (track as OPEN-Q).

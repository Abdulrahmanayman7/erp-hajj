# S08 — Transaction & Audit Design

> **Status:** `PROPOSED` · S08-D04 · ADR-0015 binding

## 1. Atomic execution

```text
BEGIN
  lock target season row (FOR UPDATE) — ensure still draft
  re-verify preview hash + fingerprints
  for adapter in order:
    adapter.apply(...)
  mark preview consumed
COMMIT
```

On any exception: **ROLLBACK** all adapter writes. No partial domain commits.

Adapter order: dependency-friendly (e.g. programs before entitlements; facilities before season shells).

## 2. Attempt history despite rollback

Problem: if history row is inside the same transaction, rollback would erase the failure record.

**Proposed pattern:**

1. Insert `season_copy_runs` with `status=failed` placeholder **or** open run in a short autonomous transaction (`status=started`) **before** domain transaction (**OPEN-Q**: prefer post-failure write).
2. Preferred Part 0 recommendation:
   - **Outer:** create run row `status=running` (committed).
   - **Inner:** domain transaction.
   - On success: update run `succeeded` + counts.
   - On failure: update run `failed` + error_code (domain already rolled back).

Use DB transactions carefully to avoid holding long locks across HTTP; bound batch sizes (**OPEN-Q**).

## 3. Audit events (Core/Audit)

| event_type (proposed) | When | Notes |
|---|---|---|
| `season_template.created` | template create | |
| `season_template.updated` | update | |
| `season_template.approved` | approve | |
| `season_template.archived` | archive | |
| `season_copy.preview_created` | preview | metadata: counts, kinds — no PII |
| `season_copy.preview_invalidated` | invalidation | reason |
| `season_copy.execution_succeeded` | success | run_number, counts, correlation_id |
| `season_copy.execution_failed` | failure | error_code, correlation_id |

Audit values: allow-listed attributes only; never passwords/tokens/PII/document numbers.

Correlation ID: mandatory on run + audit (existing middleware).

## 4. Concurrency controls

- `lockForUpdate` on target `seasons` row during execute.
- Unique constraints on natural keys prevent duplicate inserts under race.
- Preview consume: conditional update `valid → consumed` where hash matches; 0 rows → stale.

## 5. Security boundaries

- Policies on every endpoint.
- Adapters cannot be invoked outside orchestrator (internal API / package visibility by convention).
- Preview tokens unguessable (UUID v4); tenant-scoped lookup.

# ADR-0001: Monorepo

> **Status:** Accepted
> **Last updated:** 2026-08-06

## Context

ERP Hajj consists of a Laravel backend and a Vue frontend, built by a two-developer team collaborating through GitHub pull requests. The API and its primary consumer evolve together during the MVP, and documentation must stay adjacent to the code it governs.

## Decision

Use a **single repository (monorepo)** containing `backend/`, `frontend/`, `docs/`, shared tooling (`.cursor/`, `.github/`, `scripts/`), and root-level standards files.

## Consequences

### Positive

- One pull request can carry an API change together with its frontend consumption and documentation update, keeping them reviewable as a unit.
- Single source of truth for docs, standards, and templates; no cross-repo drift.
- Simpler workflow for a two-person team: one clone, one issue tracker, one PR queue.

### Negative / Accepted Trade-offs

- CI must be configured to run backend and frontend pipelines selectively (path filters) to stay fast — CI design is TBD.
- Backend and frontend cannot be versioned/released fully independently; acceptable for a single-product MVP.

## Notes

- If the platform later needs independent deployment cadences or more teams, this decision can be revisited with a new ADR.

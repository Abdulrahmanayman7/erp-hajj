# S07 — Implementation Plan (Parts 0–6)

> **Status:** Part 0 documentation **saved** on `feature/s07-risks-complaints` (disk).
> **Part 1+:** **NOT AUTHORIZED** until Owner explicit approval.
> **Rule:** No Part N starts until Part N−1 is accepted or Owner-waived.
> Decisions 24/24 locked — see OWNER_DECISIONS.md.

## Part 0 — Documentation (this pack)

**Goal:** MODULE_TEMPLATE design freeze for Risks & Complaints.
**Delivered:** 11 markdown files under `docs/09-modules/21-risks-and-complaints/`.
**Exit:** Owner accepts Part 0 on disk; still **no** ROADMAP/MATRIX edits unless separately authorized.
**Does not:** code, migrations, catalog registration, Git commit.

## Part 1 — Schema + domain skeleton

**Goal:** Additive migrations + Eloquent models/enums/factories; **no public routes** until Part 2.
**Includes:** sequences (D06), risks, treatments, complaints, notes; soft-delete columns (D05); optional link columns (Q5/D08).
**Tests:** schema, number generator, soft-delete guards, tenant scope fail-closed.
**Stop:** Owner Part 1 authorization required. Engineering clarifications in readiness report resolved or deferred in writing.

## Part 2 — Risks API

**Goal:** CRUD + assess + approve (SoD P02) + treatment + assign + close + audit.
**Permissions:** register `risks.*` + D09 sensitive entry for approve.
**Tests:** matrix bands, transitions, self-approval, 401/403/404.

## Part 3 — Complaints API

**Goal:** Intake + Q4 transitions + assign + escalate (D03) + resolve + close/reopen (P03/D04) + notes.
**Permissions:** register `complaints.*` + D09 close/reopen.
**Tests:** full graph, reasons, escalate actors, delete D05.

## Part 4 — Notifications (ADR-0013)

**Goal:** Curated type catalog additions; dispatcher hooks; occurrence dedupe keys from TEST_PLAN.
**Out:** external channels (Q6).
**Tests:** N01 matrix; skip employees without users; no duplicate on retry.

## Part 5 — Frontend (Vue RTL)

**Goal:** List/detail pages, forms, permission visibility, Arabic locale, nav entry.
**Tests:** Vitest validation + visibility; type-check.

## Part 6 — Hardening / UAT / readiness

**Goal:** Concurrency (D12), cross-tenant attack suite, automated UAT matrix, readiness report.
**Gates:** MySQL 8 / Staging before Hostinger — **not** claimed in local Part 0.
**Out:** merge/deploy without Owner authorization.

## Dependency notes

| Dependency | Impact |
|---|---|
| Tenancy / Season / RBAC / Audit / Notifications | Required platform baseline (exists) |
| S01–S05 optional link targets | Validate when IDs sent |
| S06 SeasonProjects | **Not** a start blocker; `project_id` optional (D08) |
| S08–S10 | Downstream consumers; not S07 prerequisites |

## Risk buffers

- If SeasonProjects still unmerged at Part 1: ship core tables without FK constraint; keep nullable `project_id` + app validation stub returning `SEASON_PROJECT_LINK_UNAVAILABLE` until S06 schema exists.
- Category catalog seed: minimal static list until Owner provides full taxonomy.

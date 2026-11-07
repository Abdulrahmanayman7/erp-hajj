# Module 21 — Risks & Complaints (S07)

> **Status:** `PART 6 COMPLETE — INTEGRATION HARDENING & UAT` (2026-11-07)
> **Phase:** Phase 2 / Workflow **S07**
> **Product scope:** [ADR-0020](../../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S07
> **Worktree:** `D:\erp-hajj-s07-risks-complaints` · branch `feature/s07-risks-complaints` @ `e71d7d9`
> **Git:** Parts 0–6 remain **uncommitted** per Owner instruction (no push/merge/deploy)

## Purpose

Provide a **tenant- and season-scoped** operational capability to:

1. Maintain an **operational risk register** (identify → assess → approve → treat → close).
2. Manage **staff complaints** end-to-end (intake → review → assign → resolve → close, with escalate/reopen).

Both registers live in **one documentation/code module** with **independent lifecycles and permissions** (Q2).

## Binding decisions

All **24** Owner decisions are binding — see [OWNER_DECISIONS.md](OWNER_DECISIONS.md).

## Package index

| Doc | Role |
|---|---|
| [OWNER_DECISIONS.md](OWNER_DECISIONS.md) | Decision register (24/24) |
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Lifecycles, matrix, SoD, links, errors |
| [DATA_MODEL.md](DATA_MODEL.md) | Schema (migrations 310001–310007) |
| [PERMISSIONS.md](PERMISSIONS.md) | Implemented permission matrix |
| [API.md](API.md) | Implemented REST contract |
| [UI.md](UI.md) | Implemented Vue RTL scope |
| [TEST_PLAN.md](TEST_PLAN.md) | Pest/Vitest matrix |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | Verifiable ACs |
| [S07_IMPLEMENTATION_PLAN.md](S07_IMPLEMENTATION_PLAN.md) | Parts 1–6 gates |
| [S07_PART0_READINESS_REPORT.md](S07_PART0_READINESS_REPORT.md) | Part 0 closure |
| [S07_PART6_UAT_REPORT.md](S07_PART6_UAT_REPORT.md) | Part 6 UAT matrix & gaps |

## Delivery summary (Parts 1–6)

| Part | Delivered |
|---|---|
| 1 | Schema, factories, numbering, tenant scope |
| 2 | Risks API + policies + tests |
| 3 | Complaints API + escalate dual-path + tests |
| 4 | In-app notifications + durable `occ:{id}` dedupe |
| 5 | Vue RTL lists/details/lifecycle actions |
| 6 | Timeline, operational link selectors, project capability gate, escalate UI, UAT |

## Known remaining gaps

- **MySQL 8 staging validation still pending** (SQLite `:memory:` only in Part 6).
- SeasonProjects interactive picker enables only when a compatible S06 API + `season_projects` table exist; S06 is not merged into this worktree.
- Global ROADMAP / Phase 2 Scope Matrix intentionally untouched.

## Out of scope (unchanged)

- Crisis command center / SOS suite.
- Email, SMS, WhatsApp, Web Push, FCM.
- Pilgrim/public self-service intake.
- SLA timers / breach jobs.
- AI / predictive scoring beyond L×I.
- S06 as a hard prerequisite for creating risks/complaints.

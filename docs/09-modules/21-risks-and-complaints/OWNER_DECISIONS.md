# S07 — Owner Decisions Register (Final)

> **Status:** All **24** decisions **APPROVED / BINDING** (2026-11-07)
> Engineering clarifications that do not reopen these decisions are listed in [S07_PART0_READINESS_REPORT.md](S07_PART0_READINESS_REPORT.md).

## 1. Functional (Q1–Q6)

| ID | Choice | Binding rule | Docs | AC |
|---|---|---|---|---|
| **Q1** | **A** | Complaints are **full operational** in Phase 2: intake, classify, assign, follow-up, escalate, resolve, close | BR, API, UI, TEST | AC-C1 |
| **Q2** | **A** | **Two registers** (risks + complaints) inside **one module**; independent lifecycles and permissions | README, PERMS, DATA | AC-M1 |
| **Q3** | **A** | Risk matrix **5×5**: Likelihood 1–5, Impact 1–5; `score = L × I` | BR, API, UI, TEST | AC-R2 |
| **Q4** | **A** | Complaint states: `new → under_review → in_progress → resolved → closed`, plus escalate/reopen under controls | BR, API, UI, TEST | AC-C1 |
| **Q5** | **A** | `season_id` **mandatory**; optional links to pilgrim / group / facility / vehicle / project with existence + tenant + season validation | DATA, API, BR | AC-C3 |
| **Q6** | **A** | Scope = ops + follow-up + escalate + **in-app** notifications only — no Command Center, Crisis Suite, or external channels | README, BR, UI | AC-X1 |

## 2. Governance (P/A/N)

| ID | Choice | Binding rule | Docs | AC |
|---|---|---|---|---|
| **S07-P01** | **A** | Authorized internal users may create; **separate** create permissions for risks vs complaints | PERMS, API | AC-R1, AC-C0 |
| **S07-P02** | **A** | Risk officer reviews/approves assessment + treatment; **creator cannot approve own risk** | BR, API, TEST | AC-R3 |
| **S07-P03** | **A** | Assignee proposes complaint resolution; authorized supervisor **closes** or **reopens**; **reason required** | BR, API, UI | AC-C2 |
| **S07-P04** | **A** | Fine-grained permissions per register/action; tenant + season + authorized scope isolation | PERMS, TEST | AC-S1 |
| **S07-A01** | **A** | Audit create, update, assess, assign, escalate, resolve, close, reopen (actor, time, material changes) | BR, API, TEST | AC-A1 |
| **S07-N01** | **A** | In-app notify on assign, escalate, high/critical risk, resolve, reopen; dedupe without dropping distinct events; ADR-0013 | BR, TEST | AC-N1 |

## 3. Detailed design (D01–D12)

| ID | Choice | Binding rule | Docs | AC |
|---|---|---|---|---|
| **S07-D01** | **A** | Risk states: `identified → under_assessment → approved → treating → closed` with `closure_outcome ∈ {mitigated, accepted, transferred, cancelled}` | BR, API, UI, DATA | AC-R4 |
| **S07-D02** | **A** | Severity from score: **1–4 low**, **5–9 medium**, **10–15 high**, **16–25 critical** | BR, API, N01 | AC-R2 |
| **S07-D03** | **A** | Escalate: **assignee** (via linked User) **or** holder of `complaints.escalate` | BR, PERMS, API | AC-C4 |
| **S07-D04** | **A** | Reopen: `closed → under_review` via `complaints.reopen`; reason required; **no MVP time window** | BR, API | AC-C2 |
| **S07-D05** | **A** | Soft-delete **only** while risk=`identified` or complaint=`new`; forbidden after assessment/assignment/leaving initial state | BR, DATA, API | AC-D5 |
| **S07-D06** | **A** | Numbers per tenant+season: `RSK-YYYY-######` / `CMP-YYYY-######` via sequences; no reuse after soft-delete | DATA, API | AC-D6 |
| **S07-D07** | **A** | **No SLA** fields, timers, or breach jobs in S07 MVP | BR | AC-X2 |
| **S07-D08** | **A** | `project_id` **optional**; if sent, validate SeasonProject exists + same tenant/season; missing project never blocks create; unavailable module → stable 422 | BR, API, DATA | AC-D8 |
| **S07-D09** | **A** | `sensitiveExplicitOnly`: `risks.approve_assessment`, `complaints.close`, `complaints.reopen` | PERMS | AC-P9 |
| **S07-D10** | **A** | **Staff-only** intake (authenticated tenant users with create permission); no pilgrim/public portal | BR, UI, API | AC-X1 |
| **S07-D11** | **A** | Treatment plan fields: `title`, `plan_body` (actions), `owner_employee_id`, `due_at`, `status` | DATA, API | AC-R5 |
| **S07-D12** | **A** | Transition under row lock; stale/invalid status → reject with stable machine code (HTTP **409** preferred for stale concurrency; see engineering note) | BR, API, TEST | AC-D12 |

## 4. Explicit non-requirements

- External notification channels.
- Crisis/command-center UX.
- SLA / KPI engines (S09 later may consume counts; not S07 delivery).
- Mandatory S06 merge before S07 Part 1.
- Automatic 24-hour notification suppression windows (lifecycle dedupe uses **event occurrence ids**).

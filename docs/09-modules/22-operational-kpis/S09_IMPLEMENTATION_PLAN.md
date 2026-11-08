# S09 — Implementation Plan (Post-Approval Only)

> **Status:** `NOT_STARTED` / plan only  
> **Prerequisite:** Owner freeze of S09-D01…D15 + explicit **S09-D16 coding authorization**  
> **Date:** 2026-11-08

---

## Milestones (proposed)

| Part | Content | Exit |
|---|---|---|
| 0 | Design pack (this directory) | Owner decisions |
| 1 | Aggregator skeleton + season/tenant helpers + Pest isolation | Green security tests |
| 2 | Transport + Accommodation KPI builders | Formula tests |
| 3 | Procurement + Projects + Risks/Complaints builders | Double-count guards |
| 4 | API (per S09-D07) + FE RTL cards | Vitest + build |
| 5 | Hardening, UAT script, docs sync | Owner acceptance pack |

## Non-starts until authorization

- Feature branch  
- Migrations (none expected for MVP unless Owner requires)  
- Production ops  

## DoD reminder

Follow `DEFINITION_OF_DONE.md` + ADR-0014 omit semantics + cross-tenant tests.

# S08 — Acceptance Criteria

> **Status:** `PROPOSED` · Apply when coding is Owner-authorized  
> Sprint closure also requires ADR-0020 §9 + DEFINITION_OF_DONE

## Part 0 (this authorization)

| ID | Criterion | Status |
|---|---|---|
| AC-P0-1 | Owner decisions D01–D10 documented | Met in OWNER_DECISIONS.md |
| AC-P0-2 | Entity matrix deny-by-default + candidates | Met (pending field approval) |
| AC-P0-3 | Architecture, data, API, RBAC, preview, audit, tests, roadmap docs present | Met |
| AC-P0-4 | ROADMAP/MATRIX reconciled without fabricated acceptance | Met in Part 0 edits |
| AC-P0-5 | No app code / migrations executed / commits / pushes | Required confirmation in Part 0 report |

## Future implementation acceptance (not authorized now)

| ID | Criterion |
|---|---|
| AC-S08-1 | Only APPROVED matrix kinds copy; DENY kinds never appear in target |
| AC-S08-2 | Hybrid from_season and from_template share controls |
| AC-S08-3 | Source draft/active/closed; target draft; same tenant |
| AC-S08-4 | Date mapping applied; execution timestamps not copied |
| AC-S08-5 | Mandatory preview; stale preview cannot execute |
| AC-S08-6 | Preview shows adds/changes/conflicts/exclusions without PII |
| AC-S08-7 | Safe idempotent reruns; manual rows protected |
| AC-S08-8 | Atomic rollback on failure; attempt audited |
| AC-S08-9 | Permissions D06 enforced in backend; cross-tenant 404 |
| AC-S08-10 | No S06 integration |
| AC-S08-11 | Cross-tenant + RBAC + PII exclusion tests pass |
| AC-S08-12 | FE wizard RTL + permission-gated actions |
| AC-S08-13 | Owner acceptance recorded before MATRIX → COMPLETE |

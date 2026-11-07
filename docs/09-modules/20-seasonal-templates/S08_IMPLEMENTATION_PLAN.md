# S08 — Implementation Roadmap (Proposal Only)

> **Does NOT authorize coding.** Separate Owner act required after field-level matrix approval.

## Recommended sequence

| Phase | Scope | Depends on | Est. complexity |
|---|---|---|---|
| **Gate A** | Owner signs entity matrix + role-code mapping OPEN-Qs | Part 0 | Low (governance) |
| **Gate B** | Owner coding authorization | Gate A | — |
| **Part 1** | Migrations + models + provenance + catalog permissions | Gate B | Medium |
| **Part 2** | Orchestrator + preview engine + history API | Part 1 | High |
| **Part 3** | SeasonPlanning + Transport adapters (APPROVED kinds only) | Part 2 | High |
| **Part 4** | Accommodation + Procurement/Inventory adapters as approved | Part 3 | High |
| **Part 5** | Vue wizard + nav + i18n | Part 2+ | Medium |
| **Part 6** | Hardening: isolation suite, PII tests, UAT evidence | Parts 3–5 | Medium |

## Dependencies

- Foundation seasons (done).
- APPROVED allowlist (blocking).
- Domain modules for adapters (present); adapter scope limited by matrix.
- **Not** S06 (D08).
- S07 present as deny-list evidence only.

## Risks

| Risk | Mitigation |
|---|---|
| Scope creep via “just one more table” | Matrix gate; deny default |
| PII leakage via contacts/drivers | Default DENY personal phones until Owner allows |
| Large copy timeouts | Bound batches; OPEN-Q async |
| Invasive domain hooks for manual_touched | Prefer provenance module table + plan-time diff |
| Role label ≠ catalog codes | Resolve OPEN-Q before Part 1 seeds |

## Required Owner approvals before coding

1. Field-level matrix sign-off (promote CANDIDATE → APPROVED/DENY).
2. Role template mapping (Administrator / Operations Manager).
3. Date strategy confirmation.
4. Preview TTL.
5. Explicit **coding authorization** document.

## Open technical questions

See Part 0 readiness report §10.

## GO/NO-GO for implementation

| Check | Now |
|---|---|
| Part 0 docs complete | Yes (this package) |
| Field matrix Owner-approved | **No** |
| Coding authorization | **No** |
| **Implementation readiness** | **NO-GO** |

# ADR-0008: Decision–Recommendation ownership and single-step approval

- **Status:** Accepted
- **Date:** 2026-08-10
- **Sprint:** 011 (Decisions implementation)
- **Deciders:** ERP Hajj product / engineering (documentation lock)

## Context

Workflow 1 requires: Meeting → Recommendation → Decision → Task(s) → …  
Sprint 010 introduced first-class `MeetingRecommendation` rows and deliberately left conversion to Decisions (ADR-0007). Sprint 011 must lock:

1. Who owns the Decision↔Recommendation FK
2. Whether Decisions may exist without a meeting
3. Cardinality of recommendation → Decision
4. A minimal approval lifecycle without Tasks or multi-stage chains

> **Implementation note (2026-08-10):** Sprint 011 implemented this ADR through the Decisions vertical slice, including the tenant-owned Decision model, lifecycle APIs, permission seed, audit events, and the Meetings **إنشاء قرار** conversion affordance.

## Decision

### 1. Relationship ownership

- **Decisions own** nullable `source_recommendation_id` → `meeting_recommendations.id`.
- **Do not** add `decision_id` to `meeting_recommendations`.
- Source meeting is **derived** through the recommendation; **no** redundant `source_meeting_id` on `decisions`.

### 2. Cardinality

- One **final** recommendation produces **at most one** Decision (UNIQUE on `source_recommendation_id`).
- Conversion requires recommendation `final` and parent meeting `completed`.

### 3. Standalone Decisions

- Allowed in MVP (`source_recommendation_id` null), consistent with CORE_WORKFLOWS (“may originate from a meeting or exist independently”).

### 4. Approval lifecycle

- Single-step capability-based approval: `draft` → `pending_approval` → `approved` → `closed`.
- Approver may **return to draft** with required comment (no separate `rejected` terminal status).
- Early **cancel** from `draft` | `pending_approval` only.
- **`approved` is official immediately** — no separate `active` status and no activation scheduler.
- Permissions reuse catalog verbs (`update` / `approve` / `close`) — no micro-permissions for submit/reject.

### 5. Closure without Tasks

- Sprint 011 allows administrative `approved → closed` without Task completion checks.
- Future Tasks module may add a close gate; Decision does not store task arrays or fake progress now.
- Future Tasks own `decision_id` (Decision does not own task FKs).

## Consequences

### Positive

- Clear governance entity without coupling Meetings schema to Decisions.
- Duplicate conversion is DB-enforced.
- Standalone operational decisions remain possible.
- Lifecycle stays small and testable; mirrors Contracts/Meetings action-endpoint style.

### Negative / trade-offs

- One-to-one recommendation→Decision may be limiting if business later needs multiple Decisions per recommendation (would need Change Request + unique constraint drop).
- Manual close without Tasks can close “too early” operationally — accepted for Sprint 011; document risk for Sprint 012.
- Return-to-draft (vs rejected terminal) means cancelled vs abandoned-after-reject nuances are reduced — intentional simplicity.

## Alternatives considered

| Alternative | Why rejected |
|---|---|
| `decision_id` on recommendations | Couples Meetings writes to Decisions; ADR-0007 already deferred ownership |
| Meeting-only Decisions | Conflicts with locked CORE_WORKFLOWS independent decisions |
| Many Decisions per recommendation | Overbuilds MVP; no documented need |
| Multi-stage approvals | Out of scope; not committed |
| Separate `active` + scheduler | Unnecessary without proven future `effective_date` activation need |
| Defer `close` until Tasks | Blocks governance completion; administrative close is enough for Sprint 011 |

## References

- [07-decisions/](../09-modules/07-decisions/)
- [ADR-0007](ADR-0007-MEETING-AGENDA-AND-RECOMMENDATIONS.md)
- [CORE_WORKFLOWS.md](../01-business/CORE_WORKFLOWS.md)

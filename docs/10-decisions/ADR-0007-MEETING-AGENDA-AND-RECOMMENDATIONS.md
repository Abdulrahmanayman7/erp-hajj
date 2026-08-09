# ADR-0007: Meeting agenda items and first-class recommendations

> **Status:** Accepted
> **Date:** 2026-08-09
> **Sprint:** 010 (Meetings specification)

## Context

Workflow 1 is locked as:

Meeting → Recommendation → Decision → Task(s) → …

Glossary defines Recommendation as a meeting outcome that **may** become a Decision, and Minutes as the formal meeting record. Stub Meetings docs leaned toward ordered agenda items and an own-table recommendation, but left minutes format and recommendation persistence TBD.

Sprint 010 must keep Decisions and Tasks out of scope while remaining future-compatible.

## Decision

1. **Agenda** is modeled as structured `meeting_agenda_items` (title, optional description, `sort_order`) — not a single agenda blob only. Meeting `description` remains an optional overview field.
2. **Minutes** are stored as a single `meetings.minutes_body` TEXT field (textarea UX; no rich-text package; no revision table in MVP).
3. **Recommendations** are first-class `meeting_recommendations` rows with `draft` \| `final` status only. They are **not** Decisions and carry **no `decision_id`** in Sprint 010.
4. Future Decisions module may add an optional link (`meeting_id` and/or `meeting_recommendation_id`) when converting — conversion is owned by Decisions, not Meetings.

## Consequences

- Workflow 1 has identifiable recommendation records without inventing Decision rows early.
- Completing a meeting can finalize draft recommendations atomically without decision approval semantics.
- UI must not expose a functional “create decision” control in Sprint 010.
- Structured agenda enables optional linkage recommendation → agenda item without forcing 1:1.

## Alternatives considered

| Alternative | Why rejected |
|---|---|
| Agenda as free text only | Conflicts with documented “agenda item / ordered” concept and weakens later recommendation linkage |
| Recommendations embedded only in minutes text | Breaks Workflow 1 identity; hard for Decisions to reference safely |
| Creating Decision records from Meetings now | Out of Sprint 010 scope; conflates modules |
| Full minutes revision history table | Overkill for MVP; audit events suffice |

## References

- [docs/09-modules/06-meetings/](../09-modules/06-meetings/)
- [docs/01-business/CORE_WORKFLOWS.md](../01-business/CORE_WORKFLOWS.md)
- [docs/00-project/GLOSSARY.md](../00-project/GLOSSARY.md)

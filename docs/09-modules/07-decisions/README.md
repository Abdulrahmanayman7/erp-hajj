# Module: Decisions (القرارات)

> **Status:** Specified (Sprint 011) — **not implemented**
> **Last updated:** 2026-08-10

## Purpose

Manage each tenant’s **formal governance decisions** as a **separate business entity** (never merged into Tasks) — the next step of Workflow 1 in [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md):

**Meeting → Recommendation → Decision → Task(s) → Responsible → Execution → Measurement → Closure**

Sprint 011 specifies **Decisions only**. Tasks remain a future module. A Decision may be created **from a final Meeting Recommendation** or **independently** (no meeting).

## Domain separation (non-negotiable)

| Concept | Meaning | Owned by |
|---|---|---|
| **MeetingRecommendation** | Meeting outcome that *may* become a Decision | Meetings |
| **Decision** | Formal governance / management decision | This module |
| **Task** | Assignable execution work | Tasks (Sprint 012+) |
| **Employee** | Issuer / responsible personnel identity | Employees |
| **User** | Authenticated actor (`created_by`, transition actor) | Users & Authorization |
| **OrganizationUnit** | Optional responsible unit | Organization Structure |
| **Document** | Attachments | Documents (later) |
| **Notification** | Delivery of approval / status alerts | Notifications (later) |

Do **not** treat a recommendation as automatically approved. Do **not** create Task rows in Sprint 011. Do **not** store `decision_id` on `meeting_recommendations` — Decisions owns `source_recommendation_id`.

## Sprint 011 scope

| In scope | Out of scope |
|---|---|
| `decisions` + server-generated `DEC-######` | Tasks module / auto task generation |
| Optional `source_recommendation_id` (unique when set) | Multi-stage approval chains |
| Standalone decisions (no recommendation) | Decision type/category catalog |
| Controlled lifecycle action endpoints | `In Progress` / `Blocked` execution statuses |
| Append-only `decision_status_transitions` | Fake task progress / KPI measurement |
| Optional org unit + issuer + responsible employees | Documents upload/download |
| Create-from-recommendation conversion (Decisions-owned) | E-signature / finance fields |
| Policies + `decisions.*` permissions (seed at implementation) | SoftDeletes; free PATCH of `status` |
| Arabic RTL list + details + Meetings “إنشاء قرار” affordance | Notification delivery |
| Audit events + Pest/Vitest matrices | Dashboard KPI widgets |

## Module placement

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/Decisions/` |
| Frontend | `frontend/src/modules/decisions/` |
| Config | `backend/config/decisions.php` (number prefix/pad) |

## Personas

| Persona | Typical use |
|---|---|
| Tenant Owner / General Manager | Full lifecycle; approve; close |
| Department Manager | Create/submit drafts; view; limited approval if granted |
| Auditor | View decisions + history (read) |
| Supervisor / Employee | Default **none** |

Exact default role grants: [PERMISSIONS.md](PERMISSIONS.md).

## Architectural decisions

| Topic | Decision | Doc |
|---|---|---|
| Numbering | `DEC-000001…`, tenant sequence + `FOR UPDATE`, immutable | [DATA_MODEL.md](DATA_MODEL.md) |
| Recommendation link | Decisions own nullable unique `source_recommendation_id` | [ADR-0008](../../10-decisions/ADR-0008-DECISION-RECOMMENDATION-AND-APPROVAL.md) |
| Cardinality | One final recommendation → **at most one** Decision | [ADR-0008](../../10-decisions/ADR-0008-DECISION-RECOMMENDATION-AND-APPROVAL.md) |
| Standalone | Allowed (`source_recommendation_id` null) | [ADR-0008](../../10-decisions/ADR-0008-DECISION-RECOMMENDATION-AND-APPROVAL.md) |
| Source meeting | **Derived** via recommendation — no redundant `source_meeting_id` | [DATA_MODEL.md](DATA_MODEL.md) |
| Type/category | **Omitted** in MVP | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Lifecycle | `draft` → `pending_approval` → `approved` → `closed`; cancel early; return-to-draft | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Approval | Single-step capability `decisions.approve` | [ADR-0008](../../10-decisions/ADR-0008-DECISION-RECOMMENDATION-AND-APPROVAL.md) |
| Close without Tasks | Manual administrative close in Sprint 011; Tasks may gate later | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Priority | **Omitted** in MVP | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Delete | Hard delete **draft-only** (never left draft) | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Future Tasks | Task will own `decision_id` nullable | [BUSINESS_RULES.md](BUSINESS_RULES.md) |

## References

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) Workflow 1 · [BUSINESS_RULES.md](../../01-business/BUSINESS_RULES.md)
- [06-meetings/](../06-meetings/) · [08-tasks/](../08-tasks/) · [04-employees-and-supervisors/](../04-employees-and-supervisors/)
- [ADR-0007](../../10-decisions/ADR-0007-MEETING-AGENDA-AND-RECOMMENDATIONS.md) · [ADR-0008](../../10-decisions/ADR-0008-DECISION-RECOMMENDATION-AND-APPROVAL.md)
- [MODULE_TEMPLATE.md](../../02-architecture/MODULE_TEMPLATE.md)

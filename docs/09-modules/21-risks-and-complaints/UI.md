# Risks & Complaints — UI (Implemented Part 5–6)

> **Status:** PART 6 — RTL Arabic-first Vue module shipped
> Package: `frontend/src/modules/risks-complaints/` · Locale: `risksComplaintsAr`
> Binding: Q1–Q6, P01–P04, D01–D05, D07–D08, D10 · No crisis maps · No SLA widgets · No external notify UI

## 1. Navigation

| Item | Route | Permission gate |
|---|---|---|
| المخاطر | `/app/risks` | `risks.view` |
| الشكاوى | `/app/complaints` | `complaints.view` |

- Sidebar under operations/governance accordion (nav tests cover link count).
- SeasonWorkspaceBar / active season required (Q5).

## 2. Risks

| Screen | Responsibility |
|---|---|
| `SeasonRisksPage` | List, filters, create CTA, season gate, loading/empty/error |
| `RiskFormDrawer` | Create/edit + `OperationalLinksFields` |
| `RiskDetailsPage` | Overview, assessment, treatment, lifecycle actions, timeline |
| `RiskAssessmentPanel` | L/I matrix; approve hidden for creator client-side |
| `RiskTreatmentForm` | D11 fields |
| `RiskLifecycleActions` | Assign / close / soft-delete |

## 3. Complaints

| Screen | Responsibility |
|---|---|
| `SeasonComplaintsPage` | List, filters, staff intake CTA |
| `ComplaintFormDrawer` | Intake + operational links |
| `ComplaintDetailsPage` | Overview, notes, lifecycle, timeline |
| `ComplaintLifecycleActions` | Review / assign / escalate / resolve / close / reopen / delete |
| `ComplaintNotesPanel` | Append-only notes |

### Escalation visibility (Part 6)

Escalate control is shown when status allows **and** either:

1. User has `complaints.escalate`, **or**
2. Current user's `employee_id` matches `assignee_employee_id`

Server remains authoritative (D03).

## 4. Operational links & project (Part 6)

`OperationalLinksFields` reuses season-scoped lookups:

| Link | Source API / permission hint |
|---|---|
| Season pilgrim | `listSeasonPilgrims` · `season_pilgrims.view` |
| Group | `listPilgrimGroups` · `season_pilgrims.view` |
| Facility | season facilities → `facility_id` · `accommodation.view` |
| Season vehicle | `listSeasonVehicles` · transportation view/manage |
| Project | Enabled only when `GET /operational-links/capabilities` reports `season_projects_available`; otherwise dashed unavailable message (`risks.links.projectUnavailable`) |

No invented SeasonProjects endpoints. No S06 code copy.

## 5. Audit timeline (Part 6)

`OperationalTimelinePanel` on risk/complaint detail:

- Read-only list from `/risks/{id}/timeline` or `/complaints/{id}/timeline`.
- Loading / empty / error states.
- Arabic event labels via `audit.events.*` when present.
- Does not require `audit.view`; gated by module view permission on the API.

## 6. Permission visibility

| Control | Visible when |
|---|---|
| Create risk/complaint | respective create permission |
| Approve assessment | `risks.approve_assessment` && !isCreator |
| Close/reopen complaint | sensitive permissions explicitly granted |
| Escalate | assignee linked user **or** `complaints.escalate` |
| Project link select | capabilities.season_projects_available |

## 7. Explicit non-UI (unchanged)

- Live ops map / crisis board.
- Email/SMS composer.
- SLA dashboards.
- Pilgrim mobile complaint form.

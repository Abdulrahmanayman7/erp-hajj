# Risks & Complaints — API (Implemented)

> **Status:** PART 6 — implemented contract
> Base: `/api/v1` · Auth: Sanctum · Season: `X-Season-Id` / Season Context
> Envelope: project standard success/error JSON · Correlation ID echoed

## 1. Common behaviours

| Concern | Rule |
|---|---|
| Unauthenticated | 401 |
| Missing permission | 403 |
| Cross-tenant / unknown / wrong-season row | **404** |
| Validation / domain rule | **422** + `code` |
| Stale concurrency (D12) | **409** + `CONFLICT_STALE_STATE` |
| Pagination | Platform list meta |
| Audit | Material creates/updates/transitions → `audit_logs` |
| Notifications | ADR-0013 afterCommit; occurrence key `occ:{id}` |

## 2. Risks

| Method | Path | Permission |
|---|---|---|
| `GET` | `/risks` | `risks.view` |
| `POST` | `/risks` | `risks.create` |
| `GET` | `/risks/{risk}` | `risks.view` |
| `PATCH` | `/risks/{risk}` | `risks.update` |
| `DELETE` | `/risks/{risk}` | `risks.delete` (status `identified` only) |
| `POST` | `/risks/{risk}/assess` | `risks.assess` |
| `PUT` | `/risks/{risk}/treatment` | `risks.manage_treatment` |
| `POST` | `/risks/{risk}/approve-assessment` | `risks.approve_assessment` (+ SoD) |
| `POST` | `/risks/{risk}/assign` | `risks.assign` |
| `POST` | `/risks/{risk}/close` | `risks.close` |
| `GET` | `/risks/{risk}/timeline` | `risks.view` (not `audit.view`) |

List filters: `status`, `severity_level`, `owner_employee_id`, `q`, page/per_page.

Optional create/update links (nullable, server-validated): `season_pilgrim_id`, `group_id`, `facility_id`, `transport_season_vehicle_id`, `project_id`.

## 3. Complaints

| Method | Path | Permission / rule |
|---|---|---|
| `GET` | `/complaints` | `complaints.view` |
| `POST` | `/complaints` | `complaints.create` |
| `GET` | `/complaints/{complaint}` | `complaints.view` |
| `PATCH` | `/complaints/{complaint}` | `complaints.update` |
| `DELETE` | `/complaints/{complaint}` | `complaints.delete` (status `new` only) |
| `POST` | `/complaints/{complaint}/assign` | `complaints.assign` |
| `POST` | `/complaints/{complaint}/escalate` | `complaints.escalate` **OR** assignee's linked User |
| `POST` | `/complaints/{complaint}/resolve` | `complaints.resolve` (+ assignee match) |
| `POST` | `/complaints/{complaint}/close` | `complaints.close` (sensitive) |
| `POST` | `/complaints/{complaint}/reopen` | `complaints.reopen` (sensitive) |
| `POST` | `/complaints/{complaint}/notes` | `complaints.add_note` |
| `GET` | `/complaints/{complaint}/timeline` | `complaints.view` (not `audit.view`) |

Create requires `category_code`, `title`, `body`. Same optional operational links as risks.

## 4. Operational links capabilities

### `GET /operational-links/capabilities`

Permission: any of `risks.view` / `complaints.view` / `risks.create` / `complaints.create`.

```json
{
  "season_projects_available": false,
  "season_projects_reason": "season_projects_api_unavailable"
}
```

Does **not** invent SeasonProjects. UI must disable project selection when unavailable. Submitting `project_id` while SeasonProjects is absent → `SEASON_PROJECT_LINK_UNAVAILABLE` / `LINKED_ENTITY_INVALID`.

## 5. Timeline resource

Read-only projection of existing `audit_logs` for `operational_risk` / `operational_complaint` entity types after season-scoped entity authorization. Slim fields:

```json
{
  "id": 1,
  "event_type": "RISK_CREATED",
  "actor_label": "…",
  "actor_user_id": 3,
  "entity_type": "operational_risk",
  "entity_id": 1,
  "entity_number": "RSK-2026-000001",
  "correlation_id": "…",
  "created_at": "…",
  "summary": {}
}
```

Sensitive complaint narrative fields (`body`, `resolution_text`, close/reopen reasons) are stripped from `summary`. No second audit store.

## 6. Stable error codes (selected)

| Code | Typical HTTP |
|---|---|
| `RISK_SELF_APPROVAL_FORBIDDEN` | 422 |
| `RISK_INVALID_TRANSITION` / `COMPLAINT_INVALID_TRANSITION` | 422 |
| `RISK_TREATMENT_REQUIRED` / `RISK_ASSESSMENT_INCOMPLETE` | 422 |
| `RISK_MATRIX_OUT_OF_RANGE` | 422 |
| `COMPLAINT_CLOSE_REASON_REQUIRED` / `COMPLAINT_REOPEN_REASON_REQUIRED` | 422 |
| `LINKED_ENTITY_INVALID` | 422 |
| `SEASON_PROJECT_LINK_UNAVAILABLE` | 422 |
| `CONFLICT_STALE_STATE` | 409 |

## 7. Notification occurrence mechanism

Within the same DB transaction as the domain write:

1. Insert row into `operational_notification_occurrences` → durable `occurrence_id`.
2. Dispatch in-app notification with dedupe bucket `occ:{occurrence_id}` via ADR-0013 `NotificationDedupe`.
3. Retry of the same occurrence does not create a duplicate inbox row.
4. A new domain occurrence (re-assign, re-resolve after reopen, severity re-entry) allocates a new id → new notification.
5. Transaction rollback removes both domain change and occurrence (zero notification).

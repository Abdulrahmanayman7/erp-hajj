# S08 — API Design Proposal

> **Status:** `PROPOSED` · Coding **NOT AUTHORIZED**  
> Base path: `/api/v1` (Sanctum + tenant middleware + season middleware where required)  
> Envelope: existing API error/resource conventions.

## 1. Permissions per endpoint

| Method | Path | Permission |
|---|---|---|
| GET | `/season-templates` | `season_templates.view` |
| POST | `/season-templates` | `season_templates.manage` |
| GET | `/season-templates/{template}` | `season_templates.view` |
| PATCH | `/season-templates/{template}` | `season_templates.manage` |
| POST | `/season-templates/{template}/approve` | `season_templates.manage` |
| POST | `/season-templates/{template}/archive` | `season_templates.manage` |
| POST | `/season-copy/previews` | `season_copy.preview` |
| GET | `/season-copy/previews/{preview}` | `season_copy.preview` or `history` |
| POST | `/season-copy/executions` | `season_copy.execute` |
| GET | `/season-copy/runs` | `season_copy.history` |
| GET | `/season-copy/runs/{run}` | `season_copy.history` |

Unauthenticated → **401**. Missing permission → **403**. Cross-tenant resource id → **404**.

## 2. Templates

### POST `/season-templates`

Request (proposed):

```json
{
  "name": "قالب موسم 1447",
  "description": "برامج ونقاط نقل",
  "items": [
    { "entity_kind": "season_programs", "selection": { "codes": ["*"] } },
    { "entity_kind": "transport_entitlements", "selection": { "codes": ["*"] } }
  ]
}
```

Validation: name required; each `entity_kind` must be **APPROVED** in current matrix (at coding time); reject DENY/CANDIDATE-unsigned kinds with `SEASON_COPY_KIND_DENIED`.

### POST `.../approve`

Only `draft` → `approved`; pins `matrix_version`.

## 3. Preview

### POST `/season-copy/previews`

```json
{
  "mode": "from_season",
  "source_season_id": 12,
  "target_season_id": 15,
  "date_mapping": { "strategy": "season_start_delta" },
  "selection": {
    "entity_kinds": ["season_programs", "transport_entitlements"]
  },
  "resolutions": []
}
```

Or `"mode": "from_template", "source_template_id": 3` (template must be `approved`).

**Server checks:**

- Same tenant (implicit).
- Source season status ∈ {draft, active, closed} when mode=from_season.
- Target season status = `draft`.
- Expand plan via adapters; persist preview; return summary.

**Response (proposed):**

```json
{
  "data": {
    "preview_token": "…",
    "content_hash": "…",
    "expires_at": "…",
    "counts": { "add": 10, "change": 2, "exclude": 4, "conflict": 1 },
    "lines": [ { "entity_kind": "season_programs", "action": "add", "natural_key": "PROG-A", "summary": {…} } ],
    "exclusions": [ { "reason_code": "KIND_NOT_AUTHORIZED", "entity_kind": "season_pilgrims" } ]
  }
}
```

Lines **must not** include pilgrim names, document numbers, or other PII.

## 4. Execute

### POST `/season-copy/executions`

```json
{
  "preview_token": "…",
  "content_hash": "…",
  "resolutions": [
    { "entity_kind": "season_programs", "natural_key": "PROG-A", "decision": "skip" }
  ]
}
```

**Server checks:**

- Permission `season_copy.execute`.
- Preview `valid`, not expired, hash matches, fingerprints match current source/target/matrix.
- Target still `draft`.
- All blocking conflicts have resolutions.
- Run adapters in one transaction.

Success **200/201** with run resource. Stale → **409** `SEASON_COPY_PREVIEW_STALE`.

## 5. History

List/filter runs by target season, status, date. Show counts and error codes — no PII.

## 6. Cross-tenant scenarios

| Attempt | Result |
|---|---|
| `source_season_id` of another tenant | **404** |
| `target_season_id` of another tenant | **404** |
| Template id of another tenant | **404** |
| Preview token replay across tenant context | **404** / invalid |

`tenant_id` never accepted from body.

## 7. Season header

If routes are nested under season context middleware, document whether `X-Season-Id` must equal `target_season_id` (**OPEN-Q** — recommend require match on execute to avoid operator mistakes).

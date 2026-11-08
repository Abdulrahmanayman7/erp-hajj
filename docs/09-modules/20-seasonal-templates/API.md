# S08 — API Design Proposal

> **Status:** `IMPLEMENTED ON FEATURE BRANCH` (proposal text below; expansion in §2.1)  
> **Last updated:** 2026-11-08  
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

### 2.1 Expansion (MATRIX `2026-11-08.1`)

`POST`/`PATCH /season-templates` additionally accept:

- `capture_season_id` — capture independent snapshots of the selected kinds from this tenant season through the allowlisted adapters (foreign season → **404**; source status must be draft/active/closed). Snapshot-only kinds (`contract_definitions`) are not captured.
- `items[].snapshot` — `{anchor?: date, rows: [...]}` of allowlisted fields only. Any field outside the allowlist, non-scalar value, bad date or duplicate key → **422** `SEASON_TEMPLATE_SNAPSHOT_INVALID` (never silently trimmed).

On `PATCH`, an item that stays in the template without a new or re-captured snapshot keeps its stored snapshot. Template responses expose `has_snapshot`, `snapshot_row_count`, `snapshot_anchor`, `snapshot_codes` per item — never the rows.

New stable codes: `SEASON_TEMPLATE_SNAPSHOT_INVALID`, `SEASON_COPY_SOURCE_REQUIRED`, `SEASON_COPY_SNAPSHOT_REQUIRED` (all 422). Preview lines add conflict `TARGET_LOCKED` and exclusion `DATE_ANCHOR_MISSING`; `summary.date_transforms` carries before/after per date field. Executing a kind additionally requires its module permission (`contracts.create`, `season_projects.create`, `season_projects.manage_phases`, `tasks.create`) → **403** otherwise.

## 3. Preview

> `source_season_id` is **required** for `from_season`; for `from_template` it is optional when every selected kind has a snapshot in the template (`source_season_id` is then `null` on the preview and run).

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

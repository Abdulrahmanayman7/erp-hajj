# S08 — Preview & Conflict Resolution Design

> **Status:** `PROPOSED` · S08-D04 / S08-D05

## 1. Preview generation

1. Authorize `season_copy.preview`.
2. Validate mode, source, target (BR-04).
3. Intersect request selection with APPROVED matrix kinds for current/pinned version.
4. For each adapter: `plan()` → adds, changes, excludes, conflicts, date transforms.
5. Redact summaries (strip DENY attributes / PII keys).
6. Canonicalize plan JSON → `content_hash`.
7. Persist preview + lines; status=`valid`; set `expires_at` (proposed default **30 minutes**, **OPEN-Q**).

## 2. Preview display requirements

| Element | Source |
|---|---|
| Records to add | `action=add` lines |
| Proposed changes | `action=change` + field before/after (non-PII) |
| Conflicts | `action=conflict` + `conflict_code` |
| Date transformations | embedded in change summaries |
| Exclusions + reasons | `action=exclude` + reason codes |

**Never** include: pilgrim names, document numbers, phones of pilgrims, risk/complaint narratives copied from DENY tables, bank secrets.

## 3. Invalidation

Triggers (any → `status=invalidated`):

- Source season row updated / closed→reopened fingerprint bump.
- Target season updated (including status change).
- Template items changed.
- Matrix version bump.
- Selection or date_mapping change (client must create new preview).
- TTL expiry → `expired`.

Execute with non-`valid` preview → **409** `SEASON_COPY_PREVIEW_STALE`.

## 4. Conflict codes

| Code | Meaning | Default resolution |
|---|---|---|
| `DUPLICATE_KEY` | Natural key exists on target | `skip` or `overwrite_system` if system_owned & not manual |
| `MANUAL_MODIFIED` | Provenance shows manual touch | `skip` (override explicit) |
| `MISSING_DEPENDENCY` | e.g. program code missing for entitlement | `fail` or exclude dependency chain |
| `DATE_ANCHOR_MISSING` | Cannot map dates | exclude field / line |
| `KIND_NOT_AUTHORIZED` | Filtered by matrix | exclude |

Unresolved **blocking** conflicts → execute rejected `SEASON_COPY_CONFLICT_UNRESOLVED`.

## 5. Idempotency

- Natural keys stable across reruns.
- Provenance links target rows to source + `copy_run_id`.
- Second preview+execute with identical selection updates system-owned rows only when change detected; no duplicate inserts.

## 6. Manual-change protection

- Domain updates after copy should set `manual_touched_at` (observer or explicit domain hook — **OPEN-Q** for least-invasive approach).
- Until hooks exist, v1 may treat any non-provenance-tracked field divergence as conflict `MANUAL_MODIFIED` during plan().

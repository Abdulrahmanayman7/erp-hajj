# S08 — Business Rules

> **Status:** `PROPOSED — Part 0`  
> Binding: [OWNER_DECISIONS.md](OWNER_DECISIONS.md) · Matrix: [SAFE_COPY_ENTITY_MATRIX.md](SAFE_COPY_ENTITY_MATRIX.md)

## BR-01 Deny by default

No entity kind is copied unless its matrix status is **APPROVED** for the pinned `matrix_version`. CANDIDATE / DEFER / DENY / unlisted → exclude or reject with `SEASON_COPY_KIND_DENIED`.

## BR-02 Hard denylist

Never copy: pilgrim PII; operational transactions/history; risks/complaints; inventory movements; transport/accommodation execution; allocations/assignments; audit logs; historical execution timestamps.

## BR-03 Hybrid modes

`from_season` and `from_template` share allowlist, preview, conflict, transaction, and audit rules.

## BR-04 Season gates

- Source (season mode): status ∈ {`draft`,`active`,`closed`}, same tenant.
- Target: status = `draft`, same tenant.
- Target leaving draft before execute → fail `SEASON_COPY_TARGET_NOT_DRAFT`.

## BR-05 Date mapping

Only adapter-declared planning date fields. Strategy pinned on preview (`season_start_delta` default proposal). Execution timestamps never mapped. Preview shows before/after.

## BR-06 Mandatory preview

Execute requires `preview_token` + matching `content_hash`. Reruns require a **new** preview.

## BR-07 Preview invalidation

Invalidate when any of changes: source fingerprint, target fingerprint, matrix version, selection, date mapping, template content (if mode=template), or target season status.

## BR-08 Conflicts & manual protection

Detect duplicates and `MANUAL_MODIFIED` via provenance. Default skip manual rows. Overrides require explicit resolution + `season_copy.execute`.

## BR-09 Idempotent reruns

Re-running the same approved selection into the same draft target must not create duplicate natural keys; system-owned rows may update per policy; manual rows remain protected.

## BR-10 Atomicity

Adapter applies succeed together or not at all (domain writes). Attempt history always recorded.

## BR-11 No S06

No project entity kinds until D08 gates satisfied.

## BR-12 Authorization

All actions Policy-gated. Cross-tenant → 404. `tenant_id` not from client.

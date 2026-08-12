# ADR-0015: Semantic Audit Trail and Immutable History

- **Status:** Accepted — **Implemented** (Sprint 018)
- **Date:** 2026-08-12
- **Sprint:** 018 (Audit Trail)
- **Deciders:** ERP Hajj product / engineering (documentation lock)

## Implementation note (2026-08-12)

Shipped: hybrid `audit_logs` table; `Core/Audit` (`AuditRecorder`, `SensitiveFieldSanitizer`, event mapper) dual-writing from `AuthorizationSecurityEvent` / `AuthSecurityEvent` (log listeners preserved); `Modules/Audit` list/show API + Policy (`audit_logs.view`); Vue `/app/audit` + detail; Pest `AuditTest` 13/13; full Pest 365; Vitest 208; no export/pruning/hash-chain/observers.

## Context

Domain modules already emit semantic security events via temporary sinks:

- `AuthorizationSecurityEvent` + `AuthorizationSecurity` (RBAC + business Actions)
- `AuthSecurityEvent` + `AuthSecurity` (login / reset / blocks)

Listeners currently write **application logs only**. Cross-cutting docs require an immutable, tenant-scoped Audit Trail viewer (`audit_logs.view`) with correlation IDs, safe before/after values, and no secret leakage — but storage design, retention, observers vs semantic events, transaction failure policy, and export were still TBD.

Risks if left undecided:

- Generic Eloquent observers capturing unsafe fields and noisy diffs
- Zero-fill / client-controlled tenant or actor fields
- Audit rows vanishing when Users/entities delete
- False “success” audits after rolled-back transactions
- Merging Audit with Notifications or Dashboard Attention
- Premature export / SIEM / hash-chain complexity

## Decision

### 1. Semantic events, not generic observers

Business/Auth/Tenancy Actions own event codes and safe payloads. `Core/Audit` persists them. **Do not** auto-audit every Eloquent update.

### 2. Keep existing event constants

Do not rename `TASK_ASSIGNED`, `CONTRACT_APPROVED`, `LOGIN_SUCCESS`, etc. `AuditRecorder` consumes existing events; log listeners remain for ops logs (not a substitute for `audit_logs`).

### 3. Single hybrid `audit_logs` table

Nullable `tenant_id` per DATABASE_PRINCIPLES. Hand-rolled schema — **no** third-party auditing package.

### 4. Append-only immutability

No update/delete API or UI. Actor FK `nullOnDelete`; no polymorphic entity FK.

### 5. Actor model

`actor_type`: `user` | `system` | `platform` + optional `actor_user_id` + `actor_label` snapshot. Never fake a User for system jobs.

### 6. Snapshots + allow-listed before/after

`entity_number` / `entity_label` snapshots; before/after only safe changed attributes; central denylist for secrets/paths/tokens.

### 7. Transaction policy

Domain audits participate in the **same DB transaction** as the mutation (fail closed). Auth events: persist after outcome; do not revoke successful auth solely because audit insert failed (log critically).

### 8. Retention

Indefinite during MVP. No pruning job in Sprint 018.

### 9. Permissions

Seed `audit_logs.view` (tenant-wide). Reserve `audit_logs.export` but **defer** export format/endpoint. No edit/delete permissions.

### 10. Correlation, IP, UA

Mandatory `correlation_id`; optional IP/UA from server Request; `source` derived server-side.

### 11. Boundaries

Notifications ≠ Audit (ADR-0013). Dashboard Attention ≠ Audit (ADR-0014). No Sprint 018 Dashboard audit widget. No hash-chain. No generic metadata query language.

### 12. Viewer surface

`Modules/Audit` + Vue `modules/audit`: `GET /api/v1/audit-logs` (+ show); routes `/app/audit`, `/app/audit/:id`; sidebar **سجل التدقيق**.

## Consequences

- Positive: mechanical migration from current `->record()` call sites; clear security model; durable history.
- Trade-off: dual sinks (logs + DB) until/unless log listener is slimmed — acceptable.
- Trade-off: auth availability exception vs strict fail-closed — documented.
- Trade-off: deferred export leaves catalog permission unseeded until format CR.
- Implementation must register listeners carefully to avoid **duplicate DB rows** for one event.

## References

- [14-audit-trail/](../09-modules/14-audit-trail/)
- [AUDIT_TRAIL.md](../06-security/AUDIT_TRAIL.md)
- [DATABASE_PRINCIPLES.md](../03-database/DATABASE_PRINCIPLES.md)
- ADR-0013 (Notifications), ADR-0014 (Dashboard)

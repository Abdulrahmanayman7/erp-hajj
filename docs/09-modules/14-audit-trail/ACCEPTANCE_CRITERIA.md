# Audit Trail — Acceptance Criteria

> **Status:** Implemented (Sprint 018)
> **Last updated:** 2026-08-12

## Specification

- [x] Module docs complete per MODULE_TEMPLATE (purpose, scope, permissions, rules, data model, API, UI, tests, acceptance).
- [x] ADR-0015 accepted for semantic events, immutability, sanitization, transaction policy, retention.
- [x] Existing event inventory mapped (`AuthorizationSecurityEvent`, `AuthSecurityEvent`, planned tenancy codes).
- [x] Export deferred; `audit_logs.export` reserved only.
- [x] Cross-cutting [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md) updated to match.

## Implementation

- [x] `audit_logs` migration applied; **0** other Audit tables.
- [x] `Core/Audit` recorder + sanitizer; listeners persist from existing security events without renaming codes.
- [x] `GET /api/v1/audit-logs` and `GET /api/v1/audit-logs/{id}` enforce `audit_logs.view`; no write endpoints.
- [x] Unauthorized / cross-tenant → 403 / 404 as specified.
- [x] Tenant isolation proven; actor/entity delete preserves history.
- [x] Secrets never in audit values or API responses.
- [x] Domain mutation rollback leaves no success audit; committed ops produce audit.
- [x] `DOCUMENT_DOWNLOADED` audited without storage paths.
- [x] Notification read / Dashboard GET do not create audit noise.
- [x] Vue `/app/audit` + details; sidebar سجل التدقيق; Arabic labels; empty/loading/error.
- [x] Deep links allow-listed and Policy-checked.
- [x] `audit_logs.view` seeded; Owner/GM/Auditor templates updated.
- [x] Pest + Vitest matrices green; pint/type-check/build green.
- [x] No export UI/endpoint until format CR.
- [x] No generic Eloquent audit observers; no hash-chain; no Dashboard audit KPI.

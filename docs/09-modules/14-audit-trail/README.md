# Module: Audit Trail (سجل التدقيق)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Record and expose the immutable audit trail of critical operations across all modules. The recording mechanism lives in `Core/Audit` (used by every module); this module provides the viewing/export surface.

## Scope

- Central audit recording (via `Core/Audit`) for all critical operations.
- Audit log viewing with filters, permission-controlled (`audit_logs.view`).
- Audit export (`audit_logs.export`) — current tenant data only.
- Audit History Panel component data (per-entity audit view used across module UIs).

## Out of scope

- Editing or deleting audit records (impossible by design for normal users).
- SIEM integrations, anomaly detection (future scope).

## References

- [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md) — canonical field and event definitions

# Audit Trail — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A cannot view or export tenant B audit records.
- Recording: representative event from each module produces the correct record (fields, old/new values, actor, IP).
- Secrets: password change and login events never store password/token values (explicit assertion).
- Immutability: no update/delete route exists; direct attempts fail.
- Masking: sensitive fields (per decided list) appear masked in responses.
- Permissions: `audit_logs.view` and `audit_logs.export` matrices.
- Retention/business-record deletion: deleting a record keeps its audit rows.
- Frontend: filters, expandable diffs, Audit History Panel embedding.
- E2E: perform a contract approval → find it in audit logs → view it on the contract's history panel.

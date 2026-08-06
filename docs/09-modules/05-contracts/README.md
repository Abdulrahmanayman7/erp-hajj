# Module: Contracts (العقود)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Manage contract administration and the controlled contract lifecycle: Draft → Review → Approval → Signature → Execution → Closure or Renewal.

## Scope

- Contracts CRUD with configurable categories.
- Controlled lifecycle transitions via action endpoints, each recording actor, timestamp, and optional comments.
- Attachments (preserved), status history, approval history.
- Contract expiry notifications.

## Out of scope

- Procurement and supplier management (future scope).
- Financial accounting of contract values (future scope) — value/currency are informational fields only.

## Initial category examples (configurable)

Employee, Supervisor, Hotel, Catering, Transportation, Supplier, Consultant contracts.

## References

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) (Workflow 2) · [12-notifications/](../12-notifications/)

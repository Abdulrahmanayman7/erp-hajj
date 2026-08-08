# ADR-0002: Modular Monolith

> **Status:** Accepted
> **Last updated:** 2026-08-06

## Context

The MVP contains 15 interrelated administrative modules sharing one domain (campaign governance), one database (MySQL), and heavy cross-module workflows (Meeting → Decision → Task; Contract lifecycle). The team is two developers. The system must be scalable, secure, auditable, and maintainable.

## Decision

Build the backend as a **modular monolith**: a single deployable Laravel 12 application organized into explicit business modules (one per MVP area), with enforced internal layering:

- Thin controllers; Form Requests for validation; Actions for use cases; Services only for reusable domain operations; Policies for authorization; API Resources for responses; Events and Jobs only when justified.
- Cross-cutting concerns (tenancy scoping, audit trail) live in a shared support layer used by all modules.

Microservices are explicitly rejected for the MVP.

## Consequences

### Positive

- Transactions and workflow chains spanning modules remain simple and consistent (one database, one process).
- Tenant isolation and audit can be enforced centrally in one shared layer.
- Two developers can move fast without distributed-systems overhead (service discovery, network failure handling, distributed tracing).
- Clear module boundaries keep the codebase maintainable and leave a future extraction path open if ever needed.

### Negative / Accepted Trade-offs

- The whole backend scales and deploys as one unit; horizontal scaling is at the application level.
- Module boundary discipline must be enforced through code review and Cursor rules, since the compiler will not enforce it.

## Notes

- The concrete module layout is documented in [BACKEND_STRUCTURE.md](../02-architecture/BACKEND_STRUCTURE.md); the folder mechanism is finalized at scaffolding time.

# Module: Audit Trail (سجل التدقيق)

> **Status:** Implemented (Sprint 018)
> **Last updated:** 2026-08-12
> ADR: [ADR-0015](../../10-decisions/ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md)

## Purpose

Provide a **tenant-scoped, append-only operational and security history** so authorized Users can answer:

- Who did what?
- When?
- To which entity (with durable snapshots)?
- In which tenant / execution context?
- What changed (safe before/after)?
- What correlation/request context exists?
- Can authorized users search and filter that history?

Audit Trail is **accountability history**. It is **not** application logs, Notifications, analytics/BI, event sourcing, SIEM, or a user-activity replay system.

## Scope (MVP / Sprint 018 implementation)

- Hand-rolled persistence in `Core/Audit` (no third-party auditing package).
- Single hybrid table `audit_logs` (see [DATA_MODEL.md](DATA_MODEL.md)).
- Semantic event recording from existing domain sinks (`AuthorizationSecurityEvent`, `AuthSecurityEvent`) plus tenancy/platform events when those Actions ship.
- Read API: list + details only (`audit_logs.view`).
- Arabic RTL UI: `/app/audit`, `/app/audit/:id`, sidebar **سجل التدقيق**.
- Optional **Audit History Panel** (same list API filtered by entity) for module detail pages.
- Seed `audit_logs.view`; grant Owner / GM / Auditor templates.
- Pest + Vitest matrices in [TEST_PLAN.md](TEST_PLAN.md).

## Out of scope (MVP)

- Edit / delete / soft-correct audit rows.
- CSV/PDF/Excel **export** (permission `audit_logs.export` **reserved**; format + endpoint deferred — see [PERMISSIONS.md](PERMISSIONS.md)).
- Cryptographic hash-chaining / blockchain tamper evidence.
- Generic Eloquent model observers that auto-audit every update.
- SIEM integrations, anomaly detection, real-time streaming.
- Dashboard “recent audit” KPI/widget (Dashboard Attention remains business SoRs — [13-dashboard/](../13-dashboard/)).
- Arbitrary JSON metadata query language.
- Pruning / automatic retention deletion during MVP.
- Platform Super Admin console for all-tenant audit search (tenant viewers see their tenant; platform-null rows stay platform-side).

## Personas (capability-driven)

| Template | Typical use |
|---|---|
| Tenant Owner / General Manager | Tenant-wide audit investigation |
| Auditor | Read-only accountability review (`audit_logs.view` + existing `*.view`) |
| Department Manager / Supervisor / Employee | **No** default audit access |
| Read-only | No audit access |

Visibility is **never** decided by role-name checks in Policies.

## Ownership split

| Concern | Owner |
|---|---|
| Semantic event name + safe payload | Business / Auth / Tenancy module Action |
| Persist, sanitize, query, display | Audit Trail (`Core/Audit` + `Modules/Audit`) |
| Recipient attention | Notifications (ADR-0013) — independent |

```text
Domain / Auth Action
  → AuthorizationSecurityEvent | AuthSecurityEvent (existing)
      → Log* listener (ops logs — keep)
      → AuditRecorder listener (audit_logs row — new)
```

Do **not** invent duplicate event codes. Canonical catalog: [BUSINESS_RULES.md](BUSINESS_RULES.md) § Event catalog.

## Binding decisions

| Topic | Decision |
|---|---|
| Storage | Single `audit_logs` table; hand-rolled `Core/Audit` |
| Permissions | `audit_logs.view` (seeded); `audit_logs.export` reserved / deferred |
| Immutability | Append-only; no PATCH/DELETE routes |
| Observers | **Forbidden** for automatic model auditing |
| Before/after | Allow-listed changed attributes only; secrets excluded |
| Retention | Indefinite during MVP |
| Export | Deferred |
| Integrity crypto | Not in MVP |
| Transaction | Domain audits in same DB transaction as mutation (ADR-0015) |
| Auth audits | Persist after auth outcome; auth availability not rolled back if audit write fails (logged) |

## References

- Cross-cutting: [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md)
- ADR: [ADR-0015](../../10-decisions/ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md)
- Notifications boundary: [ADR-0013](../../10-decisions/ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md)
- Temporary sinks (today): `AuthorizationSecurityEvent`, `AuthSecurityEvent` → application logs only

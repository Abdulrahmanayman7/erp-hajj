# Module: Contracts (العقود)

> **Status:** Implemented (Sprint 009)
> **Last updated:** 2026-08-09

## Purpose

Manage each tenant’s **business/legal agreement records** and the controlled contract lifecycle defined in [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) Workflow 2:

**Draft → Review → Approval → Signature → Execution → Closure or Renewal**

Contracts are a reusable legal/business record layer for Hajj campaign companies — not payroll, procurement, accounting, or document DMS.

## Domain separation (non-negotiable)

| Concept | Meaning | Owned by |
|---|---|---|
| **Contract** | Business/legal agreement record | This module |
| **User** | Login / auth account | Users & Authorization |
| **Employee** | Personnel record (optional link) | Employees |
| **OrganizationUnit** | Org hierarchy (optional responsible unit) | Organization Structure |
| **Document** | File storage / attachments | Documents (later sprint) |
| **Supplier / Vendor** | External vendor master data | Future / out of MVP unless Change Request |
| **Task / Meeting / Decision** | Governance collaboration | Separate modules |

Do **not** merge suppliers, payments, e-signature, or DMS into Contracts.

## Sprint 009 scope

| In scope | Out of scope |
|---|---|
| `contract_categories` catalog (tenant-owned) | Supplier/vendor master module |
| `contracts` + server-generated `CTR-######` | Payment schedules, invoicing, ledger, tax |
| Controlled lifecycle action endpoints | E-signature providers / government integrations |
| Append-only status transition history | Full Documents upload/download (deferred) |
| Optional `employee_id` / `organization_unit_id` | Procurement workflows |
| Informational `value` + `currency` (SAR default) | Accounting of contract values |
| Expiry job + expiring-soon filter/badge | Notification delivery (hook only) |
| Permissions, Policies, Arabic RTL UI | SoftDeletes dual-model; free status editing |
| Audit events + Pest/Vitest matrices | Dashboard KPI widgets (Dashboard sprint) |

## Module placement

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/Contracts/` |
| Frontend | `frontend/src/modules/contracts/` |
| Config | `backend/config/contracts.php` (prefix, pad, currency, expiring window) |

## Personas

| Persona | Typical use |
|---|---|
| Tenant Owner / General Manager | Full lifecycle oversight; approve/sign/close/renew |
| Department Manager | Create/update drafts; review; view unit-related contracts |
| Auditor | View contracts + history (read) |
| Supervisor / Employee | No default write access |

Exact default role grants: [PERMISSIONS.md](PERMISSIONS.md).

## Architectural decisions

| Topic | Decision | Doc |
|---|---|---|
| Categories | Tenant-owned catalog active/inactive; unused hard-delete only (ADR-0006) | [DATA_MODEL.md](DATA_MODEL.md) |
| Numbering | `CTR-000001…`, sequence + `FOR UPDATE`, immutable | [DATA_MODEL.md](DATA_MODEL.md) |
| Party | Counterparty free-text + optional employee link; tenant is implicit first party | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Attachments | Owned by Documents (Sprint 013 — implemented; ADR-0010); **المستندات** on contract details | [09-documents/](../09-documents/) |
| Transition history | `contract_status_transitions` authoritative (incl. correlation ID) | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Sign | Manual attestation only — not e-sign | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Renewal | One direct child; transactional; unique `renewed_from_contract_id` | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Delete | Hard delete **draft-only** (never transitioned); else cancel/close | [BUSINESS_RULES.md](BUSINESS_RULES.md) |

## References

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) · [BUSINESS_RULES.md](../../01-business/BUSINESS_RULES.md)
- [04-employees-and-supervisors/](../04-employees-and-supervisors/) · [03-organization-structure/](../03-organization-structure/)
- [09-documents/](../09-documents/) · [12-notifications/](../12-notifications/)
- [MODULE_TEMPLATE.md](../../02-architecture/MODULE_TEMPLATE.md)

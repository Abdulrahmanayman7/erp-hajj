# Business Rules

> **Status:** Approved (stated rules only; the rest TBD)
> **Last updated:** 2026-08-06

## Purpose

Collect the cross-module business rules that are explicitly agreed. Module-specific rules live in each module's `BUSINESS_RULES.md` under [docs/09-modules/](../09-modules/). Rules must not be invented; anything unstated is TBD.

## Domain Separation Rules

- **Decisions are a separate business entity** — never merged into Tasks.
- **Assets and Custodies are separate entities**: an Asset is a physical or registered resource; a Custody is the handover of an asset to an employee or supervisor.
- **Inventory Items and Assets are separate concepts**: inventory is quantity-tracked via transactions; assets are individually tracked.
- **Employee and Supervisor share one identity record**: operational reporting uses `employees.supervisor_id`; RBAC role `supervisor` is separate; unit manager (`organization_units.manager_user_id`) is separate. No duplicate personnel records. See [04-employees-and-supervisors/](../09-modules/04-employees-and-supervisors/).

## Tenancy

- Every tenant's data is strictly isolated; cross-tenant access must be impossible.
- Each tenant user belongs to one tenant in the MVP; multi-organization membership is future scope.

## Governance (Workflow 1)

- A meeting may create one or more decisions; a decision may also exist independently of any meeting.
- A decision may generate one or more tasks; tasks must have responsible users/employees.
- Completing one task does not close the decision unless all required tasks are completed.

## Contracts (Workflow 2)

- Status transitions are controlled and each records actor, timestamp, and optional comments.
- Deleting an approved or executed contract is restricted; Sprint 009: hard delete **draft-only**; otherwise cancel/close/expire/renew (no SoftDeletes dual model).
- Contract expiry notifications must be supported (delivery in Notifications sprint; Contracts owns expiry status + hooks).
- Numbering: `CTR-######` tenant sequence (see [05-contracts/](../09-modules/05-contracts/)).

## Assets and Custodies (Workflow 3)

- An asset cannot be actively assigned to more than one person at the same time.
- Custody history is immutable except through controlled correction permissions.

## Inventory (Workflow 4)

- Quantities are calculated from transactions; manual stock editing is restricted to permissioned adjustments with a reason.
- Negative stock is blocked unless explicitly configured later.

## Access and Audit

- Authorization is dynamic role- and permission-based (`module.action`); no persona bypasses Policies/Gates — including the Tenant Owner.
- Exceptional access (e.g. Platform Super Admin into tenant data) must be explicit, permission-controlled, and audited.
- All critical operations across all modules are audited; no module may bypass permissions or audit logging.

## TBD (not yet defined — do not assume)

- Numbering/reference schemes for **decisions, tasks**: TBD. (Contracts numbering locked: `CTR-######`. Meetings numbering locked Sprint 010: `MTG-######` — see [06-meetings/](../09-modules/06-meetings/).)
- Approval hierarchies and delegation rules: TBD.
- Backup Supervisor behavior: future vision, TBD unless explicitly approved for the MVP.
- Recurring-task automation details: TBD.
- Document retention periods and confidentiality-level definitions: TBD.
- Task deadline/SLA rules: TBD.
- Negative-stock configuration mechanism: TBD.

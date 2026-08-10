# Users and Personas

> **Status:** Approved (default templates). Sprint 006 locks which templates seed as system roles — see [02-users-and-authorization/BUSINESS_RULES.md](../09-modules/02-users-and-authorization/BUSINESS_RULES.md). Sprint 007–011 implemented. Default Decision grants: [07-decisions/PERMISSIONS.md](../09-modules/07-decisions/PERMISSIONS.md). Sprint 012 Tasks **specified** — [08-tasks/PERMISSIONS.md](../09-modules/08-tasks/PERMISSIONS.md) · [ADR-0009](../10-decisions/ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md).
> **Last updated:** 2026-08-10

## Purpose

Describe the default user personas of the platform. **These personas are default templates only** — the system uses **dynamic roles and permissions**; roles are never hardcoded as the only possible roles.

## 1. Platform Super Admin (مدير المنصة)

**Scope:** Manages the entire platform. Creates and manages tenant organizations; activates or suspends tenants; manages platform-level settings. **Does not automatically access tenant business data** — such access must be explicitly authorized and audited.

**Responsibilities:** Tenant lifecycle management, security oversight, technical administration, monitoring, platform configuration.

**Authorization:** Separate **platform** permission layer (`platform_tenants.*`). Platform users are not assigned tenant roles and do not appear in tenant user lists.

## 2. Tenant Owner (مالك المنشأة)

**Scope:** Highest authority inside one tenant. Manages users, roles, permissions, and organization settings. Views the executive dashboard. Handles major approvals based on assigned permissions.

**Responsibilities:** Executive oversight, governance, final approvals, user and role governance, audit review.

**Important:** The Tenant Owner does **not** bypass authorization automatically — all actions pass Policies/Gates. System role `code`: `tenant_owner`. Multiple owners allowed; last-owner protection applies.

## 3. General Manager (المدير العام)

**Scope:** Oversees tenant operations. Reviews departments, contracts, meetings, decisions, and tasks. Receives administrative reports and alerts. May approve selected operations according to permissions (Sprint 011 default: full `decisions.*` for General Manager template).

**Responsibilities:** Operational leadership, decision-making, follow-up, performance review, cross-department coordination.

**System role `code`:** `general_manager`.

## 4. Department Manager (مدير الإدارة)

**Scope:** Manages assigned organizational units. Views employees and tasks within authorized departments. Creates meetings, decisions, and tasks where permitted. Reviews department performance.

**Responsibilities:** Department planning, task assignment, employee supervision, approval workflows, reporting.

**System role `code`:** `department_manager`. Sprint 007 seeds `organization_units.*` into role templates per [03-organization-structure/PERMISSIONS.md](../09-modules/03-organization-structure/PERMISSIONS.md); **row-level “only my units” scope is out of Sprint 007** (permission-wide within tenant). Unit **manager** (`manager_user_id`) means the user responsible for managing that organizational unit — **not** an employee direct/HR line supervisor and **not** an RBAC grant.

## 5. Supervisor (المشرف)

**Scope:** Supervises assigned employees or teams. Receives and updates tasks. Participates in meetings. Accesses authorized documents and assets.

**Responsibilities:** Supervision, execution follow-up, reporting, team coordination.

**System role `code`:** `supervisor`. Sprint 008 personnel reporting uses `employees.supervisor_id` (employee→employee) — distinct from this RBAC role and from `organization_units.manager_user_id`. Suggested default grant when Employees ships: `employees.view` (see [04-employees-and-supervisors/PERMISSIONS.md](../09-modules/04-employees-and-supervisors/PERMISSIONS.md)).

## 6. Employee (الموظف)

**Scope:** Views and updates assigned tasks. Accesses permitted documents. Participates in meetings. Views own profile and assigned custodies.

**Responsibilities:** Task execution, status updates, document access, meeting participation, custody responsibility.

**System role `code`:** `employee`. Personnel **Employee** records are specified in Sprint 008; a login User may exist without an Employee and vice versa.

## 7. Reviewer / Auditor (المراجع / المدقق)

**Scope:** Read-only or controlled review access. Reviews audit logs, contracts, decisions, documents, and workflows. Cannot edit operational records unless explicitly permitted.

**Responsibilities:** Compliance review, audit inspection, data verification, governance reporting.

**System role `code`:** `auditor`.

## 8. Read-only User / Visitor (مستخدم اطلاع فقط)

**Scope:** Views only authorized information. Cannot create, edit, approve, delete, or export sensitive data unless explicitly granted.

**System role `code`:** `read_only`.

## Rules

- Roles and permissions are **dynamic** (see [PERMISSION_MODEL.md](../06-security/PERMISSION_MODEL.md)); these personas seed default **system** role templates only. Tenants may create **custom** roles.
- A user may hold **multiple roles**; effective permissions are the union of active roles.
- Permissions come **only through roles** (no direct user permissions in MVP).
- Every user passes Policies/Gates — no persona bypasses authorization.
- Each tenant user belongs to **one tenant** in the MVP; multi-organization membership is future scope unless approved later.
- Exact Sprint 006 default permission sets: [02-users-and-authorization/PERMISSIONS.md](../09-modules/02-users-and-authorization/PERMISSIONS.md).

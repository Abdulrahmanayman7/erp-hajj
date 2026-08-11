# Users and Personas

> **Status:** Approved (default templates)
> **Last updated:** 2026-08-06

## Purpose

Describe the default user personas of the platform. **These personas are default templates only** — the system uses **dynamic roles and permissions**; roles are never hardcoded as the only possible roles.

## 1. Platform Super Admin (مدير المنصة)

**Scope:** Manages the entire platform. Creates and manages tenant organizations; activates or suspends tenants; manages platform-level settings. **Does not automatically access tenant business data** — such access must be explicitly authorized and audited.

**Responsibilities:** Tenant lifecycle management, security oversight, technical administration, monitoring, platform configuration.

## 2. Tenant Owner (مالك المنشأة)

**Scope:** Highest authority inside one tenant. Manages users, roles, permissions, and organization settings. Views the executive dashboard. Handles major approvals based on assigned permissions.

**Responsibilities:** Executive oversight, governance, final approvals, user and role governance, audit review.

**Important:** The Tenant Owner does **not** bypass authorization automatically — all actions pass Policies/Gates.

## 3. General Manager (المدير العام)

**Scope:** Oversees tenant operations. Reviews departments, contracts, meetings, decisions, and tasks. Receives administrative reports and alerts. May approve selected operations according to permissions.

**Responsibilities:** Operational leadership, decision-making, follow-up, performance review, cross-department coordination.

## 4. Department Manager (مدير الإدارة)

**Scope:** Manages assigned organizational units. Views employees and tasks within authorized departments. Creates meetings, decisions, and tasks where permitted. Reviews department performance.

**Responsibilities:** Department planning, task assignment, employee supervision, approval workflows, reporting.

## 5. Supervisor (المشرف)

**Scope:** Supervises assigned employees or teams. Receives and updates tasks. Participates in meetings. Accesses authorized documents and assets.

**Responsibilities:** Supervision, execution follow-up, reporting, team coordination.

## 6. Employee (الموظف)

**Scope:** Views and updates assigned tasks. Accesses permitted documents. Participates in meetings. Views own profile and assigned custodies.

**Responsibilities:** Task execution, status updates, document access, meeting participation, custody responsibility.

## 7. Reviewer / Auditor (المراجع / المدقق)

**Scope:** Read-only or controlled review access. Reviews audit logs, contracts, decisions, documents, and workflows. Cannot edit operational records unless explicitly permitted.

**Responsibilities:** Compliance review, audit inspection, data verification, governance reporting.

## 8. Read-only User / Visitor (مستخدم اطلاع فقط)

**Scope:** Views only authorized information. Cannot create, edit, approve, delete, or export sensitive data unless explicitly granted.

## Rules

- Roles and permissions are **dynamic** (see [PERMISSION_MODEL.md](../06-security/PERMISSION_MODEL.md)); these personas seed default role templates only.
- Every user passes Policies/Gates — no persona bypasses authorization.
- Each tenant user belongs to **one tenant** in the MVP; multi-organization membership is future scope unless approved later.

## TBD

- Which personas ship as seeded default roles and their exact default permission sets: TBD.

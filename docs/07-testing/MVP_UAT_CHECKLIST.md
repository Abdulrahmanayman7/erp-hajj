# MVP UAT Checklist

> **Status:** Ready for execution — **not** marked passed unless scenarios were actually run
> **Last updated:** 2026-08-12

Use a dedicated **UAT tenant** (not production data). Prefer seeded/bootstrap roles:

| Persona | Typical role key | Notes |
|---|---|---|
| Owner | `tenant_owner` | Full admin within tenant |
| GM | `general_manager` | Broad view + settings view |
| Department Manager | department manager template | Scoped management |
| Supervisor | supervisor template | Team operations |
| Employee | employee template | Limited self-service |
| Auditor | auditor template | `audit_logs.view` |

Do **not** seed fake production business data into the production database.

For each scenario: record Pass / Fail / Blocked and tester initials.

---

## UAT-01 Authentication

**Precondition:** Active user exists; tenant is `active`.

1. Open SPA login.
2. Log in with valid credentials.
3. Confirm redirect into `/app` (Dashboard when permitted).
4. Log out; confirm protected routes redirect to login.
5. Attempt login with wrong password; confirm failure without leaking tenant existence details beyond product norms.
6. (If available) disable user; confirm subsequent API use is blocked.

**Expected:** Session established only for active users on active tenants; logout clears access.

---

## UAT-02 RBAC

**Precondition:** Two users — Owner and Employee (minimal permissions).

1. As Employee, confirm sidebar hides admin modules (Users/Roles/Audit as applicable).
2. As Employee, attempt direct URL to `/app/users` or `/app/audit` → UI deny / 403.
3. As Owner, confirm Users/Roles management works.
4. Create a custom role; assign a subset of permissions; assign to a user; verify grants.
5. Re-run RBAC seeder/provision on staging only if ops requests — confirm **custom role grants preserved**.

**Expected:** Backend denies unauthorized actions (403); UI mirrors but does not replace backend.

---

## UAT-03 Organization → Employee

**Precondition:** `organization_units.*` and `employees.*` grants.

1. Create an org unit.
2. Create an employee linked to that unit.
3. Optionally link a User.
4. Activate/deactivate employee as designed.

**Expected:** Employee appears in lists; cross-tenant IDs fail with 404 if attempted via API tools.

---

## UAT-04 Meeting → Decision → Task (governance)

**Precondition:** Workflow permissions for meetings, decisions, tasks.

1. Create meeting → schedule → add attendees/agenda → start → minutes → recommendation → complete.
2. Create Decision from final recommendation (or standalone) → submit → approve.
3. Create Task linked to Decision → assign → start → progress → complete.
4. Attempt to close Decision with open tasks → expect close gate denial.
5. Complete remaining tasks → close Decision.

**Expected:** Illegal transitions blocked; close gate enforced.

---

## UAT-05 Documents

**Precondition:** `documents.*` grants; PHP upload limits configured.

1. Upload allowed file type ≤ 20 MiB.
2. Link to an entity (e.g. Contract or Task) if UI supports.
3. Download with permission; confirm unauthorized role cannot download.
4. Archive → restore.
5. Attempt host entity delete while document linked → expect guard where designed.

**Expected:** No public URL; no storage path in JSON; unauthorized download denied.

---

## UAT-06 Inventory

**Precondition:** warehouse/inventory permissions.

1. Create warehouse → category → item.
2. Receipt → Issue → Transfer → Adjustment.
3. Confirm balances never go negative.
4. If minimum stock set, confirm low-stock notification appears for eligible recipients.

**Expected:** Decimal quantities stable; no negative stock.

---

## UAT-07 Assets & Custodies

**Precondition:** assets permissions.

1. Create asset → assign custody → return.
2. Move through maintenance/restore or retire/lost as allowed.
3. Confirm inventory stock unchanged by asset actions.

**Expected:** Single active custody rules held; inventory untouched.

---

## UAT-08 Notifications

**Precondition:** Domain actions that emit notifications; scheduler/worker running in UAT env.

1. Trigger a curated event (task assign, contract expiring soon scan, etc.).
2. Confirm in-app notification for recipient User.
3. Mark read / read-all.
4. Confirm disabled users are skipped.

**Expected:** No `notifications.*` permission required for own inbox; no cross-user leakage.

---

## UAT-09 Dashboard

**Precondition:** `dashboard.view` plus section view permissions as applicable.

1. Open `/app`.
2. Confirm KPIs/sections match permissions (sparse omit).
3. Confirm figures reflect current tenant SoR after prior UAT actions.

**Expected:** No other-tenant data; unauthorized sections omitted.

---

## UAT-10 Audit

**Precondition:** `audit_logs.view`.

1. Perform a critical action (role assign, document upload, decision approve, etc.).
2. Open Audit list/detail.
3. Confirm one meaningful row; no passwords/tokens/storage paths.

**Expected:** Append-only; tenant-scoped; secrets sanitized.

---

## UAT-11 Cross-tenant isolation (API)

**Precondition:** Two tenants; API client (or Postman) with tokens/sessions per tenant.

1. As Tenant A, request Tenant B resource IDs for users, contracts, documents, tasks, audit, etc.
2. **Expected:** `404` (not `403`) for cross-tenant lookups per project convention.

---

## UAT-12 Responsive / RTL smoke

**Widths:** desktop, tablet, mobile viewport.

Pages: Dashboard, Users/Roles, Organization, Employees, Contracts, Meetings, Decisions, Tasks, Documents, Inventory, Assets, Notifications, Audit.

- [ ] No critical horizontal overflow
- [ ] Drawers/dialogs usable
- [ ] Arabic labels consistent (no raw enum codes)
- [ ] RTL alignment acceptable

---

## UAT-13 Browsers

Record what was actually tested:

| Browser | Version | Result |
|---|---|---|
| Chrome | | |
| Firefox | | |
| Edge | | |
| Mobile Safari / Chromium | | |

Do not claim untested browsers.

---

## Sign-off

| Role | Name | Date | Result |
|---|---|---|---|
| Tester | | | |
| Product/Owner | | | |
| Tech lead | | | |

**Overall UAT:** ☐ Passed · ☐ Passed with waived P2 · ☐ Failed

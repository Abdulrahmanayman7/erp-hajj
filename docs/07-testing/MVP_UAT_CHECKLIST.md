# MVP UAT Checklist

> **Status:** Partially executed via automated + disposable MySQL RC validation (2026-08-13) — **interactive browser UAT still required**
> **Last updated:** 2026-08-13
> **RC branch:** `release/0.1.0-uat`

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

**Legend for this RC session:** `Auto` = Pest/Vitest evidence; `MySQL` = disposable MariaDB migrate/seed/scanners; `UI` = interactive browser (not run here).

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

**RC 2026-08-13:** Auto **Pass** (Pest Auth suites). UI **Blocked / not executed**.

---

## UAT-02 RBAC

**Precondition:** Two users — Owner and Employee (minimal permissions).

1. As Employee, confirm sidebar hides admin modules (Users/Roles/Audit as applicable).
2. As Employee, attempt direct URL to `/app/users` or `/app/audit` → UI deny / 403.
3. As Owner, confirm Users/Roles management works.
4. Create a custom role; assign a subset of permissions; assign to a user; verify grants.
5. Re-run RBAC seeder/provision on staging only if ops requests — confirm **custom role grants preserved**.

**Expected:** Backend denies unauthorized actions (403); UI mirrors but does not replace backend.

**RC 2026-08-13:** Auto **Pass** (Users/Roles Pest). MySQL double-seed: 83 permissions, 7 roles, Owner has `tenant_settings.update`, GM view-only. UI **not executed**.

---

## UAT-03 Organization → Employee

**Precondition:** `organization_units.*` and `employees.*` grants.

1. Create an org unit.
2. Create an employee linked to that unit.
3. Optionally link a User.
4. Activate/deactivate employee as designed.

**Expected:** Employee appears in lists; cross-tenant IDs fail with 404 if attempted via API tools.

**RC 2026-08-13:** Auto **Pass** (Organization + Employee Pest, incl. cross-tenant). UI **not executed**.

---

## UAT-04 Meeting → Decision → Task (governance)

**Precondition:** Workflow permissions for meetings, decisions, tasks.

1. Create meeting → schedule → add attendees/agenda → start → minutes → recommendation → complete.
2. Create Decision from final recommendation (or standalone) → submit → approve.
3. Create Task linked to Decision → assign → start → progress → complete.
4. Attempt to close Decision with open tasks → expect close gate denial.
5. Complete remaining tasks → close Decision.

**Expected:** Illegal transitions blocked; close gate enforced.

**RC 2026-08-13:** Auto **Pass** (Meeting/Decision/Task Pest). UI **not executed**.

---

## UAT-05 Documents

**Precondition:** `documents.*` grants; PHP upload limits configured.

1. Upload allowed file type ≤ 20 MiB.
2. Link to an entity (e.g. Contract or Task) if UI supports.
3. Download with permission; confirm unauthorized role cannot download.
4. Archive → restore.
5. Attempt host entity delete while document linked → expect guard where designed.

**Expected:** No public URL; no storage path in JSON; unauthorized download denied.

**RC 2026-08-13:** Auto **Pass** (Documents Pest). Host PHP upload limits **not verified**. UI **not executed**.

---

## UAT-06 Inventory

**Precondition:** warehouse/inventory permissions.

1. Create warehouse → category → item.
2. Receipt → Issue → Transfer → Adjustment.
3. Confirm balances never go negative.
4. If minimum stock set, confirm low-stock notification appears for eligible recipients.

**Expected:** Decimal quantities stable; no negative stock.

**RC 2026-08-13:** Auto **Pass** (Inventory Pest). UI **not executed**.

---

## UAT-07 Assets & Custodies

**Precondition:** assets permissions.

1. Create asset → assign custody → return.
2. Move through maintenance/restore or retire/lost as allowed.
3. Confirm inventory stock unchanged by asset actions.

**Expected:** Single active custody rules held; inventory untouched.

**RC 2026-08-13:** Auto **Pass** (Asset Pest). UI **not executed**.

---

## UAT-08 Notifications

**Precondition:** Domain actions that emit notifications; scheduler/worker running in UAT env.

1. Trigger a curated event (task assign, contract expiring soon scan, etc.).
2. Confirm in-app notification for recipient User.
3. Mark read / read-all.
4. Confirm disabled users are skipped.

**Expected:** No `notifications.*` permission required for own inbox; no cross-user leakage.

**RC 2026-08-13:** Auto **Pass** (Notification Pest). MySQL scanners executed (0 candidates on empty seed; second meetings scan stable). Continuous `queue:work` **not sustained**. UI **not executed**.

---

## UAT-09 Dashboard

**Precondition:** `dashboard.view` plus section view permissions as applicable.

1. Open `/app`.
2. Confirm KPIs/sections match permissions (sparse omit).
3. Confirm figures reflect current tenant SoR after prior UAT actions.

**Expected:** No other-tenant data; unauthorized sections omitted.

**RC 2026-08-13:** Auto **Pass** (Dashboard Pest). UI **not executed**.

---

## UAT-10 Audit

**Precondition:** `audit_logs.view`.

1. Perform a critical action (role assign, document upload, decision approve, etc.).
2. Open Audit list/detail.
3. Confirm one meaningful row; no passwords/tokens/storage paths.

**Expected:** Append-only; tenant-scoped; secrets sanitized.

**RC 2026-08-13:** Auto **Pass** (Audit Pest). UI **not executed**.

---

## UAT-11 Cross-tenant isolation (API)

**Precondition:** Two tenants; API client (or Postman) with tokens/sessions per tenant.

1. As Tenant A, request Tenant B resource IDs for users, contracts, documents, tasks, audit, etc.
2. **Expected:** `404` (not `403`) for cross-tenant lookups per project convention.

**RC 2026-08-13:** Auto **Pass** (explicit cross-tenant tests across modules + Settings).

---

## UAT-12 Responsive / RTL smoke

**Widths:** desktop, tablet, mobile viewport.

Pages: Dashboard, Users/Roles, Organization, Employees, Contracts, Meetings, Decisions, Tasks, Documents, Inventory, Assets, Notifications, Audit, Settings.

- [ ] No critical horizontal overflow
- [ ] Drawers/dialogs usable
- [ ] Arabic labels consistent (no raw enum codes)
- [ ] RTL alignment acceptable

**RC 2026-08-13:** **Not executed** (no interactive browser session).

---

## UAT-13 Browsers

Record what was actually tested:

| Browser | Version | Result |
|---|---|---|
| Chrome | — | Not tested |
| Firefox | — | Not tested |
| Edge | — | Not tested |
| Mobile Safari / Chromium | — | Not tested |

Do not claim untested browsers.

---

## UAT-14 System Settings (Sprint 020)

1. Owner: GET/PATCH `/api/v1/tenant-settings`; UI `/app/settings` save.
2. GM: view-only (no update).
3. Employee: denied by default.
4. Immutable: `tenant_code`, `locale` (`ar`), no `tenant_id` override; IANA timezone only.
5. Audit `TENANT_SETTINGS_UPDATED` on real change; GET/no-op not audited.
6. No new settings migration / no KV product writes.

**RC 2026-08-13:** Auto **Pass** (TenantSettingsTest 17/17). MySQL: permissions present; KV rows remain 0 after seed. UI **not executed**.

---

## Sign-off

| Role | Name | Date | Result |
|---|---|---|---|
| Tester (automated RC) | Cursor agent | 2026-08-13 | Automated/MySQL gates Pass; interactive UI Blocked |
| Product/Owner | | | |
| Tech lead | | | |

**Overall UAT:** ☐ Passed · ☐ Passed with waived P2 · ☑ Failed / incomplete (interactive browser UAT + human sign-off remaining)

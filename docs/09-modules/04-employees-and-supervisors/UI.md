# Employees and Supervisors — UI

> **Status:** Specified (Sprint 008) — **no pages yet**
> **Last updated:** 2026-08-09

## Module & navigation

| Item | Value |
|---|---|
| Frontend module | `frontend/src/modules/employees/` |
| Route | `/app/employees` |
| Sidebar label | **الموظفون** |
| Placement | Top-level / sibling group to org & system (permission `employees.view`) |
| Design system | Existing AppShell, AppSelect, AppConfirmDialog, AppToastHost, Drawer pattern |

Optional secondary route later: `/app/positions` **or** Positions managed via a tab/drawer from Employees settings — **MVP recommendation:** simple **Positions** page `/app/positions` gated by `positions.view`, sidebar under same area or nested only if nav stays clean. Prefer one sidebar entry **الموظفون** with an in-page link/button “المسميات الوظيفية” for users with `positions.view`.

## Employees page (`/app/employees`)

### Header

- Title: **الموظفون**
- Subtitle: إدارة سجلات الموظفين والربط بالهيكل التنظيمي والمشرفين
- Primary CTA (`employees.create`): **+ إضافة موظف**

### Toolbar

- Search
- Status filter (الكل / نشط / غير نشط)
- Organization unit filter (`AppSelect` / OrganizationUnitSelect)
- Position filter (if `positions.view`)
- Supervisor filter (optional; active employees flat select)

### Table columns

| Column | Notes |
|---|---|
| الرقم الوظيفي | `employee_number` |
| الاسم | `full_name` |
| الوحدة التنظيمية | unit name/code |
| المسمى الوظيفي | position name or — |
| المشرف المباشر | supervisor name or — |
| الحالة | badge نشط / غير نشط |
| إجراءات | view/edit/supervisor/user/activate/deactivate |

No salary/HR-sensitive columns.

### States

Loading / empty (“لا يوجد موظفون بعد”) + CTA / error + retry / pagination.

## Create / Edit drawer

Reuse Users/Roles/Organization **Drawer** pattern.

| Field | Create | Edit |
|---|---|---|
| الاسم الكامل | ✓ | ✓ |
| الرقم الوظيفي | read-only placeholder “يُولَّد تلقائياً” | read-only |
| الوحدة التنظيمية | ✓ required | ✓ |
| المسمى الوظيفي | optional | optional |
| الجوال | optional | optional |
| البريد | optional | optional |
| تاريخ الالتحاق | optional | optional |
| ملاحظات | optional | optional |

Supervisor and User **not** in the generic edit form — dedicated actions to avoid permission confusion.

## Supervisor UX

Action: **تعيين المشرف** → dialog/drawer.

- Tenant employees only, **active**, exclude self, exclude cycle candidates client-side when known
- Show: name, employee_number, organization unit
- Confirm on change
- Server validates cycles

## User-link UX

Action: **ربط حساب مستخدم** / **إلغاء الربط**.

- Select existing tenant Users (active for new link)
- Exclude users already linked to another employee
- Exclude platform users
- Copy must clarify: حساب الدخول ≠ سجل الموظف
- **Do not** create passwords/users here

## Positions UX (minimal)

- List + drawer create/edit
- Activate/deactivate
- Delete only when unused; else prompt deactivate

## Confirmations

Use `AppConfirmDialog` for deactivate, supervisor replace, user unlink, position delete — never `window.confirm`.

## Responsive

Desktop table; tablet horizontal scroll or card rows; mobile stacked cards — follow Users page patterns.

# Users and Authorization — UI

> **Status:** **Implemented** (Sprint 006)
> **Last updated:** 2026-08-08
> **Locale:** Arabic RTL first

Follow [DESIGN_GUIDELINES.md](../../05-ui-ux/DESIGN_GUIDELINES.md) and existing `/app` shell patterns from Authentication.

---

## Module folders (conceptual)

```
frontend/src/modules/users/
  api/  components/  pages/  queries/  mutations/  types/  validation/  routes/

frontend/src/modules/roles/
  api/  components/  pages/  queries/  mutations/  types/  validation/  routes/
  # permission matrix lives here (catalog is read-only)
```

No Pinia store for permissions — source of truth is TanStack Query `useCurrentUser()` / auth me query.

---

## Sidebar

Under authenticated shell:

```
الرئيسية                         → /app  (dashboard.view if gated)
إدارة النظام
  ├── المستخدمون                 → /app/users   (meta.permission: users.view)
  └── الأدوار والصلاحيات         → /app/roles   (meta.permission: roles.view)
```

- Hide entire “إدارة النظام” group if neither `users.view` nor `roles.view`.
- Hidden nav ≠ authorization.

---

## Route guards

| Route | `meta` |
|---|---|
| `/app/users` | `requiresAuth`, `permission: 'users.view'` |
| `/app/users` create drawer | action gated by `users.create` |
| `/app/roles` | `permission: 'roles.view'` |
| `/app/roles/:id/permissions` | `roles.view` + save needs `roles.assign_permissions` |

**Unauthorized (authenticated):** navigate to `/app/403` (Access Denied). **Do not** redirect to login.

Reuse tenant-blocked handling from Auth (pending/suspended/archived).

---

## Frontend capability helpers

Single composable (conceptual):

- `can('users.create')`
- `canAny([...])`
- `canAll([...])`
- `hasRole('tenant_owner')` — UX only; prefer permissions for buttons

Optional `<PermissionGuard :permission="..." />` wrapping actions.

Never scatter `permissions.includes` in templates.

---

## Users page — `/app/users`

Enterprise RTL list:

- Title: المستخدمون
- Optional count from pagination `meta.total`
- Search (name/email)
- Filters: status, role
- Primary CTA: إضافة مستخدم (`users.create`)
- Table columns: الاسم، البريد، الأدوار، الحالة، تاريخ الإنشاء، إجراءات
- States: loading / empty / error
- Pagination
- Actions (permission-aware, prefer row actions not mega-menus): عرض/تعديل، تفعيل/تعطيل، إدارة الأدوار

**No** employee fields.

### Create / edit UX (final)

**Drawer (side panel)** for create + edit identity fields:

- name, email
- roles multi-select (tenant roles only) via `AppListSelect`
- invite checkbox (default on) / temporary-password note when invite unavailable
- validation + `USER_EMAIL_TAKEN` inline

**Role management:** same drawer section or nested step — full replace of `role_ids` on save of roles action (`users.assign_roles`).

Disable/enable: confirm dialog with Arabic copy; block messaging for last-owner / self-disable errors from API.

---

## Roles page — `/app/roles`

- Title: الأدوار والصلاحيات
- List: name, code, system/custom badge, active/inactive, users_count, permissions_count
- CTA: دور جديد (`roles.create`)
- Actions: edit, activate/deactivate, permissions matrix, delete when allowed
- System restrictions visible (tooltips / disabled delete)

### Permission matrix — `/app/roles/:id/permissions` (or full-page panel)

Arabic RTL matrix:

- Group by module with Arabic module titles
- Checkboxes per permission; optional “select module” when subset rule allows
- Selected count
- High-risk warnings for: `users.assign_roles`, `roles.assign_permissions`, `users.disable`, `tenant_settings.update`
- Search within catalog as list grows
- Save → `PUT .../permissions`
- Loading / dirty / error / success
- No create-permission UI

Machine `name` kept in DOM `title`/sr-only; visible label = display_name / i18n.

---

## 403 page

Simple Arabic: ليس لديك صلاحية للوصول إلى هذه الصفحة + link back to `/app`.

---

## i18n

All user-facing strings via locale files; permission display names may come from API `display_name` with i18n override map when needed.

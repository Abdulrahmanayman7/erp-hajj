# Navigation — Rafeeq ERP

> Complements [RESPONSIVE_UX.md](RESPONSIVE_UX.md).  
> Implementation source: `frontend/src/shared/composables/useAppNavigation.ts`

## Single source of truth

`useAppNavigation()` builds permission-filtered groups:

| Group key | Label (ar) | Typical items |
|---|---|---|
| `home` | الرئيسية | Dashboard |
| `system` | إدارة النظام | Users, Roles, Audit, Settings |
| `organization` | الهيكل التنظيمي / التشغيل | Org, Employees, Contracts, Meetings, Decisions, Tasks, Documents, Warehouses, Inventory, Assets, My custodies |

Every consumer (sidebar, rail, More sheet, quick actions) **must** derive from this composable or shared helpers — never hardcode parallel menus.

## Desktop (≥1280px)

- Full sidebar on the **right** (RTL).
- Collapse to icon rail width via existing `useSidebarCollapse` (localStorage).
- Topbar: breadcrumbs + chrome tools (back / soft refresh / fullscreen) allowed.

## Tablet (768–1279px)

- **No permanent full sidebar.**
- Compact **Navigation Rail** (~76px) with primary icons (dashboard, tasks, meetings, employees, documents — if permitted) + More.
- More opens **overlay drawer from the right** over content (does not push layout).
- Topbar: compact title; omit desktop browser-like chrome overload.

## Mobile (&lt;768px)

Bottom navigation (fixed, safe-area aware):

1. الرئيسية → `/app`
2. المهام → `/app/tasks` (if `tasks.view`)
3. **+** → Quick Action sheet (permission-gated creates)
4. الإشعارات → `/app/notifications`
5. المزيد → sectioned More sheet

Mobile header: brand/title + notifications shortcut + avatar/menu — **no** back/refresh/fullscreen as primary chrome.

## Permissions

- Nav items appear only when `can(permission)`.
- Quick actions appear only when create/upload permissions exist.
- Route `meta.permission` remains authoritative for deep links.

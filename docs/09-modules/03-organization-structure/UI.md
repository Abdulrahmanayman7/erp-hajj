# Organizational Structure — UI

> **Status:** Specified (Sprint 007) — **no pages yet**
> **Last updated:** 2026-08-09

## Module folder & route

| Item | Value |
|---|---|
| Frontend module | `frontend/src/modules/organization/` |
| Route | `/app/organization` |
| Sidebar label | **الهيكل التنظيمي** |
| Sidebar placement | Top-level app nav item (sibling to إدارة النظام), gated by `organization_units.view` |
| Do not add | Future business nav (transport, finance, pilgrims, …) |

## Page composition (chosen approach)

**C. Tree + details panel** (not pure org-chart graphics; not flat-only table).

Rationale: ERP-friendly for large trees; searchable; one selected node shows details/actions without a separate route per unit. Avoid heavy visual org-chart boxes.

### Header

- Title: **الهيكل التنظيمي**
- Subtitle: إدارة الإدارات والوحدات التنظيمية وتوزيع المسؤوليات
- Primary action (if `organization_units.create`): **+ إضافة وحدة تنظيمية**

### Main layout (RTL)

| Region | Content |
|---|---|
| Toolbar | Search (name/code); status filter (الكل / نشط / غير نشط); expand/collapse all |
| Tree pane | Hierarchical tree: name, type badge, status chip, child count; optional **unit manager** name truncated |
| Details pane | Selected unit: fields, unit manager (مسؤول الوحدة), child count, timestamps; actions |

### States

- **Loading:** skeleton / spinner on tree pane
- **Empty:** illustration/message + CTA to create first unit (if permitted)
- **Error:** retry; toast via `AppToastHost`
- **No selection:** details pane placeholder (“اختر وحدة من الشجرة”)

### Actions (permission-gated)

| Action | Permission | Confirm? |
|---|---|---|
| Create | `organization_units.create` | No (drawer) |
| Edit | `organization_units.update` | No (drawer) |
| Move | `organization_units.update` | **Yes** — explain parent change / subtree impact / depth limits |
| Activate | `organization_units.update` | Soft confirm optional |
| Deactivate | `organization_units.update` | **Yes** — preferred retirement; impact: cannot be parent; children remain |
| Delete | `organization_units.delete` | **Yes** — only when delete policy allows (no children / no refs); explain permanence; otherwise steer to deactivate |
| Change unit manager | `organization_units.update` | **Yes** when replacing existing unit manager |

Use **`AppConfirmDialog`** — never `window.alert` / `confirm`.

## Create / Edit drawer

Reuse Users/Roles **Drawer** pattern.

| Field | Create | Edit |
|---|---|---|
| الاسم | ✓ | ✓ |
| الرمز (code) | ✓ | read-only |
| النوع | ✓ enum | ✓ |
| الوحدة الأب | ✓ `AppSelect` (flat active units) | Edit via Move flow preferred for parent |
| مسؤول الوحدة (unit manager) | ✓ optional user select (active users) — **not** “المدير المباشر للموظف” | ✓ |
| الترتيب | ✓ optional | ✓ |

Status on create defaults active; lifecycle via actions not free-form status dropdown (match Roles).

Label copy must not imply employee HR reporting; unit manager = organizational unit accountability.

## Move UX

Dedicated confirm or small dialog: select new parent (or root); show warning about circular prevention and depth limit (client disable invalid parents; server authoritative).

## Shared components (reuse — do not duplicate)

- `AppSelect`, `AppConfirmDialog`, `AppToastHost`, `AppTooltip`
- Permission helpers / guards from existing auth/RBAC frontend
- Shared Drawer pattern from Users/Roles

## Shared selector for other modules (later)

Export a reusable **OrganizationUnitSelect** (flat active units) from this module for Employees/Tasks forms later — implement when first consumer needs it; may ship in Sprint 007 as internal helper used by parent picker.

## Explicitly not in Sprint 007 UI

- Positions CRUD page
- Drag-and-drop tree reordering
- Graphical org chart
- Per-unit employee roster
- User assignment UI
- Employee direct-manager assignment

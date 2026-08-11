# Assets and Custodies — UI

> **Status:** Implemented (Sprint 015)
> **Last updated:** 2026-08-11
> Shell: existing AppSidebar / RTL layout — **do not redesign**.

## Module placement

| Item | Value |
|---|---|
| Frontend | `frontend/src/modules/assets/` (custody UX nested; optional `custodies` pages inside same module) |
| Routes | `/app/assets`, `/app/assets/:id`, `/app/my-custodies` |
| Sidebar | **الأصول** (`assets.view`) after المخزون; **عُهَدي** optional secondary entry to `/app/my-custodies` (same permission) |
| Theme | Light, Arabic RTL, deep green + gold |

## Screens

### Assets list — الأصول

- Header + CTA إضافة أصل (`assets.create`) → Drawer.
- Filters: search, status, category, warehouse, organization unit, assigned employee.
- Desktop table / mobile cards: number, name, category, serial, status badge, warehouse, current employee (from custody), org unit, actions.
- Row → details.

### Create / edit drawer

Sections: بيانات الأصل · التصنيف · المستودع/الجهة · قيمة الشراء (informational) · ملاحظات.
`asset_number` read-only after create.
**No** status dropdown — lifecycle via details actions.

### Asset details

Sections:

1. **نظرة عامة** — number, name, category, serial/barcode, condition, purchase value, acquisition date, notes
2. **الحالة والموقع** — status badge, home warehouse, organization unit
3. **العهدة الحالية** — active custody card (employee, dates, notes) or empty
4. **سجل العهد** — append-only custody table
5. **سجل الحالة** — transitions timeline
6. **المستندات** — `EntityDocumentsSection` (`asset`)

Actions (permission-gated + status-gated):

| CTA | Action |
|---|---|
| تسليم عهدة | Assign dialog |
| استلام العهدة | Return dialog |
| إرسال للصيانة | Confirm |
| إعادة للإتاحة | Restore |
| استبعاد | Retire (reason) |
| فقد | Declare lost (reason) |

### Assign dialog

Employee (active), expected return (optional), condition at assignment, notes.
Only when status `available`.

### Return dialog

`next_status` select (available / maintenance / damaged / retired), condition at return, notes.
Only when active custody exists.

### My custodies — عُهَدي

List actor’s custodies (active first). Link to asset details (self-view).
No assign/return buttons unless user also holds those permissions.

### Categories manager

Drawer/manager behind `assets.update` (like contract/document categories).

## Warehouse integration

Warehouse details may add a compact **أصول مخزّنة هنا** list filtered by `warehouse_id` (home warehouse) — optional Sprint 015 stretch; if included, do **not** mix with inventory quantity KPIs.

## Employee integration

Employee details may add **العُهد الحالية** section (active custodies for that employee) when `assets.view` — lightweight; do not redesign Employees module.

## Documents UX

Reuse `EntityDocumentsSection`. Custody details may also host documents (`custody` morph) when viewing a custody record.

## Responsive

Desktop: tables + drawers + detail sections.
Mobile: cards, stacked metadata, full-width dialogs, large lifecycle buttons.
No horizontal squeeze of desktop tables.

## Explicit non-goals

- Editable status select on forms
- Editable completed custody rows
- Inventory quantity widgets on asset pages
- Maintenance work-order UI

# Dashboard — UI

> **Status:** Specification complete — implementation pending (Sprint 017)
> **Last updated:** 2026-08-12
> Shell: existing AppLayout / AppTopbar / AppSidebar / RTL — **do not redesign** the shell.

## Route

| Item | Value |
|---|---|
| Canonical path | `/app` |
| Name | `app-home` (reuse) or `dashboard` — prefer keeping `/app` as home |
| Alias | `/app/dashboard` → redirect to `/app` (recommended) |
| Meta | `requiresAuth: true`, `permission: 'dashboard.view'` |
| Replaces | Temporary `AppHomePage` welcome / users-roles quick actions as primary landing |

Sidebar label: **لوحة التحكم** (existing `nav.dashboard` if present) pointing to `/app`.

---

## Page structure (RTL, light theme)

### 1. Header

```text
لوحة التحكم
نظرة سريعة على حالة العمل
```

Optional meta row: tenant display name (from `/me`), today’s date in tenant timezone, **تحديث** manual refresh button.

Unread notifications chip (optional): shows `notifications.unread_count` → `/app/notifications`. Bell in topbar remains primary Notifications UX.

### 2. KPI row

- Render **priority KPI cards** in fixed order when keys exist:
  1. `tasks_overdue`
  2. `decisions_pending_approval`
  3. `contracts_expiring_soon`
  4. `inventory_attention`
  5. `custodies_overdue`
  6. `meetings_today`
- Use shared **KPI Card** component ([DESIGN_GUIDELINES.md](../../05-ui-ux/DESIGN_GUIDELINES.md)).
- Click → `href` from API.
- Severity styling: critical / warning / info (design tokens).
- Desktop: responsive grid (2–3 columns); tablet: 2; mobile: 1 — **no horizontal scroll**.

Secondary KPI values may appear in Work/Resources panels, not necessarily all in the top row.

### 3. Attention — يحتاج انتباهك

- List/cards from `attention[]`.
- Severity icon/color; title; subtitle; count badge.
- Click → `href`.
- Empty: `لا توجد تنبيهات حرجة`.

### 4. Today / Soon — اليوم والقريب

Tabs or stacked subsections:

- اجتماعات اليوم
- مهام مستحقة اليوم
- عقود تنتهي قريبًا
- اجتماعات خلال 7 أيام

Empty per subsection: e.g. `لا توجد اجتماعات اليوم`.

### 5. Work — العمل

- Compact Task / Decision highlights using available KPI + lists.
- If `work.my_tasks` present: card **مهامي** (open / overdue) → مهامي filter.

### 6. Resources — الموارد

- Inventory low/out counts (if present).
- Assets available / in use / maintenance.
- Custodies overdue / due soon.
- If `resources.my_custodies` present: **عُهَدي**.

---

## Charts

**None in Sprint 017.** Do not install chart packages. Do not render empty pie charts.

---

## States

| State | Behavior |
|---|---|
| Loading | Skeleton KPI row + section placeholders |
| Error | Shared error state + retry (whole page; API fails closed) |
| Empty tenant | KPIs at 0 where permitted + empty Attention/Today copy — still valid |
| No module permissions | Message: لا توجد وحدات تشغيلية ضمن صلاحياتك |

---

## Responsive

| Breakpoint | Layout |
|---|---|
| Desktop | Multi-column KPIs; Attention + Today side-by-side when space |
| Tablet | 2-column KPIs; stacked sections |
| Mobile | Single column; large tap targets |

RTL preserved (`ms`/`me` utilities).

---

## Deep-links

- Prefer API-provided `href`.
- Frontend must not invent external URLs.
- If module filter query differs slightly at implementation time, align both Dashboard API builder and module list parsers in the same PR.

---

## Arabic labels (canonical)

| Key | Arabic |
|---|---|
| Page title | لوحة التحكم |
| Subtitle | نظرة سريعة على حالة العمل |
| Attention | يحتاج انتباهك |
| Today | اليوم والقريب |
| Work | العمل |
| Resources | الموارد |
| Refresh | تحديث |
| My tasks | مهامي |
| My custodies | عُهَدي |
| Overdue tasks | المهام المتأخرة |
| Pending decisions | قرارات بانتظار الموافقة |
| Expiring contracts | عقود تنتهي قريبًا |
| Low stock | مخزون منخفض / تنبيهات المخزون |
| Overdue custodies | عُهد متأخرة |
| Meetings today | اجتماعات اليوم |

Avoid English labels in visible UI.

---

## Accessibility

- KPI values announced as text (not color-only).
- Attention list keyboard navigable.
- Refresh button labeled.

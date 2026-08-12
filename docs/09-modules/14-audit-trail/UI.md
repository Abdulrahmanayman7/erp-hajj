# Audit Trail — UI

> **Status:** Specification complete — implementation pending (Sprint 018)
> **Last updated:** 2026-08-12

## Routes

| Route | Page | Permission |
|---|---|---|
| `/app/audit` | Audit log list | `audit_logs.view` |
| `/app/audit/:id` | Audit log details | `audit_logs.view` |

Module folder: `frontend/src/modules/audit/` (`api/`, `queries/`, `types/`, `pages/`, `components/`).

## Sidebar

- Arabic label: **سجل التدقيق**
- Group: **نظام / إدارة** (`nav.systemAdmin`) alongside Users/Roles
- Permission-gated: show only if `can('audit_logs.view')`

## List page

### Header

- Title: سجل التدقيق
- Subtitle: سجل العمليات الحساسة والتغييرات المهمة

### Filters

- Search (event / entity number / label / actor label)
- Event type (select of known codes or free exact)
- Actor user (user picker if `users.view`, else id)
- Entity type + entity id
- Date from / date to
- Correlation ID (advanced)
- Clear filters

### Desktop table columns

| Column | Content |
|---|---|
| الوقت | `created_at` in tenant timezone |
| الحدث | Arabic label from `event_type` map (not raw code as primary) |
| المنفذ | Actor label or **النظام** |
| الكيان | `entity_number` + `entity_label` (or —) |
| الوصف | Short reason or derived one-liner |

Row click → details. No edit/delete affordances.

### Mobile

Stacked cards; filters in drawer/sheet. No forced horizontal scroll of a huge table.

### Empty

`لا توجد أحداث مطابقة`

### Loading / error

Skeleton table/cards; Arabic error + retry. Do not show stale rows as truth after hard failure.

## Details page

Sections:

1. **الملخص** — Arabic event label, timestamp, correlation id
2. **المنفذ** — type + label (+ link to user if exists and `users.view`)
3. **الكيان** — snapshot; deep-link button only if API `deep_link.available`
4. **التغييرات** — before → after for changed fields only (readable Arabic field labels where mapped)
5. **بيانات إضافية** — structured metadata key/value (not a raw JSON dump as the only view; optional expandable technical JSON allowed)
6. **السياق** — IP, user agent, source, context_type

Deleted entity: snapshot only; no broken deep link.

## Before/after UX

```text
الحالة
مسودة → معتمد
```

Show only keys present in before/after. Masked/redacted values never shown as real secrets.

## Event labels

Canonical DB/API code stays English machine form. UI map example:

| Code | Arabic |
|---|---|
| `TASK_ASSIGNED` | تم إسناد مهمة |
| `CONTRACT_APPROVED` | تمت الموافقة على عقد |
| `DOCUMENT_DOWNLOADED` | تم تنزيل مستند |
| `LOGIN_SUCCESS` | تسجيل دخول ناجح |
| `STOCK_TRANSFERRED` | تحويل مخزون |

Unknown codes: show code as fallback + generic “حدث نظام”.

## System actor UX

`actor_type = system` → display **النظام** (never blank “null”).

## Audit History Panel (shared)

Embeddable list (same API filters) on module detail pages when viewer has `audit_logs.view`. Chronological, read-only. Not required on every module in first PR — ship panel component + wire at least one host (e.g. Contract or Task) as proof; remaining hosts can follow without API changes.

## Export button

**Hidden / absent** until `audit_logs.export` is implemented.

## Design system

Reuse existing Data Table, filters, Empty/Loading/Error, RTL shell. No charts. No new icon library.

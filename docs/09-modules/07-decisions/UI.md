# Decisions — UI

> **Status:** Specified (Sprint 011) — **not implemented**  
> **Last updated:** 2026-08-10  
> Shell: existing AppSidebar / RTL layout — **do not redesign**.

## Module placement

| Item | Value |
|---|---|
| Frontend path | `frontend/src/modules/decisions/` |
| List route | `/app/decisions` |
| Details route | `/app/decisions/:id` |
| Sidebar label | **القرارات** |
| Visibility | `decisions.view` |

## List page — القرارات

**Header:** القرارات  
**Primary CTA:** إضافة قرار (`decisions.create`) → opens create Drawer (standalone).

### Filters

- بحث (number / title)
- الحالة
- الوحدة التنظيمية
- المسؤول عن المتابعة
- وجود توصية مصدر / توصية
- اجتماع مصدر (optional `meeting_id` join filter)
- نطاق تاريخ السريان / الاستحقاق

### Columns (desktop)

| Column | Content |
|---|---|
| الرقم | `DEC-######` |
| العنوان | title |
| المصدر | standalone label **أو** recommendation/meeting compact |
| المسؤول | responsible employee |
| الوحدة | organization unit |
| تاريخ السريان | effective_date |
| الاستحقاق | due_date |
| الحالة | Arabic status badge |
| إجراءات | view / edit draft / lifecycle per permissions |

**Do not** show Task progress or fake completion %.

### Empty / loading / error

Reuse shared AppEmpty / skeletons / error patterns from Meetings/Contracts.

## Create / edit Drawer

Reuse existing Drawer pattern (full width on mobile).

**Sections:**

1. **بيانات القرار** — title, body, notes  
2. **المصدر** — read-only when from recommendation (meeting + recommendation link); empty/standalone message otherwise  
3. **المسؤولية** — organization unit, issuer, responsible employee (tenant selectors)  
4. **التواريخ** — effective_date, due_date  

**Number:** server-generated — show placeholder “يُنشأ تلقائياً” on create; display after save.  
**Status:** never a free dropdown — badges + lifecycle actions only.

Labels (do not conflate):

| Arabic | Meaning |
|---|---|
| مصدر القرار | Recommendation / standalone |
| جهة الإصدار | Issuer employee |
| المسؤول عن المتابعة | Responsible employee (oversight) |

Do **not** call المسؤول a Task assignee.

## Create from recommendation (Meetings)

Sprint 010 omitted this button; Sprint 011 **adds** it.

**Where:** Meeting details → Recommendations list/card.

**Show when:**

- Recommendation `status = final`
- Parent meeting `status = completed`
- No Decision already linked (`source_recommendation_id`)
- User has `decisions.create`

**Action label:** إنشاء قرار

**Behavior:**

1. `POST /api/v1/decisions` with `source_recommendation_id`
2. On success → navigate to `/app/decisions/:id` (draft) or open Decision details
3. Do **not** auto-approve
4. On `DECISION_ALREADY_CREATED_FROM_RECOMMENDATION` → show stable Arabic error; offer link to existing Decision if list filter can resolve it

Do not embed Decision editing inside the Meeting screen beyond this CTA.

## Decision details — `/app/decisions/:id`

**Sections:**

1. **نظرة عامة** — number, title, body, notes, status, dates, org, issuer, responsible  
2. **المصدر** — compact recommendation + derived meeting with link to `/app/meetings/:id` when present  
3. **سجل الحالة** — append-only timeline  

**Omit** Tasks section entirely until Sprint 012.

## Lifecycle UX

Explicit actions by status + permission (AppConfirmDialog; comment field when required):

| Status | Actions (Arabic) |
|---|---|
| draft | إرسال للاعتماد · إلغاء · تعديل · حذف (if untouched) |
| pending_approval | اعتماد · إرجاع للمسودة (سبب إلزامي) · إلغاء |
| approved | إغلاق |
| closed / cancelled | read-only |

No raw status dropdown.

## Responsive

| Breakpoint | Behavior |
|---|---|
| Desktop | Table + details page |
| Tablet | Adaptive filter wrap |
| Mobile | Card/compact rows; full-width Drawer; stacked details; lifecycle actions usable |

RTL throughout; deep green + gold accent per design system.

## Query invalidation

Invalidate decisions list/detail after mutations. When converting from Meeting, also invalidate meeting detail (recommendation row may show linked Decision later via optional badge — optional UX: show “قرار مرتبط” if API exposes existence; list filter by `source_recommendation_id` is enough for MVP without denormalizing onto Meetings).

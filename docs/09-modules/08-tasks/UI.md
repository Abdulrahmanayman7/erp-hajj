# Tasks — UI

> **Status:** Specified (Sprint 012) — **not implemented**  
> **Last updated:** 2026-08-10  
> Shell: existing AppSidebar / RTL layout — **do not redesign**.

## Module placement

| Item | Value |
|---|---|
| Frontend path | `frontend/src/modules/tasks/` |
| List route | `/app/tasks` |
| Details route | `/app/tasks/:id` |
| Sidebar label | **المهام** |
| Visibility | `tasks.view` |

Placement: after القرارات in the governance chain (Meetings → Decisions → Tasks).

## List page — المهام

**Header:** المهام  
**Primary CTA:** إضافة مهمة (`tasks.create`) → create Drawer (standalone).

### Toolbar / filters

- بحث
- الحالة
- الأولوية
- المسؤول (assignee employee)
- القرار المصدر
- الوحدة التنظيمية
- متأخرة (`overdue=true`)
- Segmented: **كل المهام** \| **مهامي** (`assigned_to_me=true`) — no separate `/my-tasks` route

### Columns (desktop)

| Column | Content |
|---|---|
| الرقم | `TSK-######` |
| العنوان | title |
| المصدر | Decision compact or مستقل |
| المسؤول | assignee employee |
| الاستحقاق | due_date |
| الأولوية | priority badge |
| التقدم | progress % (compact) |
| الحالة | status badge (+ **متأخرة** derived badge when overdue) |
| إجراءات | view / lifecycle per permissions |

No KPI charts.

## Create / edit Drawer

Reuse existing Drawer pattern.

**Sections:**

1. **بيانات المهمة** — title, description, notes, priority  
2. **المصدر** — optional Decision selector (or read-only when opened from Decision)  
3. **التعيين** — organization unit, assignee employee  
4. **التواريخ** — start_date, due_date  

Number: “يُنشأ تلقائياً”. Status: never a free dropdown.

Labels:

| Arabic | Meaning |
|---|---|
| مصدر المهمة | Decision / standalone |
| المسؤول عن التنفيذ | Assignee Employee (**not** Decision المسؤول عن المتابعة) |
| الوحدة التنظيمية | Org unit |

## Create from Decision

On Decision details when `status = approved` and user has `tasks.create`:

**إنشاء مهمة** → open Task Drawer with `decision_id` pre-set (and optional org suggestion). User enters title/assignee/dates → submit `POST /tasks`. Navigate to `/app/tasks/:id` on success.

If Decision is closed: hide CTA (cannot create).

## My Tasks UX

Segment control on list: مهامي uses `assigned_to_me=true`. Requires linked Employee; if none, show empty/help: “لا يوجد ملف موظف مرتبط بحسابك”.

## Task details — `/app/tasks/:id`

**Sections:**

1. **نظرة عامة** — number, title, description, status, priority, progress, dates, overdue badge, notes  
2. **المصدر** — Decision link to `/app/decisions/:id` when present  
3. **التعيين** — current assignee + assignment history  
4. **سجل الحالة** — transitions  
5. **النتيجة** — completion_notes / completed_at when completed  

Omit Documents upload. Prefer no placeholder.

## Assignment UX

- Show current assignee clearly.
- Action **تعيين / إعادة تعيين** (`tasks.assign`) → employee selector + optional comment + confirm when reassigning.
- History compact timeline.

## Execution UX

Explicit actions (AppConfirmDialog):

| Status | Actions |
|---|---|
| draft | تعيين · إلغاء · تعديل · حذف (if untouched) |
| assigned | بدء المهمة · إعادة تعيين · إلغاء · تعديل · تحديث التقدم |
| in_progress | إكمال · تحديث التقدم · إعادة تعيين · إلغاء |
| completed / cancelled | read-only |

Assignees with self-service see start / progress / complete on **own** Tasks without manager verbs.

## Completion UX

Dialog: required **ملاحظات الإنجاز / النتيجة** (`completion_notes`). No attachment requirement. Explain this is Task measurement for Workflow 1.

## Overdue UX

Derived badge **متأخرة** (not a lifecycle status). Optional filter chip. Do not invent “due soon” unless cheap client-side from due_date.

## Decision details integration (Sprint 012)

Add real section **المهام المرتبطة**:

- Counts: open / completed / cancelled (one API: `GET /tasks?decision_id=` with meta or dedicated compact include later — prefer list filter + client counts for MVP, or Decision show includes `tasks_summary` if cheap)
- Prefer Decision show resource gains optional `tasks_summary: { open, completed, cancelled, total }` loaded in one query — document for implementation.
- Table/list of linked Tasks (number, title, assignee, status, due).
- CTA إنشاء مهمة when approved.
- Close button: if open Tasks exist, disable/block with Arabic explanation from `DECISION_CLOSE_NOT_ALLOWED`.

## Responsive

| Breakpoint | Behavior |
|---|---|
| Desktop | Table + details |
| Tablet | Adaptive filters |
| Mobile | Cards; full-width Drawer; stacked actions |

RTL throughout.

## Query invalidation

Invalidate tasks list/detail after mutations. When creating from Decision / assigning / completing: also invalidate Decision detail (summary/close eligibility). Avoid full-app invalidation.

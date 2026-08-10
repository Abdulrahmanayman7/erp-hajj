# Documents — UI

> **Status:** Implemented (Sprint 013)  
> **Last updated:** 2026-08-11  
> Shell: existing AppSidebar / RTL layout — **do not redesign**.

## Module placement

| Item | Value |
|---|---|
| Frontend path | `frontend/src/modules/documents/` |
| List route | `/app/documents` |
| Details route | `/app/documents/:id` |
| Sidebar label | **الوثائق** |
| Visibility | `documents.view` |

Placement: after المهام in the governance/ops chain (or under organization group — **lock: after Tasks** in the same nav group as Meetings/Decisions/Tasks).

## List — الوثائق

**Header:** الوثائق  
**Primary CTA:** رفع مستند (`documents.upload`) → upload Drawer/Dialog.

### Toolbar

- بحث
- الحالة (نشط / مؤرشف / الكل) — default نشط
- التصنيف
- الرافع (uploader)
- نوع السجل المرتبط + رقم السجل
- تاريخ الرفع (from/to)

### Columns (desktop)

| Column | Content |
|---|---|
| الرقم | `DOC-######` |
| العنوان | title |
| الملف | original_filename + size |
| التصنيف | category |
| المرتبط | compact link label or مستقل |
| الرافع | uploader |
| التاريخ | created_at |
| الحالة | active/archived badge |
| إجراءات | view / download / archive / delete per permissions |

Mobile: cards with title, number, status, primary download.

No KPI charts. No inline file previewer required (deferred).

## Upload UX

Drawer/dialog sections:

1. **الملف** — file picker (drag-drop optional if consistent with stack; native input minimum)
2. **البيانات** — title, description, category
3. **الربط** — optional entity type + entity selector (searchable)

Show client-side size/type hints matching server rules. On success: toast + navigate to details or stay on list.  
Duplicate checksum: optional non-blocking warning (“يوجد ملف مطابق مسبقاً”).

Progress: browser upload progress if practical; not a hard requirement.

## Details — `/app/documents/:id`

Sections:

1. **نظرة عامة** — number, title, description, status, category, mime/size, checksum (truncated), dates  
2. **الملف** — original filename; Download CTA (`documents.download`)  
3. **الربط** — link to host entity route when present; unlink/edit with `documents.update`  
4. **الأرشفة** — archive/restore actions  

Never show storage path. No version timeline.

## Categories manager

Accessible from list toolbar or settings-style panel for `documents.manage_categories` (mirror Contract categories UX: list + create/edit + deactivate).

## Entity integration — المستندات

On details pages for **Contracts, Meetings, Decisions, Tasks, Employees, Organization Units**:

- Section title: **المستندات**
- Requires `documents.view` to list; upload CTA requires `documents.upload`
- Table/cards of Documents filtered by `linkable_type` + `linkable_id`
- Upload opens Documents upload UX with link **preselected and locked**
- Download/archive actions permission-aware
- Replace placeholder “المرفقات — قريباً” text from prior sprints with this real section at implementation

Do **not** implement nested Documents modules inside each host package beyond a thin shared widget/composable under `documents/` or `shared/`.

## Responsive

| Breakpoint | Behavior |
|---|---|
| Desktop | Table + details |
| Mobile | Cards; full-width drawer; stacked actions |

RTL throughout. Light theme only.

## Query invalidation

Invalidate documents list/detail after upload/update/archive/restore/delete.  
When linked: also invalidate host entity detail query keys as needed. Avoid full-app invalidation.

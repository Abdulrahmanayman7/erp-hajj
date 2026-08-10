# Meetings — UI

> **Status:** Implemented (Sprint 010) — pages live
> **Last updated:** 2026-08-10

Arabic RTL first. Reuse AppSidebar, Drawer, AppSelect, AppConfirmDialog, AppToastHost, PermissionGuard, TanStack Query. Do not redesign the shell.

## Routes

| Route | Page | Permission |
|---|---|---|
| `/app/meetings` | List | `meetings.view` |
| `/app/meetings/:id` | Details | `meetings.view` |

## Sidebar

- Label: **الاجتماعات**
- Gate: `meetings.view`
- Placement: top-level sibling near العقود / employees (do not invent a new nav group hierarchy)

## List page — الاجتماعات

- Subtitle: إدارة الاجتماعات ومحاضرها وتوصياتها
- Primary CTA: **إضافة اجتماع** (`meetings.create`)
- Toolbar: search, status AppSelect, date range, organization unit, upcoming toggle (**قادم**)
- Empty: لا توجد اجتماعات بعد

### Columns

| Column | Arabic |
|---|---|
| meeting_number | رقم الاجتماع |
| title | العنوان |
| scheduled_at | الموعد |
| chairperson | رئيس الجلسة |
| organization_unit | الوحدة التنظيمية |
| status | الحالة |
| attendee_count | الحضور |
| actions | إجراءات |

Status badges (vue-i18n): مسودة · مجدول · جارية · مكتملة · ملغاة.

Derived chips (not statuses): **قادم**, **اليوم** when applicable.

## Create / edit drawer

Reuse Drawer pattern. Sections:

1. **بيانات الاجتماع** — title, description
2. **الموعد والمكان** — scheduled_at (optional until schedule), location_type, location_text, meeting_link
3. **المسؤولون** — organization unit, chairperson, secretary (employee selectors)
4. **ملاحظات** — notes

- Meeting number: read-only after create / placeholder يُولَّد تلقائياً
- Status: **not** a free dropdown
- Edit blocked when `completed` \| `cancelled`

## Details page — `/app/meetings/:id`

Sections (stack on mobile; tabs optional if design system already uses tabs — prefer clear sections):

| Section | Arabic | Content |
|---|---|---|
| Overview | نظرة عامة | Fields, lifecycle actions, timeline |
| Attendees | الحضور | Employee list + attendance |
| Agenda | جدول الأعمال | Ordered items |
| Minutes | المحضر | Textarea editor |
| Recommendations | التوصيات | List CRUD |
| Attachments | المرفقات | Placeholder only: المرفقات ستتوفر مع وحدة المستندات |

**Do not** show fake Tasks tabs. Sprint 011 implemented **إنشاء قرار** for eligible final recommendations (see [07-decisions/UI.md](../07-decisions/UI.md)) — not a fake control: real `decisions.create` conversion.

## Lifecycle actions UX

Show only valid actions for status + permission:

| Action | Arabic | Confirm |
|---|---|---|
| Schedule | جدولة الاجتماع | confirm + datetime if needed |
| Reschedule | إعادة الجدولة | confirm + new datetime |
| Start | بدء الاجتماع | confirm |
| Complete | إنهاء الاجتماع | confirm; block with clear error if minutes empty |
| Cancel | إلغاء الاجتماع | confirm + **required** reason |

Use `AppConfirmDialog` — never `window.confirm`.

## Attendees UX

- Add via employee AppSelect / search
- Show: name, employee_number, optional org/position if already on employee summary APIs
- Attendance AppSelect: مدعو / حضر / غائب / معتذر
- No RBAC dump

## Minutes UX

- Large textarea (no new rich-text package)
- Save via `manage_minutes`
- Read-only after complete/cancel
- Optional “last updated” if API exposes `updated_at` on meeting

## Recommendations UX

- Card/list rows: title, description, owner, status (مسودة / نهائية)
- Create/edit/delete while meeting open
- **Sprint 011 implemented:** for `final` recommendations on a **completed** meeting with no linked Decision yet, show **إنشاء قرار** when the user has `decisions.create` (POST `/api/v1/decisions` with `source_recommendation_id`; navigate to Decision draft). See [07-decisions/UI.md](../07-decisions/UI.md).

## Timeline UX

Append-only transitions: Arabic action/status, actor (or النظام if null), timestamp, comment.

## Responsive

- Desktop: table + details route
- Tablet: adaptive filters
- Mobile: card list; full-width drawer; stacked details sections
- RTL throughout

## Query invalidation

Invalidate meeting list + detail (and nested queries) after create/update/lifecycle/attendee/minutes/agenda/recommendation mutations. Avoid refetch storms.

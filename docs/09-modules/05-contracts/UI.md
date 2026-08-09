# Contracts — UI

> **Status:** Specified (Sprint 009) — **no pages yet**
> **Last updated:** 2026-08-09

## Module & navigation

| Item | Value |
|---|---|
| Frontend module | `frontend/src/modules/contracts/` |
| Route | `/app/contracts` |
| Optional details | `/app/contracts/:id` (justified — timeline + transitions volume) |
| Sidebar label | **العقود** |
| Placement | Top-level / sibling to organization & employees group (`contracts.view`) |
| Design system | Existing AppShell, AppSelect, AppConfirmDialog, AppToastHost, Drawer |

Categories: in-page **تصنيفات العقود** drawer/button (like Employees → Positions), not a separate sidebar entry.

---

## Contracts list (`/app/contracts`)

### Header

- Title: **العقود**
- Subtitle: إدارة العقود ودورة اعتمادها وتنفيذها
- Primary CTA (`contracts.create`): **+ إضافة عقد**

### Toolbar

- Search (number / title / counterparty)
- Status filter
- Category filter
- Organization unit filter
- Employee filter (optional)
- Expiring soon toggle (`ينتهي قريباً`)
- Date range filters (start/end) if space allows — collapse on tablet

### Table columns

| Column | Field |
|---|---|
| رقم العقد | `contract_number` |
| العنوان | `title` |
| التصنيف | category name |
| الطرف الآخر | `counterparty_name` |
| البداية | `start_date` |
| النهاية | `end_date` or — |
| الحالة | status badge (+ expiring chip when applicable) |
| القيمة | `value` + `currency` or — |
| إجراءات | view / edit / transitions |

No payment or attachment columns in Sprint 009.

### States

Loading / empty (“لا توجد عقود بعد”) + CTA / error + retry / pagination.

---

## Create / Edit drawer

Reuse Users/Employees **Drawer** pattern. **Edit only in `draft`.**

Sections:

1. **بيانات العقد** — title, category, dates
2. **الطرف المرتبط** — counterparty_name, kind, optional employee
3. **التنظيم** — optional organization unit
4. **القيمة** — value, currency (default SAR, may be read-only SAR in MVP UI)
5. **ملاحظات**

`contract_number`: placeholder “يُولَّد تلقائياً” on create; read-only on edit.

Do **not** include status dropdown, attachments upload, or supplier picker.

---

## Details page (`/app/contracts/:id`)

Dedicated page (not only drawer) because of transition timeline:

- Header: number + title + status badge + expiring chip
- Summary cards: party, category, dates, value, org unit, employee
- **Timeline** from `contract_status_transitions` only — show previous→new status, actor (or “النظام” for scheduler), comment/reason, timestamp
- Action bar: permission- and status-aware transition buttons
- Attachments panel: disabled placeholder (“المرفقات ستتوفر مع وحدة المستندات”)

---

## Status / lifecycle UX

| Action | Arabic CTA | Confirm |
|---|---|---|
| Submit review | إرسال للمراجعة | light confirm optional |
| Return draft | إعادة للمسودة | confirm + **required comment** |
| Approve | اعتماد | confirm |
| Sign | تسجيل التوقيع | confirm — copy must state: **توثيق يدوي أن العقد وُقّع خارج النظام** (ليس توقيعاً إلكترونياً) |
| Execute | بدء التنفيذ | confirm |
| Close | إغلاق العقد | confirm |
| Cancel | إلغاء العقد | confirm + **required comment** |
| Renew | تجديد | confirm — creates **one** new draft; source becomes مجدّد; hide/disable if already renewed; map `CONTRACT_ALREADY_RENEWED` |

Use `AppConfirmDialog` — never `window.confirm`.

After renew success: navigate to successor draft (or show both ids) so operator sets **new dates** before submit.

Badges: consistent design-system colors per status; expiring: “ينتهي خلال N يوماً” using config N.

---

## Categories UX

Lightweight drawer from list toolbar (`contracts.update` to manage, `contracts.view` to see):

- list / create / edit name / activate / deactivate / hard-delete **only if unused**
- Deactivate copy: existing contracts keep the category; new contracts cannot select it
- Create/edit contract category selectors: **active only**
- Inactive category on historical contract: show name + “غير نشط” badge; do not force change

---

## Responsive

Desktop: full table + details route. Tablet: horizontal scroll / condensed columns. Mobile: card rows; drawer full width. Follow Employees/Users patterns.

## Query strategy

TanStack Query; invalidate contracts list/detail after create/update/transition/delete/category mutation. Avoid refetch storms.

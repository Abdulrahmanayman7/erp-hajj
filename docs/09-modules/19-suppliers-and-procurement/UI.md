# Suppliers & Procurement (S04) — UI

> **Status:** **ENGINEERING PROPOSAL** — **NOT IMPLEMENTED**  
> **Last updated:** 2026-10-31  
> Arabic-first, RTL, desktop-first, deep green + gold per design system. Sidebar on the right.  
> Frontend layout target: `frontend/src/modules/suppliers/` and/or `purchase-requests/` mirroring `contracts` / `inventory` (`api|pages|mutations|queries|types|validation`).  
> Owner **D1–D4**: geo picker limited seed; financial review mandatory UX; cancel-approved gated; sensitive actions hidden unless explicitly permitted.

---

## 1. Navigation

| Sidebar label (AR) | Route | Permission gate |
|---|---|---|
| الموردون | `/app/suppliers` | `suppliers.view` |
| تقييم الموردين | `/app/supplier-evaluations` | `supplier_evaluations.view` |
| طلبات الشراء | `/app/purchase-requests` | `purchase_requests.view` |
| إعدادات اعتماد المشتريات | `/app/purchase-approval-settings` | `purchase_requests.manage_approval_config` |
| تقارير اعتماد المشتريات | `/app/purchase-control-reports` | `purchase_requests.view_control_reports` |

Group under a procurement section without implying S05 PO screens.

---

## 2. Suppliers

### List — الموردون

- Filters: status, category, type, country, search name/number/legal id.  
- Columns: number, name, type, status badge, categories (chips), eligibility hint.  
- CTA: مورد جديد (`suppliers.create`).

### Details / edit

- Tabs: أساسي · فئات وتغطية · جهات اتصال · مستندات · بنوك · تقييمات · سجل الحالة.  
- Lifecycle actions via confirmation dialogs + reason when required.  
- Banking tab: masked by default; «إظهار» requires `view_banking` + optional re-auth **DEFERRED**.  
- Document expiry badges (ساري / منتهٍ / ناقص).  
- Similarity warning banner on save (non-blocking).

---

## 3. Evaluations

- List with supplier, kind, score, status, season.  
- Form: criteria weight table, score inputs, evidence note, optional season.  
- Actions: حفظ مسودة · إرسال للاعتماد · اعتماد · رفض (SoD: hide approve if current user is preparer).  
- Low score: show alert copy; no auto status change on supplier.

---

## 4. Purchase requests

### List — طلبات الشراء

- Filters: status, kind, season, priority, department, requester.  
- Badges for unpriced lines / overdue approval.  
- Duplicate hint toast when opening create with similar recent PRs.

### Create / edit wizard-ish single page

1. Header: kind (موسمي/إداري), season (required if موسمي), purpose, priority + justification, beneficiary unit.  
2. Lines table: add/remove; catalog picker **or** free text; qty/unit/need date; estimate or unpriced reason.  
3. Totals panel: إجمالي البنود المسعّرة vs تنبيه بنود غير مسعّرة.  
4. Optional supplier link (واضح أنه اختياري).  
5. Submit; acknowledge duplicate warning checkbox if warnings returned.

### Details — workflow panel

- Status timeline (versions).  
- **Financial review card (D2):** mandatory before final اعتماد; show reviewer/outcome/time/justification/version; block final approve CTA until `cleared` / `cleared_with_conditions` (including unpriced PRs).  
- **D5:** if current user is the financial reviewer, hide/disable final-approve and show that an independent approver is required; surface reassignment/escalation states from API.  
- Approval steps checklist with SLA due times (durations tenant-configured — empty state if unset).  
- Actions: اعتماد / رفض / إعادة للتعديل / إلغاء — permission + step ownership.  
- **إلغاء معتمد (D3):** separate dangerous action; requires `cancel_approved` + reason modal; disabled with explanation if `procurement_engaged_at` set (future purchasing lock).  
- Sensitive CTAs (banking, تفويض، مراجعة مالية، اعتماد، إلغاء معتمد) hidden unless `can()` — templates do not imply grants (**D4**).  
- Locked fields banner when `under_review`.  
- Coverage geo picker: D1 places only initially (Makkah, Madinah, Jeddah, Mina, Arafat, Muzdalifah + SA).

### Returned state

- Banner: أُعيد للتعديل — editing creates new version on resubmit; show prior approvals as تاريخ فقط.

---

## 5. Approval settings

- Matrix list + publish flow with effective date.  
- Authority ceilings per user.  
- SLA targets by priority×level (empty state: «لم تُضبط المدد بعد»).  
- Delegations list with expiry countdown.

---

## 6. Planning consolidation

- Simple page: create مجموعة تخطيط، attach approved PRs, notes.  
- Explicit copy: لا يُنشئ طلب عرض أسعار أو أمر شراء (S05).

---

## 7. Control reports

- Tables/charts light: متوسط زمن الاعتماد، المتأخر، النتائج، الامتثال.  
- Export **DEFERRED** unless `audit_logs.export`-like CR later.

---

## 8. UX states

Loading / empty / error / 403 hidden CTAs / 404 not found.  
RTL forms, Arabic validation messages.  
Tablet: stack tabs; keep tables at `lg+` per RESPONSIVE_UX.

---

## 9. Out of UI scope (S04)

RFQ composer, offer comparison grids, PO screens, goods receipt against PO, supplier public portal.

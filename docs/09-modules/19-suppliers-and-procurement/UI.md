# Suppliers & Procurement (S04) — UI

> **Status:** Phase I–IV UI delivered/accepted per stream evidence (Phase IV Owner functionally accepted 2026-11-01). **Phase V UI:** **IMPLEMENTED** (demand groups + control reports + CSV download; Arabic RTL) — Owner functional acceptance **PENDING**. Release acts **NOT AUTHORIZED**.  
> **Last updated:** 2026-11-01
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
| مجموعات تخطيط الطلبات | `/app/purchase-demand-groups` | `consolidate_planning` (mutate CTAs) / `purchase_requests.view` (read) |
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
- Banking tab (II-B **FUNCTIONALLY CLOSED — OWNER ACCEPTED** 2026-11-01): masked list (primary badge may sit on superseded verified version — **IIB-D11**); create/edit pending; supersede/history; verify/reject (SoD); revoke; explicit set-primary after verify; audited reveal; optional evidence.  
  - **إظهار البيانات البنكية الكاملة (**IIB-D12** / **E1** / **B7** / **M3**):** gated by explicit `suppliers.view_banking` (hidden unless `can('suppliers.view_banking')`); opens dedicated audited reveal modal; mandatory non-empty reason; trimmed **minimum 10 characters**; Arabic RTL validation for blank/short reasons; **no** reveal request until client validation passes; server validation remains authoritative ([API.md §13.5](API.md)); list/show stay masked; **no** automatic reveal; **no** persistent storage of reason or revealed identifiers; **no** plaintext bank identifiers in frontend logs. Show plaintext ephemerally only. See design pack §9. **Not implemented.**
- Document expiry badges (ساري / منتهٍ / ناقص).
- Similarity warning banner on save (non-blocking).

---

## 3. Evaluations — Phase II-C (**DESIGN DRAFT** — **IIC-D6**)

> Detail: [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md) §9. **Not implemented** (supplier tab remains locked placeholder until coding auth).

- Dedicated list `/app/supplier-evaluations` **and** supplier-details «تقييمات» tab.
- List filters: supplier, kind, status, season, category, monitoring flag.
- Form: published criteria weights (read-only after submit via snapshots); score inputs 0–100; optional evidence; monitoring **proposal** checkbox on draft (**IIC-F3**); season required in UI when kind=`post_season` (**IIC-F1**).
- Approver UI: final monitoring control on اعتماد; SoD hide اعتماد if current user is preparer.
- Criteria admin UI for draft/publish packs (permission-gated).
- Actions: حفظ مسودة · إرسال للاعتماد · اعتماد · رفض · تظلم/تصحيح.
- Monitoring: show final banner after approve; **no** supplier lifecycle CTA; draft proposal does not imply notified.

---

## 4. Purchase requests

> **Phase III UI:** [PHASE_III_PURCHASE_REQUESTS_DESIGN.md](PHASE_III_PURCHASE_REQUESTS_DESIGN.md) §6 · **III-D1–D8**.  
> **III-D8:** do **not** render اعتماد / رفض / إعادة للتعديل (or stubs) until Phase IV.  
> Approval-settings / control-reports routes remain Phase IV/V.

### List — طلبات الشراء (Phase III)

- Filters: status, kind, season, priority, org unit, requester, supplier.
- Badges for unpriced lines; **`submitted` → «بانتظار محرك الاعتماد»** (**III-D1**).
- Duplicate hint toast when opening create with similar recent PRs (**III-D6**).

### Create / edit (Phase III)

1. Header: kind (موسمي/إداري), season (required if موسمي), purpose, priority + justification, beneficiary unit (active units only — **III-D5**).
2. Lines table: add/remove; catalog picker **or** free text; qty/unit/need date; estimate or unpriced reason.
3. Totals panel: إجمالي البنود المسعّرة vs تنبيه بنود غير مسعّرة.
4. Optional supplier link (واضح أنه اختياري) with eligibility errors (**III-D4**).
5. Submit; acknowledge duplicate warning checkbox if warnings returned (**III-D6**).

### Details — Phase III panel

- Status timeline / **version history** (structured snapshots — **III-D7**).
- Banner when `submitted`: awaiting Phase IV approval engine — **not** approved (**III-D1**).
- **Financial review card (D2 / III-D2):** record/history on draft or submitted; creator may review if permitted (**III-D3**); does not unlock “approve” in III.
- Actions in III only: حفظ · إرسال · إلغاء (non-approved) · تحقق الأولوية · مراجعة مالية — permission-gated (**D4**).
- Documents links optional.
- Locked fields when `submitted`.

### Phase IV-only (deferred — do not ship in III)

- Approval steps checklist / SLA due times.
- اعتماد / رفض / إعادة للتعديل CTAs; **D5** hide final-approve for financial reviewer.
- **إلغاء معتمد (D3)** dangerous action.
- Returned-state edit banner after IV return.

---

## 5. Approval settings

- Matrix list + publish flow with effective date.
- Authority ceilings per user.
- SLA targets by priority×level (empty state: «لم تُضبط المدد بعد»).
- Delegations list with expiry countdown.

---

## 6. Planning consolidation — **Phase V** (**V-D1…V-D5**, **V-D7**)

- List/detail: create مجموعة تخطيط؛ attach **approved** PRs (header); lifecycle badges `draft/active/frozen/closed`.
- Mixed season/OU **warnings** (non-blocking).
- Explicit copy: لا يُنشئ طلب عرض أسعار أو أمر شراء (S05).
- Mutate CTAs only with `consolidate_planning`; engagement freeze disables link/unlink.

---

## 7. Control reports — **Phase V** (**V-D6=A**, **V-D8=A**)

- Four tabs: زمن الاعتماد · المتأخر · النتائج · الامتثال (RPT-V01…V04).
- **تصدير CSV** on every tab (required — **not deferred**). Same filters as on-screen.
- No XLSX. Empty/error/size-limit UX per design §9.

---

## 8. UX states

Loading / empty / error / 403 hidden CTAs / 404 not found.
RTL forms, Arabic validation messages.
Tablet: stack tabs; keep tables at `lg+` per RESPONSIVE_UX.

---

## 9. Out of UI scope (S04)

RFQ composer, offer comparison grids, PO screens, goods receipt against PO, supplier public portal.

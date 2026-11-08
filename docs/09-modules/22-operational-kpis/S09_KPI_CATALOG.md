# S09 — KPI Candidate Catalog

> **Catalog history:** Candidate inventory (feasibility sketches).  
> **S09-D01 (2026-11-08):** `OWNER_APPROVED` — v1 ships exactly **24** IDs: T01,T02,T08 · A02,A03 · P01–P08 · J01,J02,J03,J07,J09 · R01–R05,R09.  
> **S09-D02 (2026-11-08):** Owner-approved **business formulas** for those 24 — see `S09_FORMULA_MATRIX.md` / `S09_D02_OWNER_APPROVAL_2026-11-08.md`.  
> **S09-D06 (2026-11-08):** Empty/undefined semantics approved — see `S09_D06_OWNER_APPROVAL_2026-11-08.md` (precision → D14).  
> Rows outside the 24 remain catalog-only / out of S09 v1.  
> **Date:** 2026-11-08  
> Feasibility labels below are historical discovery notes; they do **not** expand D01 scope.

Legend: **FEASIBLE** · **PARTIAL** · **BLOCKED_BY_DATA_SOURCE**

---

## A. Transport (S09-KPI-Txx)

| ID | Arabic name | English name | Type | Feasibility | Notes |
|---|---|---|---|---|---|
| S09-KPI-T01 | الحركات المجدولة | Scheduled movements | count | FEASIBLE | `status ∈ {planned,in_progress}` (Owner may refine) |
| S09-KPI-T02 | الحركات المكتملة | Completed movements | count | FEASIBLE | `status = completed` |
| S09-KPI-T03 | معدل إكمال الحركات | Movement completion rate | ratio | PARTIAL | Denominator eligibility TBD (S09-D02) |
| S09-KPI-T04 | الحركات المتأخرة | Delayed movements | count | PARTIAL | No flag; compare ops vs planned; tolerance TBD |
| S09-KPI-T05 | معدل المغادرة في الوقت | On-time departure rate | ratio | PARTIAL | `departed_at`/`started_at` vs planned; threshold TBD; **not GPS** |
| S09-KPI-T06 | استغلال الأسطول | Fleet utilization | ratio | PARTIAL | Proxy via occupying runs / capacity; formula TBD |
| S09-KPI-T07 | المركبات المتاحة | Vehicle availability | count | PARTIAL | `is_active` minus occupying overlaps |
| S09-KPI-T08 | أعطال المركبات | Vehicle breakdowns | count | FEASIBLE | `transport_vehicle_breakdowns` by status |
| S09-KPI-T09 | توافق الاستحقاق النقلي | Entitlement mismatch pressure | count | PARTIAL | Live/snapshot mismatch; fulfillment % needs denominator |

---

## B. Accommodation (S09-KPI-Axx)

| ID | Arabic name | English name | Type | Feasibility | Notes |
|---|---|---|---|---|---|
| S09-KPI-A01 | الطاقة الاستيعابية المتاحة | Available capacity | capacity | PARTIAL | `effectiveCapacity` as-of window |
| S09-KPI-A02 | الطاقة المحجوزة (تخصيص) | Allocated capacity | capacity | FEASIBLE | Confirmed/completed overlapping bookings |
| S09-KPI-A03 | الإشغال الفعلي | Actual occupancy | capacity | FEASIBLE | Ops status arrived/checked_in — **≠ allocation** |
| S09-KPI-A04 | معدل التخصيص | Allocation rate | ratio | PARTIAL | Pilgrim/group denominator TBD |
| S09-KPI-A05 | معدل الإشغال الفعلي | Actual occupancy rate | ratio | PARTIAL | Occupied / capacity (or allocated) TBD |
| S09-KPI-A06 | الطاقة المتبقية | Remaining capacity | capacity | PARTIAL | capacity − allocated peak |
| S09-KPI-A07 | جاهزية المرفق | Facility readiness | status | **BLOCKED_BY_DATA_SOURCE** | No readiness SoR column |
| S09-KPI-A08 | مجموعات بلا تخصيص | Unallocated groups | count | PARTIAL | Derive via pilgrims↔assignments; “whole group” rule TBD |
| S09-KPI-A09 | الاستغلال حسب المرفق | Utilization by facility | ratio | PARTIAL | Roll up units; aggregation policy TBD |

**Unit rule:** Owner must freeze unit (beds vs positions vs pilgrims) — never mix without declaration (S09-D01).

---

## C. Procurement (S09-KPI-Pxx)

| ID | Arabic name | English name | Type | Feasibility | Notes |
|---|---|---|---|---|---|
| S09-KPI-P01 | طلبات الشراء | Purchase requests total | count | FEASIBLE | Optional season filter (nullable) |
| S09-KPI-P02 | طلبات معتمدة | Approved PRs | count | FEASIBLE | `status = approved` |
| S09-KPI-P03 | طلبات عروض صادرة | RFQs issued | count | FEASIBLE | published/awarded |
| S09-KPI-P04 | أوامر شراء منشأة | POs created | count | FEASIBLE | Exclude cancelled optional |
| S09-KPI-P05 | أوامر صادرة (اعتماد تشغيلي) | POs issued | count | FEASIBLE | Maps “approved PO” intent → `issued+` |
| S09-KPI-P06 | أوامر مستوفاة | POs fulfilled | count | FEASIBLE | received/closed or lines complete |
| S09-KPI-P07 | الكميات المستلمة | Received quantities | count | FEASIBLE | Prefer PO line rollup **xor** posted GRN lines |
| S09-KPI-P08 | استلامات معلّقة | Pending receipts | count | FEASIBLE | issued/partial + short lines |
| S09-KPI-P09 | زمن دورة الشراء | Procurement cycle time | duration | PARTIAL | Multi-hop; define start/end events |
| S09-KPI-P10 | التزامن الزمني للمورد | Supplier delivery timeliness | ratio | **BLOCKED** (proxy PARTIAL) | No promised date; lead_time_days only with Owner formula |

**Financial KPIs:** out of S09 Part 0 proposal (Phase 3 / finance).

---

## D. Seasonal Projects (S09-KPI-Jxx)

| ID | Arabic name | English name | Type | Feasibility | Notes |
|---|---|---|---|---|---|
| S09-KPI-J01 | مشاريع الموسم | Total seasonal projects | count | FEASIBLE | Soft-delete aware |
| S09-KPI-J02 | مشاريع نشطة | Active projects | count | FEASIBLE | `status = active` |
| S09-KPI-J03 | مشاريع مكتملة | Completed projects | count | FEASIBLE | `status = completed` |
| S09-KPI-J04 | مشاريع متأخرة | Overdue projects | count | PARTIAL | No project end date; task/phase proxy TBD |
| S09-KPI-J05 | إكمال المراحل | Phase completion rate | ratio | FEASIBLE | Informational phases only |
| S09-KPI-J06 | إنجاز المعالم | Milestone achievement | ratio | **BLOCKED_BY_DATA_SOURCE** | No milestones table |
| S09-KPI-J07 | التقدم الفعلي | Actual progress % | status | FEASIBLE | `progress_percent` cache |
| S09-KPI-J08 | مخطط مقابل فعلي | Planned vs actual progress | ratio | **BLOCKED_BY_DATA_SOURCE** | No planned progress |
| S09-KPI-J09 | معدل إكمال المشاريع | Project completion rate | ratio | FEASIBLE | Cohort TBD |
| S09-KPI-J10 | مشاريع معرضة للخطر | Projects at risk | count | PARTIAL | Delayed tasks and/or linked high risks |

---

## E. Risks & Complaints (S09-KPI-Rxx)

| ID | Arabic name | English name | Type | Feasibility | Notes |
|---|---|---|---|---|---|
| S09-KPI-R01 | مخاطر مفتوحة | Open risks | count | FEASIBLE | `status != closed` |
| S09-KPI-R02 | مخاطر عالية/حرجة | High/critical risks | count | FEASIBLE | severity bands BR-01 |
| S09-KPI-R03 | مخاطر مُخفَّفة | Mitigated risks | count | FEASIBLE | closed + `closure_outcome=mitigated` |
| S09-KPI-R04 | شكاوى مفتوحة | Open complaints | count | FEASIBLE | Status set TBD |
| S09-KPI-R05 | شكاوى محلولة | Resolved complaints | count | FEASIBLE | resolved and/or closed |
| S09-KPI-R06 | شكاوى متأخرة | Overdue complaints | count | **BLOCKED_BY_DATA_SOURCE** | No SLA (BR-05) |
| S09-KPI-R07 | معدل حل الشكاوى | Complaint resolution rate | ratio | FEASIBLE | Cohort TBD |
| S09-KPI-R08 | متوسط زمن الحل | Avg resolution time | duration | FEASIBLE | complaints `resolved_at`/`closed_at` |
| S09-KPI-R09 | شكاوى مُصعَّدة | Escalated complaints | count | FEASIBLE | `escalation_level > 0` |
| S09-KPI-R10 | مخاطر بانتظار إجراء | Risks awaiting action | count | PARTIAL | Open + treatments pending; exact rule TBD |

---

## Totals

| Metric | Count |
|---|---|
| Candidates listed | **48** |
| FEASIBLE (as-is or with Owner status pick only) | **24** |
| PARTIAL (needs Owner formula/tolerance/denominator) | **18** |
| BLOCKED_BY_DATA_SOURCE | **6** (A07, P10 as blocked primary, J06, J08, R06, + P10 counted once) |

Blocked list for reporting: **A07, J06, J08, R06, P10 (primary),** plus entitlement full % if Owner demands ledger-style fulfillment without denominator (T09 remains PARTIAL).

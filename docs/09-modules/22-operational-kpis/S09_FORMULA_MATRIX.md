# S09 — Formula Matrix (24 Owner-approved business semantics)

> **S09-D01:** `OWNER_APPROVED` — 24 IDs  
> **S09-D02:** `OWNER_APPROVED_WITH_DEFERRED_PRESENTATION_POLICY` (2026-11-08)  
> **S09-D03:** `OWNER_APPROVED` — existing enums/statuses frozen (2026-11-08); see `S09_D03_OWNER_APPROVAL_2026-11-08.md`  
> **S09-D04:** `OWNER_APPROVED` — tenant TZ · `DashboardClock::now()` · season-only v1 · A02/A03 interval · closed read+label · blank TZ design fallback Asia/Riyadh; see `S09_D04_OWNER_APPROVAL_2026-11-08.md`  
> **S09-D05:** `OWNER_APPROVED_TECHNICALLY_FROZEN` — C1=R1 · C2=404 · C3=yes; see clarifications record  
> **S09-D06:** `OWNER_APPROVED` — empty COUNT/SUM=0; empty AVG & J09 denom0 = undefined (`value:null`+state); UI «غير متاح»; precision → **D14** (Q8=B); see `S09_D06_OWNER_APPROVAL_2026-11-08.md`  
> **Record (formulas):** `S09_D02_OWNER_APPROVAL_2026-11-08.md`  
> **Not:** implementation authorization; no arbitrary rounding in formulas  

### Empty / undefined (D05-Q8 + D06; do not alter formulas)

| Kind | Behavior |
|---|---|
| COUNT (valid season, empty eligible) | `0` |
| P07 SUM empty | `0` |
| J07 AVG empty sample | undefined — not 0 |
| J09 denom 0 | undefined — not 0% |
| Null progress in AVG | exclude from sample; stored 0 included |
| Precision | **D14** — not frozen here |

## Cross-cutting defaults

| Field | Rule |
|---|---|
| Tenant scope | Server `TenantContext` / `UsesTenantScope` only |
| Season scope | Required selected season; entity’s own `season_id` |
| Procurement null season | **Excluded** (Q17=A) — no PR/join inference |
| Soft-delete | Exclude where SoftDeletes used |
| Zero denominator | Temp assumption **N/A** — **not** Owner Approved (Q18→D06/D14) |
| Ratio decimals | Temp assumption **1 d.p.** — **not** Owner Approved (Q18→D06/D14) |
| Unauthorized | **Omit** card (ADR-0014) — D08 pending |
| Refresh | On request; no S09 MVP cache — D09 pending |
| GPS | D10=B includes GPS in S09 **direction**; the frozen 24 formulas remain GPS-independent until a CR adds GPS KPIs |

---

## Transport

### S09-KPI-T01 — الحركات المجدولة / Scheduled movements

| # | Field | Content |
|---|---|---|
| Formula | `COUNT(*)` where `status IN ('planned','in_progress')` |
| Exclude | `draft`, `completed`, `cancelled` |
| Source | `transport_movements` / `TransportMovement` |
| Fields | `tenant_id`, `season_id`, `status` |
| SoftDeletes | N/A (no trait) |
| Permission (proposed D08) | `transportation.view` |
| Meaning | Planned or ongoing movements not yet completed |
| Consistency | `FORMULA_CONSISTENT` |
| Approval | OWNER_APPROVED (Q1=A) |

### S09-KPI-T02 — الحركات المكتملة / Completed movements

| Formula | `COUNT(*)` where `status = 'completed'` |
| Source | `transport_movements` |
| SoftDeletes | N/A |
| Permission | `transportation.view` |
| Consistency | `FORMULA_CONSISTENT` |
| Approval | OWNER_APPROVED (unchanged VERIFIED) |

### S09-KPI-T08 — أعطال المركبات / Vehicle breakdowns

| Formula | `COUNT(*)` where `status IN ('open','replaced','closed')` |
| Exclude | `cancelled` |
| Source | `transport_vehicle_breakdowns` |
| Label rule | **Not** “currently open breakdowns” — season recorded non-cancelled events |
| SoftDeletes | N/A |
| Permission | `transportation.view` |
| Consistency | `FORMULA_CONSISTENT` |
| Approval | OWNER_APPROVED (Q2=B) |

---

## Accommodation

### S09-KPI-A02 — الطاقة المحجوزة (تخصيص) / Allocated capacity

| Formula | `COUNT(accommodation_assignments)` eligible **and** active at **now** |
| Booking | `booking_status IN ('confirmed','completed')` |
| Interval (schema) | Half-open stay `[starts_at, effective_ends_at)` — same family as `AccommodationCapacityGuard` |
| Contains now | `starts_at <= :now AND effective_ends_at > :now` |
| Unit | **Assignment count** (Q3=A) — not bed/facility capacity, not positions peak |
| Exclude | `draft`, `cancelled`; expired (`effective_ends_at <= now`); future (`starts_at > now`) |
| ≠ | Occupancy (A03) |
| SoftDeletes | N/A on assignment model |
| Permission | `accommodation.view` |
| Clock | Exact TZ for `:now` → D04; fields verified |
| Consistency | `FORMULA_CONSISTENT` (clock TZ deferred D04) |
| Approval | OWNER_APPROVED (Q3=A, Q4=A) |

### S09-KPI-A03 — الإشغال الفعلي / Actual occupancy

| Formula | `COUNT(*)` where booking + ops + contains now |
| Booking | `booking_status = 'confirmed'` (formula-review eligibility; **not** expanded to completed) |
| Ops | `operational_status IN ('arrived','checked_in')` |
| Not occupied | `null` ops, effective `assigned`, `checked_out` |
| Interval | Same half-open rule as A02 |
| SoftDeletes | N/A |
| Permission | `accommodation.view` |
| Consistency | `FORMULA_CONSISTENT` (clock TZ deferred D04) |
| Approval | OWNER_APPROVED (Q4=A, Q5=A) |

---

## Procurement

**Season (Q17=A):** each of P01–P08 filters `season_id = :selected` on the entity table; **null excluded**.  
PR / RFQ / PO all have nullable `season_id` columns (migrations verified). GRN has **no** `season_id` (irrelevant for P07 SoR=A).

### S09-KPI-P01 — طلبات الشراء

| Formula | `COUNT(*)` where season match AND `status != 'cancelled'` |
| Source | `purchase_requests` |
| Permission | `purchase_requests.view` |
| Approval | OWNER_APPROVED (Q6=A, Q17=A) |

### S09-KPI-P02 — طلبات معتمدة

| Formula | `COUNT(*)` where `status = 'approved'` AND season non-null match |
| Source | `purchase_requests` |
| Note | No `approved_at` column — status snapshot |
| Approval | OWNER_APPROVED |

### S09-KPI-P03 — طلبات عروض صادرة

| Formula | `COUNT(*)` where `status IN ('published','awarded')` AND season match |
| Exclude | `draft`, `cancelled` |
| Permission | `rfq.view` |
| Approval | OWNER_APPROVED (Q7=A) |

### S09-KPI-P04 — أوامر شراء منشأة

| Formula | `COUNT(*)` where season match AND `status != 'cancelled'` |
| Source | `purchase_orders` |
| Permission | `purchase_orders.view` |
| Approval | OWNER_APPROVED (Q6=A) |

### S09-KPI-P05 — أوامر صادرة

| Formula | `COUNT(*)` where `status IN ('issued','partially_received','received','closed')` AND season match |
| Exclude | `draft`, `cancelled` |
| Label | **Issued** — never “approved PO” (no such status) |
| Approval | OWNER_APPROVED |

### S09-KPI-P06 — أوامر مستوفاة

| Formula | PO counts when **BOTH**: (1) `status IN ('received','closed')` (2) every line `bccomp(quantity_received, quantity_ordered, 4) >= 0` |
| Fields | `purchase_orders.status`; `purchase_order_lines.quantity_ordered`, `quantity_received` (decimal:4) |
| Line eligibility | All lines on the PO (no line-cancel status in schema) |
| Zero lines | Not fulfilled (`ClosePurchaseOrder` rejects empty lines; receive path needs lines) |
| Over-receipt | Line condition satisfied if received ≥ ordered; close blocks corrupted over-receipt |
| Short-close edge | Workflow **allows** `closed` with incomplete qty + reason — **fails** condition (2) → **not fulfilled** under Q8=C |
| Partial | `partially_received` never fulfilled |
| Consistency | `FORMULA_CONSISTENT` + documented integrity edge |
| Approval | OWNER_APPROVED (Q8=C) |

### S09-KPI-P07 — الكميات المستلمة

| Formula | `SUM(purchase_order_lines.quantity_received)` for POs with `season_id = :selected` (and tenant) |
| SoR | PO lines **only** — never add posted GRN quantities |
| GRN | Reconciliation evidence only |
| Cancelled POs | Cancel blocked when any `quantity_received > 0` — cancelled rows contribute 0 received in normal workflow |
| Permission | `purchase_orders.view` (Q10=A); D08 still pending for full KPI RBAC |
| Consistency | `FORMULA_CONSISTENT` |
| Approval | OWNER_APPROVED (Q9=A, Q10=A) |

### S09-KPI-P08 — استلامات معلّقة

| Formula | COUNT POs where `status IN ('issued','partially_received')` AND EXISTS line with `quantity_received < quantity_ordered` AND season match |
| Permission | `purchase_orders.view` |
| Consistency | `FORMULA_CONSISTENT` |
| Approval | OWNER_APPROVED |

---

## Seasonal Projects

SoftDeletes: **yes** — always `deleted_at IS NULL`.

### S09-KPI-J01 — مشاريع الموسم

| Formula | `COUNT(*)` where season/tenant, not soft-deleted, `status != 'cancelled'` |
| Approval | OWNER_APPROVED (Q11=A) |

### S09-KPI-J02 — مشاريع نشطة

| Formula | `status = 'active'` (+ soft-delete + season) |
| Approval | OWNER_APPROVED |

### S09-KPI-J03 — مشاريع مكتملة

| Formula | `status = 'completed'` (+ soft-delete + season) |
| Approval | OWNER_APPROVED |

### S09-KPI-J07 — التقدم الفعلي

| Formula | `AVG(progress_percent)` over projects with `status != 'cancelled'`, not soft-deleted, season match |
| Field | `season_projects.progress_percent` (decimal 5,2, default 0; maintained by `SeasonProjectProgressRollup`) |
| Forbidden | Elapsed-time progress; `delay_summary` (no writer) |
| Nulls | Column NOT NULL with default 0 in schema; **if** null ever appears → **exclude from AVG** (do not coerce to 0 in KPI layer) |
| Consistency | `FORMULA_CONSISTENT` |
| Approval | OWNER_APPROVED (Q12=A) |

### S09-KPI-J09 — معدل إكمال المشاريع

| Formula | `COUNT(status='completed') / COUNT(status IN ('active','completed')) * 100` |
| Exclude from denom | `draft`, `cancelled` (+ soft-deleted) |
| Zero denom | Display per Q18 temp N/A — **policy pending D06/D14** |
| Consistency | `FORMULA_CONSISTENT_WITH_DEFERRED_POLICY` |
| Approval | OWNER_APPROVED (Q11=A, Q13=A, Q18 defer) |

---

## Risks & Complaints

SoftDeletes: **yes** on risk & complaint models.

### S09-KPI-R01 — مخاطر مفتوحة

| Formula | `status != 'closed'` AND not soft-deleted AND season |
| Approval | OWNER_APPROVED |

### S09-KPI-R02 — مخاطر عالية/حرجة

| Formula | `severity_level IN ('high','critical') AND status != 'closed'` (+ soft-delete + season) |
| Severity | Stored `severity_level`; derived BR-01 via `score = likelihood × impact` → `RiskSeverity::fromScore` (≤4 low, ≤9 medium, ≤15 high, else critical) |
| Null severity | **Exclude** (pre-assessment) |
| Consistency | `FORMULA_CONSISTENT` |
| Approval | OWNER_APPROVED (Q16=A) |

### S09-KPI-R03 — مخاطر مُخفَّفة

| Formula | `status='closed' AND closure_outcome='mitigated'` |
| Approval | OWNER_APPROVED |

### S09-KPI-R04 — شكاوى مفتوحة

| Formula | `status IN ('new','under_review','in_progress')` |
| Exclude | `resolved`, `closed` |
| Disjoint | Must not overlap R05 |
| Approval | OWNER_APPROVED (Q14=A) |

### S09-KPI-R05 — شكاوى محلولة

| Formula | `status = 'resolved'` only |
| Not | `closed` (administrative closure ≠ resolution) |
| Approval | OWNER_APPROVED (Q15=A) |

### S09-KPI-R09 — شكاوى مُصعَّدة

| Formula | `escalation_level > 0` (prefer level; `escalated_at` corroborative) |
| Approval | OWNER_APPROVED |

---

## Compact appendix (24 only)

| ID | Formula sketch | Class |
|---|---|---|
| T01 | planned+in_progress | FORMULA_CONSISTENT |
| T02 | completed | FORMULA_CONSISTENT |
| T08 | open+replaced+closed | FORMULA_CONSISTENT |
| A02 | assignment count @ now | FORMULA_CONSISTENT |
| A03 | confirmed + arrived/checked_in @ now | FORMULA_CONSISTENT |
| P01 | PR ≠ cancelled, season | FORMULA_CONSISTENT |
| P02 | approved, season | FORMULA_CONSISTENT |
| P03 | published+awarded | FORMULA_CONSISTENT |
| P04 | PO ≠ cancelled, season | FORMULA_CONSISTENT |
| P05 | issued pipeline | FORMULA_CONSISTENT |
| P06 | received/closed **and** lines full | FORMULA_CONSISTENT |
| P07 | SUM po_lines.quantity_received | FORMULA_CONSISTENT |
| P08 | issued/partial + short lines | FORMULA_CONSISTENT |
| J01 | projects ≠ cancelled | FORMULA_CONSISTENT |
| J02 | active | FORMULA_CONSISTENT |
| J03 | completed | FORMULA_CONSISTENT |
| J07 | AVG progress_percent | FORMULA_CONSISTENT |
| J09 | completed / (active+completed) | FORMULA_CONSISTENT_WITH_DEFERRED_POLICY |
| R01 | ≠ closed | FORMULA_CONSISTENT |
| R02 | high/critical + open | FORMULA_CONSISTENT |
| R03 | closed+mitigated | FORMULA_CONSISTENT |
| R04 | open complaint set | FORMULA_CONSISTENT |
| R05 | resolved only | FORMULA_CONSISTENT |
| R09 | escalation_level > 0 | FORMULA_CONSISTENT |

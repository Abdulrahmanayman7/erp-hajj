# S09-D02 — Detailed Formula Review (24 KPIs)

> **S09-D01:** `OWNER_APPROVED` — exactly **24** identifiers (2026-11-08)  
> **S09-D02:** `OWNER_APPROVED_WITH_DEFERRED_PRESENTATION_POLICY` (Owner act **2026-11-08**)  
> **Approval record:** `S09_D02_OWNER_APPROVAL_2026-11-08.md`  
> **Baseline:** `origin/develop` @ `3a18b16` · local tip `d1dc512` (app tree ≡ develop)  
> **Evidence labels:** `VERIFIED_FROM_CODE` · `OWNER_APPROVED_BUSINESS_RULE` · `DEFERRED_PRESENTATION_POLICY` · `TECHNICAL_NOTE`

Historical review artifacts prior to 2026-11-08 Owner answers remain in companion files; this document now records **post-approval** frozen semantics.

## Global policy (D02 scope)

| Topic | Rule |
|---|---|
| Tenant | `TenantContext` only; models `UsesTenantScope` |
| Season | Required `season_id` match; procurement **null excluded** (Q17=A) |
| Soft-delete | Exclude where SoftDeletes used (projects, risks, complaints). Transport movements/breakdowns: no SoftDeletes |
| Zero denom / rounding | Temp assumptions only → **D06/D14** (Q18=A) |
| Unauthorized | Omit KPI (ADR-0014) — full map **D08** |
| GPS | None of the 24 |
| Timezone / historical windows | Snapshot **now** for A02/A03 (Q4=A); finer TZ/day bounds **D04** |

---

## Transport (3)

### T01 — الحركات المجدولة / Scheduled movements

| Field | Content |
|---|---|
| Classification | `VERIFIED_FROM_CODE` + `OWNER_APPROVED_BUSINESS_RULE` (Q1=A) |
| Model/Table | `TransportMovement` / `transport_movements` |
| Enum | `MovementStatus`: draft, planned, in_progress, completed, cancelled |
| Included | `planned`, `in_progress` |
| Excluded | `draft`, `completed`, `cancelled` |
| Formula | `COUNT(*)` with filters above + tenant + season |
| Soft-delete | N/A |
| Permission | `transportation.view` |
| GPS | Independent |
| Consistency | `FORMULA_CONSISTENT` |

### T02 — الحركات المكتملة / Completed movements

| Classification | `VERIFIED_FROM_CODE` |
| Formula | `COUNT(*)` where `status = 'completed'` |
| Consistency | `FORMULA_CONSISTENT` |

### T08 — أعطال المركبات / Vehicle breakdowns

| Classification | `VERIFIED_FROM_CODE` + `OWNER_APPROVED_BUSINESS_RULE` (Q2=B) |
| Model/Table | `TransportVehicleBreakdown` / `transport_vehicle_breakdowns` |
| Enum | `BreakdownStatus`: open, replaced, closed, cancelled |
| Included | `open`, `replaced`, `closed` |
| Excluded | `cancelled` |
| Label | Recorded non-cancelled breakdown **events** — **not** “currently open only” |
| Consistency | `FORMULA_CONSISTENT` |

---

## Accommodation (2)

### A02 — الطاقة المحجوزة (تخصيص) / Allocated capacity

| Classification | `VERIFIED_FROM_CODE` + `OWNER_APPROVED_BUSINESS_RULE` (Q3=A, Q4=A) |
| Model/Table | `AccommodationAssignment` / `accommodation_assignments` |
| Reserving statuses | `booking_status IN ('confirmed','completed')` — `AccommodationCapacityGuard` |
| Unit | **Assignment count** (not physical capacity / positions peak) |
| Interval fields | `starts_at`, `effective_ends_at` (half-open `[starts_at, effective_ends_at)`) |
| Contains now | `starts_at <= :now AND effective_ends_at > :now` |
| Excluded | draft, cancelled; non-overlapping (past/future) stays |
| ≠ Occupancy | Explicit |
| TECHNICAL_NOTE | Clock source for `:now` (tenant TZ vs stored UTC) deferred to **D04**; interval columns verified |
| Consistency | `FORMULA_CONSISTENT` |

### A03 — الإشغال الفعلي / Actual occupancy

| Classification | `VERIFIED_FROM_CODE` + `OWNER_APPROVED_BUSINESS_RULE` (Q4=A, Q5=A) |
| Booking gate | `booking_status = 'confirmed'` (formula-review eligibility; not silently expanded) |
| Ops include | `operational_status IN ('arrived','checked_in')` |
| Ops exclude | null / effective assigned / `checked_out` |
| Interval | Same contains-now rule as A02 |
| Note | Checkout sets `booking_status=completed` + `operational_status=checked_out` — outside A03 |
| Consistency | `FORMULA_CONSISTENT` |

---

## Procurement (8)

**Season rule (Q17=A):** `season_id = :selected` on entity; null excluded; no parent join.

### P01 — طلبات الشراء

| Formula | season match AND `status != 'cancelled'` |
| Enum | draft, submitted, under_review, returned, approved, rejected, cancelled |
| Permission | `purchase_requests.view` |
| Consistency | `FORMULA_CONSISTENT` |

### P02 — طلبات معتمدة

| Formula | `status = 'approved'` + season |
| Consistency | `FORMULA_CONSISTENT` |

### P03 — طلبات عروض صادرة

| Formula | `status IN ('published','awarded')` + season (Q7=A) |
| Exclude | draft, cancelled |
| Permission | `rfq.view` |
| Consistency | `FORMULA_CONSISTENT` |

### P04 — أوامر شراء منشأة

| Formula | season match AND `status != 'cancelled'` (Q6=A) |
| Permission | `purchase_orders.view` |
| Consistency | `FORMULA_CONSISTENT` |

### P05 — أوامر صادرة

| Formula | `status IN ('issued','partially_received','received','closed')` + season |
| Label | Issued — **not** approved PO |
| Consistency | `FORMULA_CONSISTENT` |

### P06 — أوامر مستوفاة

| Classification | `OWNER_APPROVED_BUSINESS_RULE` (Q8=C) + `VERIFIED_FROM_CODE` fields |
| Condition A | `status IN ('received','closed')` |
| Condition B | ∀ lines: `quantity_received >= quantity_ordered` (scale 4) |
| Both required | Yes |
| Zero lines | Not fulfilled |
| No line-cancel column | All PO lines are eligible lines |
| Short-close | `ClosePurchaseOrder` may set `closed` with remainder + reason without mutating qty → **fails B** → not fulfilled |
| Consistency | `FORMULA_CONSISTENT` (edge documented) |

### P07 — الكميات المستلمة

| SoR | `SUM(purchase_order_lines.quantity_received)` for season-scoped POs (Q9=A) |
| Forbidden | Adding posted GRN line quantities |
| Permission | `purchase_orders.view` (Q10=A); D08 pending for architecture |
| Maintainer | `PostGoodsReceipt` updates PO line rollup |
| Consistency | `FORMULA_CONSISTENT` |

### P08 — استلامات معلّقة

| Formula | `status IN ('issued','partially_received')` AND EXISTS short line + season |
| Consistency | `FORMULA_CONSISTENT` |

---

## Seasonal Projects (5)

SoftDeletes: **yes**.

### J01 — مشاريع الموسم

| Formula | count season/tenant, `deleted_at IS NULL`, `status != 'cancelled'` (Q11=A) |
| Consistency | `FORMULA_CONSISTENT` |

### J02 — مشاريع نشطة

| Formula | `status = 'active'` |
| Consistency | `FORMULA_CONSISTENT` |

### J03 — مشاريع مكتملة

| Formula | `status = 'completed'` |
| Consistency | `FORMULA_CONSISTENT` |

### J07 — التقدم الفعلي

| Formula | `AVG(progress_percent)` over non-cancelled, not deleted (Q12=A) |
| Field | `season_projects.progress_percent` — `SeasonProjectProgressRollup` |
| Null policy | Schema default 0 / NOT NULL; if null appears → **exclude from AVG** (do not coerce) |
| Forbidden | Elapsed time; `delay_summary` |
| Consistency | `FORMULA_CONSISTENT` |

### J09 — معدل إكمال المشاريع

| Numerator | `status = 'completed'` |
| Denominator | `status IN ('active','completed')` (Q13=A) |
| Zero denom | `DEFERRED_PRESENTATION_POLICY` (Q18) |
| Consistency | `FORMULA_CONSISTENT_WITH_DEFERRED_POLICY` |

---

## Risks & Complaints (6)

SoftDeletes: yes.

### R01 — مخاطر مفتوحة

| Formula | `status != 'closed'` |
| Consistency | `FORMULA_CONSISTENT` |

### R02 — مخاطر عالية/حرجة

| Formula | `severity_level IN ('high','critical') AND status != 'closed'` (Q16=A) |
| BR-01 | `RiskSeverity::fromScore` on L×I |
| Null severity | Exclude |
| Consistency | `FORMULA_CONSISTENT` |

### R03 — مخاطر مُخفَّفة

| Formula | `status='closed' AND closure_outcome='mitigated'` |
| Consistency | `FORMULA_CONSISTENT` |

### R04 — شكاوى مفتوحة

| Formula | `status IN ('new','under_review','in_progress')` (Q14=A) |
| Consistency | `FORMULA_CONSISTENT` |

### R05 — شكاوى محلولة

| Formula | `status = 'resolved'` only (Q15=A) |
| Disjoint | R04 ∩ R05 = ∅ |
| Consistency | `FORMULA_CONSISTENT` |

### R09 — شكاوى مُصعَّدة

| Formula | `escalation_level > 0` |
| Consistency | `FORMULA_CONSISTENT` |

**R07** remains out of S09 v1 (not in D01 list).

---

## Consistency audit vs earlier docs

| Issue | Resolution |
|---|---|
| Pre-approval “PROPOSED” formulas | Superseded by Owner Q1–Q18 on 2026-11-08 |
| A02 unit ambiguity | Frozen: assignment count |
| P07 dual SoR | Frozen: PO lines only |
| J07 aggregation | Frozen: arithmetic AVG |
| Q18 rounding/N/A | Deferred D06/D14 — temp assumptions only |
| P06 short-close vs dual condition | Documented edge — not a silent invent |

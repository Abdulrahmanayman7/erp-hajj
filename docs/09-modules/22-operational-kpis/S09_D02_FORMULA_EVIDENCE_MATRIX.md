# S09-D02 — Formula Evidence Matrix (24)

> **D02:** `OWNER_APPROVED_WITH_DEFERRED_PRESENTATION_POLICY` (2026-11-08)  
> **Q18:** presentation policy deferred to D06/D14  

| ID | Arabic | Class | Table | Key fields | Include | Exclude | Season | Perm | Consistency |
|---|---|---|---|---|---|---|---|---|---|
| T01 | الحركات المجدولة | APPROVED | transport_movements | status, season_id | planned, in_progress | draft, completed, cancelled | required | transportation.view | FORMULA_CONSISTENT |
| T02 | الحركات المكتملة | APPROVED | transport_movements | status | completed | others | required | transportation.view | FORMULA_CONSISTENT |
| T08 | أعطال المركبات | APPROVED | transport_vehicle_breakdowns | status, season_id | open, replaced, closed | cancelled | required | transportation.view | FORMULA_CONSISTENT |
| A02 | الطاقة المحجوزة | APPROVED | accommodation_assignments | booking_status, starts_at, effective_ends_at | confirmed, completed + contains now | draft, cancelled; non-overlap | required | accommodation.view | FORMULA_CONSISTENT |
| A03 | الإشغال الفعلي | APPROVED | accommodation_assignments | booking_status, operational_status, interval | confirmed + arrived/checked_in + now | null/assigned/checked_out; completed booking | required | accommodation.view | FORMULA_CONSISTENT |
| P01 | طلبات الشراء | APPROVED | purchase_requests | status, season_id | ≠ cancelled | cancelled; null season | non-null = selected | purchase_requests.view | FORMULA_CONSISTENT |
| P02 | طلبات معتمدة | APPROVED | purchase_requests | status | approved | others; null season | non-null | purchase_requests.view | FORMULA_CONSISTENT |
| P03 | طلبات عروض صادرة | APPROVED | procurement_rfqs | status | published, awarded | draft, cancelled; null season | non-null | rfq.view | FORMULA_CONSISTENT |
| P04 | أوامر منشأة | APPROVED | purchase_orders | status | ≠ cancelled | cancelled; null season | non-null | purchase_orders.view | FORMULA_CONSISTENT |
| P05 | أوامر صادرة | APPROVED | purchase_orders | status | issued, partially_received, received, closed | draft, cancelled | non-null | purchase_orders.view | FORMULA_CONSISTENT |
| P06 | أوامر مستوفاة | APPROVED | purchase_orders + lines | status, quantity_* | received/closed **and** lines full | partial; short-close incomplete; zero lines | non-null | purchase_orders.view | FORMULA_CONSISTENT |
| P07 | كميات مستلمة | APPROVED | purchase_order_lines via PO | quantity_received | SUM rollup | never + GRN | via PO season | purchase_orders.view | FORMULA_CONSISTENT |
| P08 | استلامات معلّقة | APPROVED | purchase_orders + lines | status, qtys | issued/partial + short line | received/closed full | non-null | purchase_orders.view | FORMULA_CONSISTENT |
| J01 | مشاريع الموسم | APPROVED | season_projects | status, deleted_at | ≠ cancelled | cancelled; soft-deleted | required | season_projects.view | FORMULA_CONSISTENT |
| J02 | مشاريع نشطة | APPROVED | season_projects | status | active | others | required | season_projects.view | FORMULA_CONSISTENT |
| J03 | مشاريع مكتملة | APPROVED | season_projects | status | completed | others | required | season_projects.view | FORMULA_CONSISTENT |
| J07 | التقدم الفعلي | APPROVED | season_projects | progress_percent | AVG non-cancelled | cancelled; elapsed-time; null→exclude | required | season_projects.view | FORMULA_CONSISTENT |
| J09 | معدل إكمال | APPROVED+DEFER | season_projects | status | completed / (active+completed) | draft, cancelled | required | season_projects.view | FORMULA_CONSISTENT_WITH_DEFERRED_POLICY |
| R01 | مخاطر مفتوحة | APPROVED | operational_risks | status, deleted_at | ≠ closed | closed; deleted | required | risks.view | FORMULA_CONSISTENT |
| R02 | مخاطر عالية/حرجة | APPROVED | operational_risks | severity_level, status | high, critical + open | closed; null severity | required | risks.view | FORMULA_CONSISTENT |
| R03 | مخاطر مخففة | APPROVED | operational_risks | status, closure_outcome | closed+mitigated | other outcomes | required | risks.view | FORMULA_CONSISTENT |
| R04 | شكاوى مفتوحة | APPROVED | operational_complaints | status | new, under_review, in_progress | resolved, closed | required | complaints.view | FORMULA_CONSISTENT |
| R05 | شكاوى محلولة | APPROVED | operational_complaints | status | resolved | closed; open set | required | complaints.view | FORMULA_CONSISTENT |
| R09 | شكاوى مصعّدة | APPROVED | operational_complaints | escalation_level | level>0 | level=0 | required | complaints.view | FORMULA_CONSISTENT |

## Source file anchors

| Domain | Primary paths |
|---|---|
| Transport | `Enums/MovementStatus.php`, `BreakdownStatus.php`; models `TransportMovement`, `TransportVehicleBreakdown` |
| Accommodation | `Support/AccommodationCapacityGuard.php` (half-open + reserving); `Models/AccommodationAssignment.php`; `Actions/AccommodationOperationsActions.php` |
| Procurement | `Enums/PurchaseRequestStatus.php`, `ProcurementRfqStatus.php`, `PurchaseOrderStatus.php`; `Actions/PostGoodsReceipt.php`, `ClosePurchaseOrder.php`; `Policies/PurchaseOrderPolicy.php` (`purchase_orders.view`) |
| Projects | `Enums/SeasonProjectStatus.php`; `Models/SeasonProject.php` SoftDeletes; `Support/SeasonProjectProgressRollup.php` |
| Risks | `Enums/RiskStatus.php`, `RiskSeverity.php`, `RiskClosureOutcome.php`, `ComplaintStatus.php`; SoftDeletes on models |

## Double-count / terminology controls (frozen)

| Risk | Mitigation (Owner-approved) |
|---|---|
| P07 PO + GRN | **Forbidden** — PO rollup only (Q9=A) |
| A02 called occupancy | **Forbidden** — allocation / assignment count |
| P05 called approved/received | **Forbidden** — issued pipeline |
| T08 called open-only | **Forbidden** — non-cancelled events (Q2=B) |
| J07 from clock time | **Forbidden** — `progress_percent` only |
| R03 = treatment done | **Forbidden** — closure_outcome |
| R04 ∩ R05 | Disjoint status sets (Q14/Q15) |

## Technical notes (not SOURCE_GAP)

| Note | Disposition |
|---|---|
| A02/A03 `:now` timezone | Schema interval clear; clock policy → D04 |
| P06 short-close vs Q8=C | Workflow-allowed incomplete `closed` → not fulfilled |
| Q18 display | Deferred — not a formula source gap |
| D08 full RBAC | Pending; Q10 freezes P07 key only |

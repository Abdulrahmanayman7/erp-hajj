# S09 — Data Source Map

> **Status:** PROPOSED evidence map (EXISTING schema)  
> **Date:** 2026-11-08  
> **Rule:** Never invent tables/columns. Paths are relative to `backend/`.

---

## Transport

| Concept | Model | Table | Key columns | Tenant | Season | Status / time semantics | Limitations | Evidence |
|---|---|---|---|---|---|---|---|---|
| Movement | `Transportation\Models\TransportMovement` | `transport_movements` | `status`, `planned_departure_at`, `planned_arrival_at`, `started_at`, `completed_at` | `tenant_id` | `season_id` | Status enum `MovementStatus`: draft/planned/in_progress/completed/cancelled. Ops times staff-recorded | No GPS; no `is_delayed` | migrations `2026_10_05_200007_*`; `Enums/MovementStatus.php` |
| Vehicle run | `TransportVehicleRun` | `transport_vehicle_runs` | `status`, `departed_at`, `arrived_at`, `completed_at`, capacity fields | `tenant_id` | via movement/season | `RunStatus`: planned/boarding/departed/arrived/completed/cancelled | On-time = compare to parent planned times | `2026_10_05_200008_*` |
| Season vehicle | `TransportSeasonVehicle` | `transport_season_vehicles` | `is_active`, `operational_capacity` | `tenant_id` | `season_id` | Active flag ≠ free of overlapping runs | Availability derived via overlap guards | `2026_10_05_200004_*` |
| Entitlement | `TransportEntitlement` | `transport_entitlements` | program/class/service | `tenant_id` | `season_id` | Policy rows, not fulfillment ledger | Fulfillment needs passenger join | `2026_10_05_200005_*` |
| Passenger | `TransportRunPassenger` | `transport_run_passengers` | `status`, `entitlement_status_at_assignment` | `tenant_id` | via run | assigned/boarded/arrived/no_show/cancelled | Live mismatch computed | `PassengerStatus.php` |
| Breakdown | `TransportVehicleBreakdown` | `transport_vehicle_breakdowns` | `status`, `occurred_at` | `tenant_id` | via vehicle/run | open/replaced/closed/cancelled | Incidents ≠ GPS | `2026_10_30_210000_*` |

---

## Accommodation

| Concept | Model | Table | Key columns | Tenant | Season | Semantics | Limitations | Evidence |
|---|---|---|---|---|---|---|---|---|
| Facility | `AccommodationFacility` | `accommodation_facilities` | `kind`, `is_active` | `tenant_id` | via season facility | Master | No readiness column | `2026_10_15_180001_*` |
| Season facility | `AccommodationSeasonFacility` | `accommodation_season_facilities` | `is_active`, `audience` | `tenant_id` | `season_id` | Link | — | `2026_10_15_180005_*` |
| Season unit | `AccommodationSeasonUnit` | `accommodation_season_units` | `capacity`, `allocation_mode`, `is_active` | `tenant_id` | via season facility | Capacity SoR for supply | Needs as-of window | `2026_10_15_180006_*` |
| Assignment | `AccommodationAssignment` | `accommodation_assignments` | `booking_status`, `operational_status`, `starts_at`, `effective_ends_at` | `tenant_id` | via unit/season | **Allocation** = confirmed/completed booking; **Occupancy** = operational arrived/checked_in | Do not equate | `2026_10_19_200001_*`, widen booking check |
| Events | `AccommodationEvent` | `accommodation_events` | `event_type`, `occurred_at` | `tenant_id` | via assignment | arrived/checked_in/checked_out/… | Append-only ops timeline | `2026_10_19_200003_*` |
| Capacity math | `AccommodationCapacityGuard` | (service) | peak concurrent overlapping | tenant via queries | season via units | Half-open intervals | Not a KPI API | `Support/AccommodationCapacityGuard.php` |

---

## Procurement

| Concept | Model | Table | Key columns | Tenant | Season | Semantics | Limitations | Evidence |
|---|---|---|---|---|---|---|---|---|
| PR | `PurchaseRequest` | `purchase_requests` | `status`, `submitted_at` | `tenant_id` | **nullable** `season_id` | draft→…→approved | No `approved_at` | `PurchaseRequestStatus.php` |
| RFQ | `ProcurementRfq` | `procurement_rfqs` | `status`, `published_at`, `awarded_at` | `tenant_id` | nullable `season_id` | draft/published/awarded/cancelled | “Issued” = published+ | `ProcurementRfqStatus.php` |
| PO | `PurchaseOrder` | `purchase_orders` | `status`, `issued_at`, `closed_at` | `tenant_id` | nullable `season_id` | draft/issued/partially_received/received/closed/cancelled | **No `approved`** — map to issued | `PurchaseOrderStatus.php` |
| PO line | `PurchaseOrderLine` | `purchase_order_lines` | `quantity_ordered`, `quantity_received` | `tenant_id` | via PO | Rollup on post GRN | Prefer vs GRN sum — not both | `PostGoodsReceipt.php` |
| GRN | `GoodsReceipt` | `goods_receipts` | `status`, `posted_at` | `tenant_id` | via PO | draft/posted | Partial receipts multi-row | `GoodsReceiptStatus.php` |
| Offer | `SupplierOffer` | `supplier_offers` | `lead_time_days` | `tenant_id` | — | Optional lead time | No promised delivery date on PO | models under SuppliersAndProcurement |

---

## Seasonal Projects

| Concept | Model | Table | Key columns | Tenant | Season | Semantics | Limitations | Evidence |
|---|---|---|---|---|---|---|---|---|
| Project | `SeasonProject` | `season_projects` | `status`, `progress_percent`, `activated_at`, `completed_at`, `delay_summary` | `tenant_id` | **required** `season_id` | draft/active/completed/cancelled | `delay_summary` unused writer; no planned progress | `SeasonProjectStatus.php` |
| Phase | `SeasonProjectPhase` | `season_project_phases` | `status`, `starts_on`, `ends_on` | `tenant_id` | via project | planned/active/done informational | Not workflow gate | BR-04 / E-Q03 |
| Task | `Task` | `tasks` | `project_id`, `due_date`, `delay_state`, `progress_percent`, `status` | `tenant_id` | `season_id` | Open statuses + delay_state | Overdue project needs Owner def | `TaskStatus`, SeasonProjects rollup |

**Milestones:** no table — BLOCKED.

---

## Risks & Complaints

| Concept | Model | Table | Key columns | Tenant | Season | Semantics | Limitations | Evidence |
|---|---|---|---|---|---|---|---|---|
| Risk | `OperationalRisk` | `operational_risks` | `status`, `severity_level`, `likelihood`, `impact`, `score`, `closure_outcome` | `tenant_id` | required `season_id` | Severity bands BR-01 | No `closed_at` | `RiskScoreCalculator.php`, BUSINESS_RULES |
| Treatment | `OperationalRiskTreatment` | `operational_risk_treatments` | `status`, `due_at` | `tenant_id` | via risk | Risk-only due | Not complaint SLA | — |
| Complaint | `OperationalComplaint` | `operational_complaints` | `status`, `resolved_at`, `closed_at`, `escalation_level`, `escalated_at` | `tenant_id` | required `season_id` | new→…→resolved/closed | **No SLA / overdue fields (BR-05)** | BUSINESS_RULES BR-05 |

---

## Soft-delete / audit

Respect SoftDeletes where models use them (`deleted_at IS NULL`). Prefer domain statuses over inventing deletion as “closed.” Audit tables are not KPI SoR for counts unless Owner decides otherwise.

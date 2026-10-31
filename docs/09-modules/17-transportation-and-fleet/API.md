# Transportation and Fleet — API

> **Status:** Implemented (backend sprint 1).
> **Last updated:** 2026-10-05
> Base: `/api/v1`, authenticated tenant routes (`auth:sanctum`, tenant context, active tenant). Standard envelope (`success`, `message`, `data`, `meta`, `errors`, `code`).

## Context rules

- `tenant_id` is never accepted from input.
- Season-scoped routes use the `season.context` middleware: `season_id` comes from the Season Context (`X-Season-Id` header or the tenant's Active season); `season_id` in a payload is ignored. A route model from another season → `404`; another tenant → `404`.
- Writes in a Closed season → `422 SEASON_CLOSED`. Execution in a non-Active season → `422 SEASON_NOT_ACTIVE`.
- Endpoints that record an operational fact accept optional `occurred_at` and `reason` (backdating rules in BUSINESS_RULES §12).

## Tenant-level (no season context)

| Method | Path | Permission |
|---|---|---|
| GET | `/transport-points` | `transportation.view` |
| POST | `/transport-points` | `transportation.manage_registry` |
| GET | `/transport-points/{transportPoint}` | `transportation.view` |
| PATCH | `/transport-points/{transportPoint}` | `transportation.manage_registry` |
| DELETE | `/transport-points/{transportPoint}` | `transportation.manage_registry` |
| POST | `/transport-points/{transportPoint}/activate` · `/deactivate` | `transportation.manage_registry` |
| GET | `/transport-vehicles` | `transportation.view` |
| POST | `/transport-vehicles` | `transportation.manage_registry` |
| GET | `/transport-vehicles/{transportVehicle}` | `transportation.view` |
| PATCH | `/transport-vehicles/{transportVehicle}` | `transportation.manage_registry` |
| DELETE | `/transport-vehicles/{transportVehicle}` | `transportation.manage_registry` |
| POST | `/transport-vehicles/{transportVehicle}/activate` · `/deactivate` | `transportation.manage_registry` |
| PUT | `/transport-vehicles/{transportVehicle}/links` | `transportation.manage_registry` |
| GET | `/transport-external-drivers` | `transportation.view` |
| POST | `/transport-external-drivers` | `transportation.manage_registry` |
| GET | `/transport-external-drivers/{externalDriver}` | `transportation.view` |
| PATCH | `/transport-external-drivers/{externalDriver}` | `transportation.manage_registry` |
| DELETE | `/transport-external-drivers/{externalDriver}` | `transportation.manage_registry` |
| POST | `/transport-external-drivers/{externalDriver}/activate` · `/deactivate` | `transportation.manage_registry` |

Bodies:

- Point: `code`, `name`, `point_type`, `notes?`.
- Vehicle: `plate_number`, `vehicle_class`, `notes?` (number is server-generated). Links: `asset_id?`, `contract_id?` (null clears).
- ExternalDriver: `full_name`, `phone?`, `notes?`.

Lists: `search`, `is_active`, `per_page` (≤ 100), paginated `meta`.

## Season-scoped (`season.context`)

### Fleet and entitlements

| Method | Path | Permission |
|---|---|---|
| GET | `/transport-season-vehicles` | `transportation.view` |
| POST | `/transport-season-vehicles` | `transportation.manage_season_fleet` |
| GET | `/transport-season-vehicles/{seasonVehicle}` | `transportation.view` |
| PATCH | `/transport-season-vehicles/{seasonVehicle}` | `transportation.manage_season_fleet` |
| DELETE | `/transport-season-vehicles/{seasonVehicle}` | `transportation.manage_season_fleet` |
| PUT | `/transport-season-vehicles/{seasonVehicle}/default-crew` | `transportation.manage_season_fleet` |
| POST | `/transport-season-vehicles/{seasonVehicle}/activate` · `/deactivate` | `transportation.manage_season_fleet` |
| GET | `/transport-entitlements` | `transportation.view` |
| POST | `/transport-entitlements` | `transportation.manage_entitlements` |
| GET | `/transport-entitlements/{entitlement}` | `transportation.view` |
| PATCH | `/transport-entitlements/{entitlement}` | `transportation.manage_entitlements` |
| DELETE | `/transport-entitlements/{entitlement}` | `transportation.manage_entitlements` |

Bodies:

- SeasonVehicle: `transport_vehicle_id`, `operational_capacity`, `service_level`, `notes?`. PATCH: `operational_capacity?`, `service_level?`, `notes?`.
- Default crew: `default_driver_employee_id?` | `default_external_driver_id?` (at most one), `default_supervisor_employee_id?`.
- Entitlement: `season_program_id`, `vehicle_class?`, `service_level?` (at least one).

### Movements

| Method | Path | Permission |
|---|---|---|
| GET | `/transport-movements` | `transportation.view` |
| POST | `/transport-movements` | `transportation.manage_movements` |
| GET | `/transport-movements/{movement}` | `transportation.view` |
| PATCH | `/transport-movements/{movement}` | `transportation.manage_movements` |
| POST | `/transport-movements/{movement}/plan` | `transportation.manage_movements` |
| POST | `/transport-movements/{movement}/start` | `transportation.operate_runs` |
| POST | `/transport-movements/{movement}/complete` | `transportation.operate_runs` |
| POST | `/transport-movements/{movement}/cancel` | `transportation.manage_movements` |
| GET | `/transport-movements/{movement}/timeline` | `transportation.view` |
| POST | `/transport-movements/{movement}/runs` | `transportation.manage_movements` |

Bodies: Movement `origin_point_id`, `destination_point_id`, `planned_departure_at`, `planned_arrival_at`, `notes?`. Cancel: `reason`. Start/complete: `occurred_at?`, `reason?`. Run: `transport_season_vehicle_id`, `capacity_override?`, `notes?`. List filters: `status`, `per_page`.

### Runs

| Method | Path | Permission |
|---|---|---|
| GET | `/transport-runs/{run}` | `transportation.view` |
| PATCH | `/transport-runs/{run}` | `transportation.manage_movements` |
| POST | `/transport-runs/{run}/start-boarding` · `/depart` · `/arrive` · `/complete` | `transportation.operate_runs` |
| POST | `/transport-runs/{run}/cancel` | `transportation.operate_runs` |
| PUT | `/transport-runs/{run}/vehicle` | `transportation.operate_runs` |
| PUT | `/transport-runs/{run}/driver` | `transportation.operate_runs` |
| PUT | `/transport-runs/{run}/supervisor` | `transportation.operate_runs` |
| GET | `/transport-runs/{run}/passengers` | `transportation.view` |
| POST | `/transport-runs/{run}/passengers` | `transportation.manage_manifest` (+ `transportation.override_entitlement` when overriding) |
| POST | `/transport-runs/{run}/passengers/board` · `/no-show` · `/arrive` | `transportation.operate_runs` |

Bodies:

- PATCH run: `capacity_override?` (null clears), `notes?`.
- Lifecycle: `occurred_at?`, `reason?`; cancel requires `reason`.
- Vehicle: `transport_season_vehicle_id`, `reason?`. Driver: `driver_employee_id` | `external_driver_id`, `reason?`. Supervisor: `supervisor_employee_id` (null ends the current supervisor), `reason?`.
- Add passengers: `source` (`individual` | `pilgrim_group` | `family_group`), `season_pilgrim_ids[]` (individual) or `source_id` (group/family), `entitlement_override?`, `override_reason?`. **All-or-nothing** for every source: any failing resolved member rejects the whole command with no partial insert; failing ids are listed in `errors.season_pilgrim_ids`. Response `meta`: `added` only.
- Outcomes: `passenger_ids[]`, `occurred_at?`, `reason?`. All-or-nothing: an invalid transition returns `TRANSPORT_PASSENGER_INVALID_TRANSITION` with the offending ids in `errors.passenger_ids`.

### Passengers, timeline, reviews

| Method | Path | Permission |
|---|---|---|
| POST | `/transport-run-passengers/{passenger}/cancel` | `transportation.manage_manifest` |
| POST | `/transport-run-passengers/{passenger}/entitlement-override` | `transportation.override_entitlement` |
| POST | `/transport-timeline-events/{timelineEvent}/correct` | `transportation.correct_history` |
| GET | `/transport-review-items` | `transportation.view` |
| POST | `/transport-review-items/{reviewItem}/resolve` | `transportation.resolve_reviews` |

Bodies: cancel `reason`; override `reason`; correct `occurred_at`, `reason`; resolve `resolution_note`. Review list filters: `status`, `severity`.

### Breakdowns & vehicle replacement (S02 / ADR-0021 D03)

Dedicated operational breakdown domain. Replacement appends a T7 vehicle assignment (does not overwrite history). Passenger moves require an explicit plan when replacement capacity is insufficient — no silent drops.

| Method | Path | Permission |
|---|---|---|
| GET | `/transport-breakdowns` | `transportation.view` |
| POST | `/transport-runs/{run}/breakdowns` | `transportation.operate_runs` |
| GET | `/transport-breakdowns/{breakdown}` | `transportation.view` |
| POST | `/transport-breakdowns/{breakdown}/close` | `transportation.operate_runs` |
| POST | `/transport-breakdowns/{breakdown}/cancel` | `transportation.operate_runs` |
| GET | `/transport-breakdowns/{breakdown}/preview-replacement` | `transportation.view` |
| POST | `/transport-breakdowns/{breakdown}/replace` | `transportation.operate_runs` |

Bodies / query:

- Record: `reason` (required), `notes?`, `occurred_at?`.
- Close / cancel: `reason` (required). Statuses: `open` → `closed` | `cancelled` | `replaced`.
- Preview query: `replacement_season_vehicle_id`. Response includes `occupied_count`, `capacity_gap`, `plan_required`, replacement capacity/overlap flags, and candidate passengers.
- Replace: `replacement_season_vehicle_id`, `reason?` (required after boarding per T7), `idempotency_key?`, `reassignments?` as `[{ run_passenger_id, target_run_id }]`. Atomic: locks breakdown → movement/runs → season vehicles → pilgrims → passengers; re-checks capacity/overlap inside the transaction; idempotent replay by key or existing `replacement_id`.

## Resources

- The passenger resource exposes no pilgrim identity numbers (passport / national ID); it carries the SeasonPilgrim reference, the pilgrim name, and the assignment snapshot only. Identity data stays in the foundation resources under `season_pilgrims.view_sensitive`.
- Passenger resource exposes `assignment_context` (immutable T9 decision: program, entitlement id/requirements, vehicle classification at assignment, status, override who/why, assigned_by), live `entitlement_status` (`none`, `compatible`, `mismatch`, `overridden`), and live override fields.
- Run resource exposes `effective_capacity`, `occupied_count`, current vehicle / driver / supervisor, and full assignment history (including the initial rows created with the Run).

## Error codes

| Code | HTTP | Meaning |
|---|---|---|
| `TRANSPORT_POINT_CODE_TAKEN` | 422 | Duplicate point code |
| `TRANSPORT_POINT_INACTIVE` | 422 | Point inactive |
| `TRANSPORT_POINT_IN_USE` | 422 | Point referenced by a Movement |
| `TRANSPORT_POINTS_SAME` | 422 | Origin = destination |
| `TRANSPORT_VEHICLE_PLATE_TAKEN` | 422 | Duplicate plate |
| `TRANSPORT_VEHICLE_ASSET_TAKEN` | 422 | Asset linked to another Vehicle |
| `TRANSPORT_VEHICLE_INACTIVE` | 422 | Vehicle inactive |
| `TRANSPORT_VEHICLE_IN_USE` | 422 | Vehicle in a season fleet |
| `TRANSPORT_NUMBER_UNAVAILABLE` | 409 | Vehicle/Movement number could not be issued; retry |
| `TRANSPORT_LINK_INVALID` | 422 | Linked asset/contract (or other reference) not found in the tenant |
| `TRANSPORT_EXTERNAL_DRIVER_INACTIVE` | 422 | ExternalDriver inactive |
| `TRANSPORT_EXTERNAL_DRIVER_IN_USE` | 422 | ExternalDriver referenced |
| `TRANSPORT_SEASON_VEHICLE_DUPLICATE` | 422 | Vehicle already in the season |
| `TRANSPORT_SEASON_VEHICLE_INACTIVE` | 422 | SeasonVehicle inactive |
| `TRANSPORT_SEASON_VEHICLE_IN_USE` | 422 | SeasonVehicle assigned to a Run |
| `TRANSPORT_CAPACITY_BELOW_ASSIGNED` | 422 | Capacity below occupied count / override |
| `TRANSPORT_EMPLOYEE_INVALID` | 422 | Employee missing or inactive |
| `TRANSPORT_DRIVER_INVALID` | 422 | Not exactly one driver reference |
| `TRANSPORT_ENTITLEMENT_DUPLICATE` | 422 | Program already has an entitlement |
| `TRANSPORT_MOVEMENT_INVALID_TRANSITION` | 422 | |
| `TRANSPORT_MOVEMENT_NOT_EDITABLE` | 422 | |
| `TRANSPORT_MOVEMENT_RUNS_NOT_SETTLED` | 422 | Runs not Completed/Cancelled |
| `TRANSPORT_RUN_INVALID_TRANSITION` | 422 | |
| `TRANSPORT_RUN_NOT_EDITABLE` | 422 | |
| `TRANSPORT_RUN_DRIVER_REQUIRED` | 422 | Depart without driver |
| `TRANSPORT_RUN_HAS_BOARDED_PASSENGERS` | 422 | |
| `TRANSPORT_RUN_PASSENGERS_NOT_SETTLED` | 422 | |
| `TRANSPORT_RUN_CAPACITY_OVERRIDE_INVALID` | 422 | Override > capacity or < occupied |
| `TRANSPORT_VEHICLE_OVERLAP` | 422 | |
| `TRANSPORT_DRIVER_OVERLAP` | 422 | |
| `TRANSPORT_SUBSTITUTION_REASON_REQUIRED` | 422 | |
| `TRANSPORT_PASSENGER_NOT_ELIGIBLE` | 422 | Not an Active SeasonPilgrim of the season |
| `TRANSPORT_PASSENGER_DUPLICATE` | 422 | Already on the Run |
| `TRANSPORT_PASSENGERS_NONE_ELIGIBLE` | 422 | Group/family had no eligible member |
| `TRANSPORT_SOURCE_INVALID` | 422 | PilgrimGroup/FamilyGroup not found in the season |
| `TRANSPORT_CAPACITY_EXCEEDED` | 422 | |
| `TRANSPORT_PASSENGER_INVALID_TRANSITION` | 422 | |
| `TRANSPORT_MANIFEST_LOCKED` | 422 | Run not accepting passengers |
| `TRANSPORT_ENTITLEMENT_MISMATCH` | 422 | |
| `TRANSPORT_ENTITLEMENT_OVERRIDE_FORBIDDEN` | 403 | Override without permission |
| `TRANSPORT_ENTITLEMENT_OVERRIDE_NOT_NEEDED` | 422 | No mismatch to override |
| `TRANSPORT_OCCURRED_AT_FUTURE` | 422 | |
| `TRANSPORT_BACKDATE_REASON_REQUIRED` | 422 | |
| `TRANSPORT_BACKDATE_FORBIDDEN` | 403 | |
| `TRANSPORT_TIMELINE_EVENT_NOT_CORRECTABLE` | 422 | |
| `TRANSPORT_TIMELINE_EVENT_ALREADY_CORRECTED` | 422 | |
| `TRANSPORT_REVIEW_ALREADY_RESOLVED` | 422 | |
| `TRANSPORT_BREAKDOWN_NOT_OPEN` | 422 | Breakdown not open for replace/close/cancel |
| `TRANSPORT_BREAKDOWN_RUN_TERMINAL` | 422 | Run completed/cancelled |
| `TRANSPORT_BREAKDOWN_VEHICLE_MISMATCH` | 422 | Breakdown vehicle is no longer current on the run |
| `TRANSPORT_REPLACEMENT_SAME_VEHICLE` | 422 | Replacement equals affected vehicle |
| `TRANSPORT_REPLACEMENT_CAPACITY_PLAN_REQUIRED` | 422 | Explicit reassignment plan required for capacity gap |
| `TRANSPORT_REPLACEMENT_PLAN_INVALID` | 422 | Plan rows invalid / incomplete |
| `TRANSPORT_REPLACEMENT_BOARDED_EXCEED_CAPACITY` | 422 | Boarded/arrived exceed replacement capacity |
| `TRANSPORT_REPLACEMENT_TARGET_RUN_INVALID` | 422 | Target run not eligible for reassignment |
| `SEASON_RESOURCE_REFERENCED` | 422 | Foundation: hard delete blocked by external references |

No GPS, QR, offline, seat, or Accommodation endpoints.

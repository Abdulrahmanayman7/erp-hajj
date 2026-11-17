# G5 — GPS History Retention Policy Contract (BLOCKED)

> **Status:** DESIGN / CONTRACT ONLY — **BLOCKED**  
> **Date:** 2026-11-16  
> **Does not authorize:** history table creation, retention period invention, or G5 COMPLETE.

## Binding technical posture

| Item | Current value |
|---|---|
| Last-known table | `gps_vehicle_last_known` (present) |
| History table | **Absent** (`gps_vehicle_location_history` must stay missing) |
| Writer | `GpsLocationHistoryWriterInterface` — **unbound** |
| Config | `config/gps.php` → `history.*` |
| `gps.history.enabled` | `false` (default) |
| `gps.history.retention_days` | **null / TBD** |
| `gps.history.deletion_policy` | **null / TBD** |
| `gps.history.consent_required` | `true` (design default until legal decision) |

## Planned authorization model (not routed)

| Permission | Intended action | Status |
|---|---|---|
| `gps.history.view` | Read historical points for a season/vehicle | **NOT IMPLEMENTED** |
| `gps.history.export` | Export history (tenant-scoped) | **NOT IMPLEMENTED** |
| `gps.history.delete` | Erasure / deletion workflow | **NOT IMPLEMENTED** |

Rules when G5 is later authorized:

1. Deny by default; explicit permission required.  
2. Tenant scope fail-closed; cross-tenant → 404.  
3. Every history read/export/delete produces an audit event **without** dumping full coordinate streams into audit values beyond agreed metadata.  
4. No public/unauthenticated history endpoints.

## Owner + legal decisions required before implementation

1. Written retention period (days) and legal basis.  
2. Consent / notice requirements for tracked staff/vehicles.  
3. Access-control approval for the planned permissions above.  
4. Deletion / erasure procedure (soft vs hard vs legal hold).  
5. Explicit Owner authorization to create `gps_vehicle_location_history` and bind a writer.

## Tests guarding BLOCKED posture

- `tests/Feature/Gps/GpsG5RetentionContractTest.php`  
- `tests/Feature/Gps/GpsProviderBindingTest.php`  
- `tests/Feature/Integration/S11BlockedIntegrationsTest.php`  
- `php artisan gps:verify-g4-readiness` asserts history disabled + writer unbound + table absent

## Explicit forbidden actions

- Inventing `GPS_HISTORY_RETENTION_DAYS` for production.  
- Shipping a history migration without the Owner/legal record.  
- Binding `GpsLocationHistoryWriterInterface` while `gps.history.enabled=false`.  
- Relocating G5 to Phase 3 to close the gap.

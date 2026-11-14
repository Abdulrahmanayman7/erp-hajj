# G4 / G5 — Approved Technical Preparation (Phase 2)

> **Updated:** 2026-11-14  
> **Does not authorize:** live vendor binding, history table creation, production GPS claims, or G4/G5 COMPLETE.  
> **Must not relocate G4/G5 to Phase 3.**

## G4 — Live provider binding (prep only)

| Item | Status |
|---|---|
| Contract | `GpsProviderInterface` + `NormalizedLocationDto` (G0) |
| Default binding | `NullGpsProvider` via `GPS_PROVIDER=null` |
| Fail-closed registry | Unknown `GPS_PROVIDER` keys throw `GpsProviderNotApprovedException` (no silent Null fallback) |
| Approved vendor adapter | **Absent** — Owner must name vendor/API + credentials before any adapter class is added |
| Invented vendor | **Forbidden** |

### Owner gate checklist (before any live adapter)

1. Named vendor / API contract document  
2. Credential vault path (not repo)  
3. Accuracy / update interval / device identity mapping  
4. Tenant secret model remains per-tenant HMAC (no global secret)  
5. Live verification plan on disposable UAT only  
6. Explicit Owner authorization record

## G5 — History / retention (prep only)

| Item | Status |
|---|---|
| Last-known table | Present (`gps_vehicle_last_known`) |
| History table | **Absent** by design (`gps_vehicle_location_history` must stay missing) |
| Writer seam | `GpsLocationHistoryWriterInterface` — **unbound**; no implementation |
| Retention period | **TBD** — legal decision required (consent, access, deletion/erasure) |
| Invented retention | **Forbidden** |

### Owner gate checklist (before history migration)

1. Written retention period + legal basis  
2. Consent / notice requirements  
3. Access control + audit for history reads  
4. Deletion / erasure procedure  
5. Explicit Owner authorization to create history storage

## Tests guarding BLOCKED posture

- `tests/Feature/Gps/GpsProviderBindingTest.php` — Null default, unapproved provider fails closed, history writer unbound, history table absent  
- `tests/Feature/Integration/S11BlockedIntegrationsTest.php` — S11 BLOCKED markers for G4/G5/U03

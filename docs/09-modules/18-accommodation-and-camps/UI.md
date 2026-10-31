# Accommodation and Camps — UI

> **Status:** Implemented (staff shell) — S03 **PARTIAL** until Owner UAT/acceptance.  
> **Last updated:** 2026-10-30

Arabic RTL shell, TanStack Query, `can()` / `PermissionGuard`, season workspace via `AccommodationSeasonBar` + `X-Season-Id`.

## Surfaces

| Route | Purpose |
|---|---|
| `/app/accommodation` | Facilities list (hotel/camp), search/filter, create |
| `/app/accommodation/facilities/:id` | Facility detail: structures, units, positions (beds/slots) |
| `/app/accommodation/allocation` | Season facilities / season units / season positions |
| `/app/accommodation/drafts` | Draft→confirm assignments; arrive / check-in / check-out / relocate / cancel |
| `/app/accommodation/scopes` | Facility + supervisor scopes |

Nav: `nav.accommodation` → `/app/accommodation` (permission any of accommodation.*).

Locale: `frontend/src/locales/accommodationAr.ts`.

Do not auto-check-in from a transportation screen. No fake GPS/QR offline UI in this sprint.

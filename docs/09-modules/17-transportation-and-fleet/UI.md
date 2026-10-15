# Transportation and Fleet — UI

> **Status:** Not built. Backend sprint 1 only.
> **Last updated:** 2026-10-05

The backend API (see [API.md](API.md)) is ready for a future frontend. The frontend sprint will design screens for: Transport Points, Vehicle registry, ExternalDrivers, Season fleet, Entitlements, Movements with their Runs, Run manifest and operations, timeline, and review items.

Constraints for the future UI (from approved decisions):

- Arabic-first, RTL, existing design system.
- Season selector supplies `X-Season-Id`; Closed seasons are read-only.
- Count-based capacity display (occupied / effective capacity); no seat maps.
- Entitlement mismatches and overrides visible on the manifest.
- No GPS, maps, QR, or offline features.

TBD: page layout, navigation placement, Arabic labels.

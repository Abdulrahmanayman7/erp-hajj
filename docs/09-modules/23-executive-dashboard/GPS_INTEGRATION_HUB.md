# GPS Integration Hub — Multi-Provider Architecture

> **Date:** 2026-11-16  
> **Production:** **NOT_AUTHORIZED**  
> **G4 Live vendor claims:** **BLOCKED** until Owner authorization + approved vendor verification  
> **G5 history / retention:** **BLOCKED** — no history table; last-known only

## Architecture

Tenant-configurable providers (`gps_providers`) select a connection method. Each method has an adapter implementing `GpsProviderAdapterInterface`:

| Method | Adapter | Notes |
|---|---|---|
| `webhook` | `WebhookGpsAdapter` | Push via existing HMAC `POST /api/v1/gps/ingest` |
| `rest_pull` | `RestPullGpsAdapter` | HTTPS pull + field map; SSRF-guarded |
| `traccar` | `TraccarGpsAdapter` | Documented Traccar `/api/server` + `/api/positions` |
| `mobile_driver` | `MobileDriverGpsAdapter` | Consent required; tracking disabled until authorized |
| `simulator` | `SimulatorGpsAdapter` | DEMO only; blocked in production |

All adapters emit `NormalizedLocationDto`. `ApplyNormalizedLocation` upserts **last-known only**.

Process-wide `GpsProviderInterface` remains default `NullGpsProvider`; unknown `GPS_PROVIDER` still fails closed.

## How to add a new provider type

1. Add enum case to `GpsConnectionMethod`.  
2. Implement `GpsProviderAdapterInterface`.  
3. Register in `GpsProviderAdapterRegistry`.  
4. Extend Form Request validation for settings/credentials.  
5. Add Pest coverage (RBAC, isolation, fail-closed live gate).  
6. Do **not** invent live production authorization.

## Setup guides

### Traccar

1. Create provider with method `traccar`.  
2. Set HTTPS `base_url` and encrypted credentials (`token` or email/password).  
3. Link devices using Traccar `deviceId` as `external_device_id`.  
4. Enable `GPS_LIVE_OUTBOUND_AUTHORIZED=true` only on disposable UAT.  
5. Run **Test connection** — do not claim connected unless the test succeeds.

### REST pull

1. Method `rest_pull` + HTTPS `endpoint_url`.  
2. Optional `field_map` / `items_path` / `auth_type`.  
3. Prefer `GPS_OUTBOUND_HOST_ALLOWLIST`.  
4. Sync via UI or `gps:sync-providers` schedule.

### Webhook

1. Method `webhook` + encrypted `webhook_secret` (and/or env `GPS_WEBHOOK_SECRETS`).  
2. Bind `device_id` ↔ season vehicle.  
3. Provider posts to `/api/v1/gps/ingest` with HMAC (`X-Gps-Signature`).

### Device mapping

Admin API `/api/v1/gps/device-bindings` enforces one device and one vehicle binding per tenant (no conflicts).
List responses include provider health and last-known freshness for the devices admin UI (`/app/gps-devices`).

### Interactive fleet map

- `GET /api/v1/gps/fleet-map?season_id=` requires `gps.map.view`.
- Arabic RTL UI: `/app/gps-fleet-map` (Leaflet + OpenStreetMap attribution) and executive dashboard GPS panel.
- Filters: vehicle / plate / provider / status; auto-refresh on the map page.
- Simulated markers always show a DEMO banner and never claim operational truth.

### Demo / simulator

1. Non-production only.  
2. `GPS_SIMULATOR_ENABLED=true`.  
3. Markers with `is_simulated=true` / `marker_status=simulated` show DEMO banner.  
4. Provider admin exposes DEMO controls (failure, offline devices, radius, poll).  
5. Never treat simulated coordinates as operational truth.

## Security

- Tenant scope on all hub models; cross-tenant → 404.  
- Permissions: `gps.providers.view|manage`, `gps.devices.manage`, `gps.map.view` (explicit assignment).  
- Secrets encrypted; API returns masked values only.  
- No coordinates/secrets in security audit payloads.  
- SSRF guard + optional host allowlist.  
- Live outbound fail-closed (`GpsOutboundGate`).  
- G5 history writer remains unbound.

## Browser UAT

Live attested disposable Browser UAT for the hub is recorded in
[`docs/audits/gps-hub-browser-uat/REPORT.md`](../../audits/gps-hub-browser-uat/REPORT.md)
(`erp_hajj_gps_uat`, Playwright journeys PASS on PR #66). GPS permissions remain
**explicit-assignment-only** (not auto-granted via Tenant Owner template).

## Remaining blockers

1. Named live vendor + Owner G4 authorization.  
2. Legal retention decision for G5.  
3. Production deploy authorization.  
4. Independent security Approve (separate governance track).

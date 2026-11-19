# Mobile Responsive QA Sprint — Report

> Date: 2026-11-19  
> Branch: `feature/mobile-responsive-qa`  
> Base: `origin/develop` @ `054a5e4`  
> Live target (read-only browser UAT): `https://erp-hij.esappx.com`

## Coverage

| Metric | Value |
|---|---|
| Routes discovered (Vue Router) | 99 path entries / 61 static app routes scanned |
| Static routes tested for overflow | 61 × 4 viewports |
| Blocked (permission) | `/app/executive-dashboard`, `/app/purchase-requests/approval-inbox`, `/app/purchase-approval-settings` |
| Mobile viewports | 320×640, 360×800, 375×812, 393×873, 430×932 |
| Tablet / desktop smoke | 768×1024, 1366×768 |
| Playwright mobile suite | **10/10 passed** (≈5.8m) |
| Document horizontal overflows | **0** |
| Frontend production build | **passed** |

## Fixes shipped in this PR

1. **Overlay vs bottom nav (P0)** — raise `fixed.inset-0.z-50` overlays to z-70; soft-hide bottom nav while dialogs/drawers are open (`main.css`).
2. **Organization mobile sheet (P0)** — z-70 + bottom padding includes bottom-nav height.
3. **Clipped tables (P1)** — transport / risks / season lists: `overflow-hidden` → `overflow-x-auto` so columns scroll inside the card instead of being clipped.
4. **GPS fleet map (P1)** — collapse vehicle panel by default on mobile; shorter map height on small screens.
5. **Topbar brand (P1)** — truncate long Arabic brand name on narrow widths.
6. **Copy-run drawer (P1)** — z-70 + `app-drawer-panel` for mobile bottom-sheet behavior.
7. **RTL polish (P2)** — `text-left` → `text-start` on purchase demand group details.

## Playwright additions

- `e2e/src/helpers/mobile.ts`
- `e2e/tests/mobile-responsive.spec.ts`
- `e2e` package script `test:mobile`

## Evidence

- Overflow JSON: `overflow-report.json`
- Screenshots: `evidence/<module>/.../iphone-375.png`

## Remaining limitations

- Live Hostinger still serves the previous frontend bundle until deploy; code fixes are verified by production build + static analysis; re-run `npm run test:mobile` after frontend publish.
- Parameterized detail routes were not exhaustively opened when fixture IDs were unavailable in this pass (list/create/static coverage prioritized).
- Platform admin routes require platform credentials (not in tenant owner fixture).
- Native iOS Safari / WebKit device not claimed — Chromium Playwright only.
- Purchase-request create sticky actions and purchase-requests mobile cards remain follow-up polish (P2).

## Deploy (frontend-only)

1. Merge PR to `develop` after CI green.
2. Build frontend: `cd frontend && npm ci && npm run build`
3. Publish `frontend/dist` to Hostinger per existing Hostinger frontend deploy runbook.
4. Re-run: `cd e2e && npm run test:mobile`

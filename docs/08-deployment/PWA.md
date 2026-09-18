# Progressive Web App (Install / Standalone)

> Status: Implemented (shell installability). **Web Push remains out of scope** until [CHANGE_REQUEST_PWA_WEB_PUSH.md](../00-project/CHANGE_REQUEST_PWA_WEB_PUSH.md) is approved.
> Last updated: 2026-09-18

## Architecture

Manual PWA (no `vite-plugin-pwa` / Workbox):

| Asset | Path |
|---|---|
| Manifest | `frontend/public/manifest.webmanifest` |
| Service worker | `frontend/public/sw.js` |
| Icons | `frontend/public/icons/*` |
| Registration | `frontend/src/shared/pwa/registerServiceWorker.ts` (production only) |
| Standalone helper | `frontend/src/shared/pwa/displayMode.ts` |
| Install helper | `frontend/src/shared/pwa/usePwaInstall.ts` |

## Global icon policy

- Installed app icon is always the **official global RAFEEA / رفيع** logo from `frontend/src/assets/brand/rafeea-logo.png`.
- **Never** tenant logo, org logo, or user avatar.
- Platform Admin and Tenant users install the **same** app shell/icon.
- `purpose: any` icons use a **transparent** canvas (no artificial fill added around the artwork).
- `purpose: maskable` icons use brand `#064e3b` fill with the logo inside the maskable safe zone (OS may crop edges).
- `apple-touch-icon` is opaque brand-green (iOS prefers non-transparent home-screen icons).

## Manifest

- `name`: ERP Hajj — رفيع · `short_name`: رفيع
- `lang`: `ar` · `dir`: `rtl`
- `start_url` / `scope`: `/` (not tenant-bound)
- `display`: `standalone` (+ `display_override: ["standalone"]`)
- `theme_color`: `#ffffff` (matches in-app chrome so iOS/Android do not paint a green band under the bottom nav)
- `background_color`: `#064e3b` (install splash only)
- `orientation`: `any` (phones + tablets)

## Service worker / cache

Versioned cache name: `erp-hajj-shell-v2`.

| Request | Behavior |
|---|---|
| `GET /api/*` | **Not intercepted** (always network) |
| Non-GET | Ignored |
| Navigation | Network-first (fresh `index.html` after deploy) |
| `/assets/*` (hashed Vite build) | Cache-first; old `erp-hajj-shell-*` caches deleted on activate |

Never cache auth responses, tenant JSON, or user business data.

On deploy: bump frontend assets → new hashed `/assets/*` URLs; navigations fetch fresh HTML; old shell caches purged when SW activates.

## Install behavior

- Chromium/Android: capture `beforeinstallprompt`, user-triggered install via `PwaInstallCard` (More sheet / Platform mobile).
- iOS Safari: no prompt API — Arabic hint `مشاركة ← إضافة إلى الشاشة الرئيسية` (dismissible; not shown every visit after dismiss).
- Hide install UI when `display-mode: standalone` or `appinstalled`.

## Standalone detection

`isStandaloneDisplay()` uses `(display-mode: standalone)` and iOS `navigator.standalone`.
Applies `html.is-standalone` for light CSS hooks — **no** forked auth/UI.

## Zoom policy (accessibility trade-off)

Viewport:

```html
width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover
```

Intent: reduce accidental pinch/double-tap zoom in mobile/PWA so the ERP feels app-like.

Trade-off: browser page-zoom gesture is restricted; users needing magnification should use **OS accessibility zoom**. Documented intentionally.

Additional mitigations:

- `touch-action: manipulation` on `html`
- Interactive `input`/`select`/`textarea` use **≥16px** font on viewports ≤1023px to avoid iOS focus zoom
- Desktop layouts unchanged in practice

## Safe areas

App/Platform shells already use `100dvh` + `env(safe-area-inset-*)` on topbar, bottom nav, drawers, and page padding.

## Routing

Same Vue Router + SPA fallback (`vercel.json` / Hostinger rewrite to `index.html`) for `/login`, `/setup`, `/app/*`, `/platform/*` in browser and installed PWA. No separate installed-app auth.

## Hostinger deploy notes

1. Build frontend (`npm run build`) and publish `dist` including `manifest.webmanifest`, `sw.js`, `icons/`.
2. Ensure `sw.js` and `manifest.webmanifest` are served from site root with correct MIME (`application/manifest+json` for manifest).
3. Prefer `Cache-Control: no-cache` for `sw.js` so clients pick up SW updates.
4. After deploy, open the site once in a browser so the new SW activates; installed PWAs update on next launch/navigation.

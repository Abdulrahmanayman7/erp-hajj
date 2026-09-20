# Progressive Web App (Install / Standalone)

> Status: Implemented (shell installability). **Web Push remains out of scope** until [CHANGE_REQUEST_PWA_WEB_PUSH.md](../00-project/CHANGE_REQUEST_PWA_WEB_PUSH.md) is approved.
> Last updated: 2026-09-20

## Architecture

Manual PWA (no `vite-plugin-pwa` / Workbox):

| Asset | Path |
|---|---|
| Version constant | `frontend/src/shared/pwa/pwaVersion.ts` (**single bump point**) |
| Sync script | `frontend/scripts/sync-pwa-version.mjs` (`npm run pwa:sync`) |
| Manifest | `frontend/public/manifest.webmanifest` |
| Service worker | `frontend/public/sw.js` |
| Icons | `frontend/public/icons/*` |
| Registration | `frontend/src/shared/pwa/registerServiceWorker.ts` (production only) |
| Standalone helper | `frontend/src/shared/pwa/displayMode.ts` |
| Install helper | `frontend/src/shared/pwa/usePwaInstall.ts` |

## Global icon policy

- Installed app / PWA icon source: `frontend/src/assets/brand/rafeea-app-icon.png` (global designed app icon).
- In-app UI branding (sidebar, topbar, login, etc.) continues to use `frontend/src/assets/brand/rafeea-logo.png` and is **not** replaced by the PWA app icon.
- **Never** tenant logo, org logo, or user avatar.
- Platform Admin and Tenant users install the **same** app shell/icon.
- Regenerate production icons with: `php frontend/scripts/generate-pwa-icons.php`
- `purpose: any` icons are near full-bleed from the designed app icon.
- `purpose: maskable` icons keep extra padding (~20%) so the central Rafeea emblem stays inside the Android safe zone (circle / rounded square / squircle).
- `apple-touch-icon` is opaque (iOS prefers non-transparent home-screen icons).

## Versioning strategy (current: `PWA_ASSET_VERSION = 4`)

**Chosen approach:** stable icon filenames + `?v=<PWA_ASSET_VERSION>` query on:

- HTML: manifest link, favicon, apple-touch-icon
- Manifest `icons[].src`
- Shell Cache Storage name: `erp-hajj-shell-v<PWA_ASSET_VERSION>`

**Why query strings (not renamed files):**

- One set of PNG binaries (no duplicate `icon-192-v4.png` copies).
- Standards-compliant; browsers treat `?v=` as a distinct HTTP cache key.
- Future icon updates = bump **one** constant → `npm run pwa:sync` (also runs on `pretest` / `prebuild`).

**Why not rely on SW cache bump alone:** Cache Storage only affects what the service worker intercepts. This SW never puts `/icons/*` or the manifest into Cache Storage. The **OS launcher icon** is a separate platform cache (WebAPK / home-screen bookmark) and is **not** controlled by JavaScript.

### Future update process

1. Replace/regenerate icons if needed (`php frontend/scripts/generate-pwa-icons.php`).
2. Bump `PWA_ASSET_VERSION` in `frontend/src/shared/pwa/pwaVersion.ts`.
3. `npm run pwa:sync` (automatic on test/build).
4. Build + deploy.
5. Clients fetch new `index.html` → new manifest/icon URLs → new `sw.js` (`erp-hajj-shell-vN`).

## Manifest

- `name`: ERP Hajj — رفيع · `short_name`: رفيع
- `lang`: `ar` · `dir`: `rtl`
- `start_url` / `scope`: `/` (not tenant-bound)
- `display`: `standalone` (+ `display_override: ["standalone"]`)
- `theme_color`: `#ffffff` (matches in-app chrome so iOS/Android do not paint a green band under the bottom nav)
- `background_color`: `#064e3b` (install splash only)
- `orientation`: `any` (phones + tablets)
- Icons (versioned): `/icons/icon-192.png?v=4`, `/icons/icon-512.png?v=4` (`purpose: any`); `/icons/icon-192-maskable.png?v=4`, `/icons/icon-512-maskable.png?v=4` (`purpose: maskable`)
- HTML manifest link: `/manifest.webmanifest?v=4`

## Service worker / cache

Versioned cache name: `erp-hajj-shell-v4` (tied to `PWA_ASSET_VERSION`).

Registration options: `updateViaCache: "none"`; `registration.update()` on load and when the tab becomes visible (no reload loops).

| Request | Behavior |
|---|---|
| `GET /api/*` | **Not intercepted** (always network) |
| `/manifest.webmanifest`, `/sw.js`, `/icons/*` | **Not intercepted** (never in Cache Storage) |
| Non-GET | Ignored |
| Navigation | Network-first (fresh `index.html` after deploy) |
| `/assets/*` (hashed Vite build) | Cache-first; old `erp-hajj-shell-*` caches deleted on activate |

Never cache auth responses, tenant JSON, or user business data.

## Update layers (do not confuse these)

| Layer | What updates | Guaranteed on existing install? |
|---|---|---|
| **HTTP cache** | Browser revalidates `index.html` / `sw.js` / manifest when Cache-Control says so | Improved by Hostinger/Vercel headers below |
| **Service worker** | New `sw.js` installs → old shell caches deleted → hashed assets refresh | Yes, after visit/visibility + SW activate |
| **Manifest document** | New icon URL list (`?v=N`) | Usually after fresh HTML + manifest fetch |
| **OS launcher icon** | Home-screen / WebAPK icon bitmap | **Not guaranteed** — Android Chrome and iOS often keep the icon captured at install time |

### Why a device can still show the old icon

1. The phone already installed the PWA (or Add to Home Screen) when the old icon URL/bytes were current.
2. Android WebAPK / launcher and iOS home-screen bookmarks **cache the icon at the OS level**. Changing `sw.js` cache name (`v3` → `v4`) does **not** rewrite that OS bitmap.
3. Even with new manifest icon URLs, Chromium may delay or skip launcher icon refresh for an existing install; some devices never update until uninstall + reinstall (or clear site data + re-add).
4. Aggressive HTTP caching of `manifest.webmanifest` / `/icons/*` without versioned URLs made stale bytes more likely — mitigated by `?v=` + `Cache-Control` policy below.

**Previous `erp-hajj-shell-v3` bump alone was not sufficient** to update installed OS icons. It was still useful for shell Cache Storage hygiene after the icon file change.

### Fallback when an OS icon is stuck

- **Android:** uninstall the installed PWA (or Chrome → Site settings → Clear & reset) → open the site → Install again.
- **iOS:** delete the home-screen icon → Safari → Share → Add to Home Screen again.

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

## HTTP Cache-Control (Hostinger / Vercel)

| Resource | Recommended `Cache-Control` |
|---|---|
| `index.html` | `no-cache` |
| `sw.js` | `no-cache, no-store, must-revalidate` |
| `manifest.webmanifest` | `no-cache` |
| `/assets/*` (hashed) | `public, max-age=31536000, immutable` |
| `/icons/*` | `public, max-age=31536000, immutable` (safe because HTML/manifest use `?v=` busting) |

Configured in:

- `frontend/vercel.json` (Vercel)
- `frontend/public/.htaccess` (static Apache `dist` document root — includes SPA → `index.html` fallback)
- `backend/public/.htaccess` and `deploy/hostinger/public/.htaccess` (Laravel public; FilesMatch only — **does not** replace SPA/API routing)

When co-locating SPA files in Laravel `public/`, **merge** the PWA `FilesMatch` / `SetEnvIf` blocks; never overwrite Laravel’s front-controller rewrite with the SPA-only `.htaccess`.

## Hostinger deploy notes

1. Bump `PWA_ASSET_VERSION` only when icons/manifest identity change; run `npm run build` (runs `pwa:sync`).
2. Publish `dist` including `manifest.webmanifest`, `sw.js`, `icons/`, and (if Apache static root) `.htaccess`.
3. Ensure Hostinger serves the Cache-Control headers above (`mod_headers` enabled).
4. After deploy, open the site once (or switch back to the installed PWA) so the new SW activates.
5. If a device still shows the **old launcher icon**, use the uninstall/reinstall fallback — that is an OS limitation, not a missed deploy.

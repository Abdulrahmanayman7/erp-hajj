# Mobile Patterns — Rafeeq ERP

## Header

Simplified:

`[Menu] [Brand] …… [Refresh] [Notifications]`

Refresh: tap = invalidate queries; press-and-hold 5 seconds = hard reload. No back/fullscreen as primary chrome.

## Bottom navigation

Touch targets ≥ 44×44px. Center **+** opens Quick Actions sheet.

## Pull-to-Refresh

Global on `AppLayout` / `PlatformLayout` (not per-page):

- Touch devices only; disabled on desktop (`≥1280`) and non-touch pointers.
- Starts only when the shell main scroller is at `scrollTop = 0`.
- Progressive indicator: اسحب للتحديث → اترك للتحديث → spinner.
- On release past the threshold: TanStack Query `invalidateQueries` + `refetchQueries({ type: 'active' })` — **no** full `window.location.reload()` by default.
- Suppressed while document scroll is locked (drawer/sheet), while `[aria-modal="true"]` is open, on editable fields, and inside `[data-no-pull-refresh]`.
- Pull distance uses resistance; concurrent refreshes are ignored until the active one finishes.

Header refresh button remains: tap = soft invalidate; press-and-hold 5s = hard reload.

## Bottom sheets

Prefer for: filters, sort, quick create, compact picks.

Requirements: RTL, internal scroll, `safe-area-inset-bottom`, focus trap / Escape, body scroll lock (`useBodyScrollLock`).

Reuse `AppMobileFilters` for list filter sheets.

## Lists

Transform entity tables into tappable cards/rows:

- Primary title + status badge
- Secondary meta (unit, role, dates)
- Entire row navigates to details

## Forms

Single column; sticky footer actions with safe-area padding (`.app-sticky-form-actions`).

Create/edit **drawers** on `<md` are bottom sheets (`max-height` ~92dvh, rounded top, inner scroll). On tablet they are inset side panels, not full-bleed pages. Overlay z-index sits above the bottom nav.

## Loading / empty / error

- Skeletons matching layout (prefer over full-page spinner after first paint)
- Empty + permission-aware CTA
- Distinguish initial error vs background refetch toast

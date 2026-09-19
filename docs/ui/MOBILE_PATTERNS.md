# Mobile Patterns — Rafeeq ERP

## Header

Simplified:

`[Menu] [Brand] …… [Refresh] [Notifications]`

Refresh: tap = invalidate queries; press-and-hold 5 seconds = hard reload. No back/fullscreen as primary chrome.

## Bottom navigation

Touch targets ≥ 44×44px. Center **+** opens Quick Actions sheet.

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

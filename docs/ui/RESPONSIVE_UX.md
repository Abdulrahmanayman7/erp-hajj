# Responsive UX — Rafeeq ERP (رفيع)

> Source of truth for adaptive UX across Desktop / Tablet / Mobile.
> Last updated: 2026-09-18
> Related: [DESIGN_GUIDELINES.md](../05-ui-ux/DESIGN_GUIDELINES.md) · [NAVIGATION.md](NAVIGATION.md) · [MOBILE_PATTERNS.md](MOBILE_PATTERNS.md) · [TABLET_PATTERNS.md](TABLET_PATTERNS.md) · [DESIGN_TOKENS.md](DESIGN_TOKENS.md)

## Principles

1. **One app, three presentations** — same routes, permissions, API, and business logic.
2. **Width-based, never user-agent** — CSS/Tailwind + one `useBreakpoint()` when JS is required.
3. **No device-specific page copies** — no `MobileTasksPage` / `TabletTasksPage`.
4. **Desktop stays powerful** — do not regress ≥1280px workflows.
5. **Arabic-first RTL** — logical CSS (`inline-start/end`), drawers/sheets from the right where appropriate.
6. **Preserve PWA / safe-area** — see `docs/08-deployment/PWA.md`.

## Breakpoint contract

| Name | Width | Shell |
|---|---|---|
| Small mobile | &lt; 640px | Mobile header + bottom nav |
| Large mobile | 640–767px | Same mobile shell |
| Tablet portrait | 768–1023px | **Navigation rail** (~76px) + compact header |
| Tablet / small laptop | 1024–1279px | Navigation rail + overlay for full nav |
| Desktop | ≥ 1280px (`xl`) | Full RTL sidebar (existing) |
| Large desktop | ≥ 1536px | Same desktop + denser content grids |

**Tailwind mapping (project convention):**

- Mobile chrome: default / `max-md:`
- Tablet rail: `md:` … `max-xl:`
- Full sidebar: `xl:`
- Dense tables (entity lists): typically `lg:` table / below = cards (Tasks already uses this; keep unless a page needs `xl`)

Do **not** sprinkle `window.innerWidth`. Use CSS first; JS via `useBreakpoint()` only.

## Shell summary

```
AppLayout (tenant)
├── xl+: AppSidebar (full)
├── md–xl: AppNavigationRail + AppNavOverlay (on demand)
├── AppTopbar (mode-aware: mobile simplified / tablet compact / desktop full)
├── main.app-shell-main (owns vertical scroll)
├── xl+: AppFooter
└── max-md: AppBottomNav (+ QuickActionSheet, More sheet)
```

Platform shell (`PlatformLayout`) stays separate but shares tokens/safe-area; it does not use tenant bottom nav.

## Navigation density

| Surface | Behavior |
|---|---|
| Desktop sidebar | Existing grouped hierarchy + collapse |
| Tablet rail | Icons for primary destinations + More → overlay from **inline-end (right in RTL)** |
| Mobile bottom | الرئيسية · المهام · **+** · الإشعارات · المزيد |
| Mobile More | Sectioned sheet (not a shrunk desktop sidebar) |
| Global + | Permission-gated quick creates (task/meeting/decision/employee/document) |

## Content patterns

- **Dashboard:** calm enterprise greeting (no photo hero); responsive card grids.
- **Entity lists:** desktop table OK; mobile/tablet → `EntityListItem` / cards; avoid horizontal page scroll of tables.
- **Filters:** desktop inline; mobile → bottom sheet with count + apply/reset (`AppMobileFilters`).
- **Forms:** multi-column desktop; single column mobile; sticky save respects `safe-area-inset-bottom`.

## Quality bar

Desktop = information-dense ERP.  
Tablet = operational workspace with compact chrome.  
Mobile = focused, touch-first, action-oriented.

All share identity: deep Rafeeq green, white/mint surfaces, restrained semantic color.

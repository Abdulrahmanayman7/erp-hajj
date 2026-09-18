# Design Tokens — Rafeeq ERP

Implemented in `frontend/src/shared/styles/main.css` (`@theme` + semantic `:root` variables).

## Color

| Token | Role |
|---|---|
| `--app-bg` / `brand-bg` | Subtle mint-neutral application background (`#f3f6f4`) |
| `--surface` / `brand-surface` | Elevated white surfaces |
| `--surface-muted` / `--surface-subtle` | Nested / quiet fills |
| `--border` / `--border-soft` | Soft neutral borders (never heavy gray) |
| `--primary` / `--primary-hover` / `--primary-soft` | Rafeeq green identity |
| `--success*` `--warning*` `--danger*` `--info*` | Semantic accents only |

Green is accent/identity — not the entire UI.

## Surfaces

| Level | Use |
|---|---|
| 0 | Application background |
| 1 | Cards / page surfaces (`app-surface-flat`) |
| 2 | Sheets, menus, overlays (`--shadow-overlay`) |

Prefer whitespace over nested bordered cards.

## Radius

| Token | Value |
|---|---|
| `--radius-sm` | 8px |
| `--radius-control` | 10px |
| `--radius-card` | 14px |
| `--radius-card-lg` | 16px |
| `--radius-sheet` | 20px |

## Shadows

| Token | Use |
|---|---|
| `--shadow-xs` | Optional card whisper |
| `--shadow-sm` | Soft lift |
| `--shadow-overlay` | Sheets / dropdowns |

Normal cards stay almost flat (border, little or no shadow).

## Typography

| Class | Weight | Approx size |
|---|---|---|
| `.app-type-page` | 700 | 22–26px |
| `.app-type-section` | 600 | 16–17px |
| `.app-type-card` | 600 | 15px |
| `.app-type-kpi` | 700 | 26–30px |
| `.app-type-body` | 400 | 14px |
| `.app-type-secondary` | 400 | 13px |
| `.app-type-caption` | 500 | 12px |

Arabic line-height: `--leading-arabic` ≈ 1.65.

## Spacing / shell

- `--app-page-pad-x/y`: 16 → 20 → 28 (mobile → tablet → desktop)
- `--app-section-gap`: 20 → 24 → 28
- `--app-nav-rail-width`: 96px
- `--app-bottom-nav-height`: 60px

## Shared primitives

- `.app-input` / `.app-input--search`
- `.app-btn-primary` / `.app-btn-ghost`
- `.app-segmented` / `.app-segmented__item`
- `.app-status-success`
- `.app-kpi-icon--*`

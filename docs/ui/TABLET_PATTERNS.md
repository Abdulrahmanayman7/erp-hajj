# Tablet Patterns — Rafeeq ERP

## Navigation rail

- Width ~72–80px
- Icon + optional tiny label or tooltip
- Active state (brand soft fill)
- Does **not** permanently consume 250–300px

## Overlay navigation

When More / expanded nav is needed:

- Panel from **inline-end** (right in RTL)
- Overlays content; main width stays stable
- Same grouped items as desktop sidebar (from `useAppNavigation`)

## Layout density

- Portrait dashboards: ~2 columns
- Landscape / 1024+: 2–3 columns when readable
- No horizontal page scroll
- Tables may remain at `lg+`; between 768–1023 prefer cards if columns overflow

# S08 — UI Design Proposal

> **Status:** `PROPOSED` · Coding **NOT AUTHORIZED**  
> Shell: existing brand RTL patterns (`AppPageHeader`, `AppMobileFilters`, brand tokens).

## Pages (staff web only)

| Route (proposed) | Permission | Purpose |
|---|---|---|
| `/app/season-templates` | `season_templates.view` | Template library list |
| `/app/season-templates/:id` | `season_templates.view` | Template detail / manage |
| `/app/seasons/:id/safe-copy` | `season_copy.preview` | Wizard on **draft** target season |
| `/app/season-copy/runs` | `season_copy.history` | Execution history |

Nav entries gated like S07 (`useAppNavigation.ts` pattern). No entry for users without view/preview.

## Safe-copy wizard steps

1. Select mode: from season | from template.
2. Select source (same tenant) + confirm target draft season.
3. Select entity kinds (only APPROVED kinds shown).
4. Configure date mapping (show explanation).
5. Generate preview → table of adds/changes/conflicts/exclusions.
6. Resolve conflicts.
7. Execute (requires `season_copy.execute`) → success/failure summary.

Stale preview → blocking banner; force regenerate.

## Accessibility / RTL

Arabic-first copy; `text-start`; no raw English status enums in UI (i18n keys).

# System Settings — UI

> **Status:** Implemented (Sprint 020)
> **Last updated:** 2026-08-16

## Route

| Path | Name | Permission |
|---|---|---|
| `/app/settings` | الإعدادات | `tenant_settings.view` (route meta); mutations need `tenant_settings.update` |

Single canonical page — no scattered settings screens.

## Sidebar

- Group: **System** (النظام) — alongside Users / Roles / Audit.
- Label: **الإعدادات**.
- Visible iff `can('tenant_settings.view')`.
- Icon: suggest `Settings` (lucide) — match existing sidebar patterns.

## Page structure

One page, two sections (no unrelated tabs):

1. **معلومات المنشأة** — name + contact fields, with a responsive two-column form where suitable.
2. **المنطقة واللغة** — searchable timezone select with a human-friendly display label and IANA ID hint; locale shown as a read-only setting (`العربية` / `ar`).

No Operations section until tenant-configurable thresholds are approved.

## Save UX

- Explicit **حفظ** button.
- Dirty-state: enable Save only when dirty; disable while mutation pending.
- Success toast (Arabic).
- Server 422: keep dirty form; map field errors.
- **No** auto-save on blur.
- Route navigation and browser unload confirm before discarding dirty edits.
- Show an inline soft warning only when the timezone differs from the loaded/saved value.
- View-only users see readable values and an explanatory banner; they do not see editable-looking disabled controls or Save.

## Timezone control

- Searchable select/combobox of IANA zones (**curated PHP-compatible catalog** on the frontend, verified against `timezone_identifiers_list()`). Full browser `Intl` lists are intentionally **not** used as the offer list because they can include identifiers PHP rejects.
- Persist canonical id (`Asia/Riyadh`), never `+03:00`.
- Friendly Arabic/English labels optional; value is IANA.
- If the tenant already stores a valid IANA id outside the curated offer list, the UI still includes that value so it remains selectable until changed.

## View-only mode

If `view` without `update`:

- Render values as read-only text (not merely disabled inputs that still look editable).
- Hide Save.
- Backend still enforces `tenant_settings.update` on PATCH.

## States

| State | UX |
|---|---|
| Loading | Header/card-shaped skeletons without an empty form flash |
| GET error | Arabic error + **إعادة المحاولة** |
| Empty | N/A — always show effective defaults |
| Saving | Button loading; prevent double submit |

## Responsive / RTL

- Desktop: single-column panels, comfortable max width.
- Mobile: stacked fields; no wide tables.
- Full RTL.

## Audit panel (optional)

- Optional “سجل التغييرات” using Audit list filtered by event `TENANT_SETTINGS_UPDATED` / entity tenant — **nice-to-have**; not blocking if Audit entity linking for Tenant is awkward. Prefer link to `/app/audit?event=TENANT_SETTINGS_UPDATED` if filters allow; otherwise omit in v1 UI.

## Module folder (planned)

```text
frontend/src/modules/settings/
  api/
  queries/
  mutations/
  types/
  validation/
  pages/SettingsPage.vue
  components/…
```

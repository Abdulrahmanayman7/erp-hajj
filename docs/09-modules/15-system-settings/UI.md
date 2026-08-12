# System Settings — UI

> **Status:** Specified (Sprint 020) — implementation pending  
> **Last updated:** 2026-08-12

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

1. **عام** — name + contact fields  
2. **المنطقة والوقت** — timezone select; locale shown read-only (`العربية` / `ar`)

No Operations section until tenant-configurable thresholds are approved.

## Save UX

- Explicit **حفظ** button.
- Dirty-state: enable Save only when dirty; disable while mutation pending.
- Success toast (Arabic).
- Server 422: keep dirty form; map field errors.
- **No** auto-save on blur.
- Confirm dialog **not** required for MVP (low risk vs destroy actions); optional soft warning if timezone changes (“يؤثر على التواريخ والتنبيهات”).

## Timezone control

- Searchable select/combobox of IANA zones (curated common list + full IANA search acceptable).
- Persist canonical id (`Asia/Riyadh`), never `+03:00`.
- Friendly Arabic/English labels optional; value is IANA.

## View-only mode

If `view` without `update`:

- Render values as read-only text (not merely disabled inputs that still look editable).
- Hide Save.
- Backend still enforces `tenant_settings.update` on PATCH.

## States

| State | UX |
|---|---|
| Loading | Skeleton / spinner on page |
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

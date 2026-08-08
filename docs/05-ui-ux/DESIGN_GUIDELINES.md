# Design Guidelines

> **Status:** Approved direction; detailed tokens TBD
> **Last updated:** 2026-08-08

## Purpose

Define the UI/UX rules for the web platform. One design language for all modules — do not create a different design language per module.

## Direction (decided)

- **Arabic first, RTL first** — the layout is designed RTL-first, not LTR-flipped.
- **Enterprise, clean, professional, accessible.**
- **Responsive, desktop-first.**
- **Light theme initially.**
- **Deep green primary color**; **gold as a restrained accent**; white and neutral backgrounds.
- **Sidebar on the right.**
- Styling with **Tailwind CSS**.

## RTL Rules

- `dir="rtl"` and `lang="ar"` at the document root.
- Use logical CSS properties/utilities (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`) — never physical left/right utilities for layout.
- Direction-sensitive icons (arrows, chevrons, steppers) mirror correctly in RTL.
- Numbers, dates, and mixed Arabic/Latin content render legibly (bidi handled deliberately).

## Shared Components (build once in `shared/components/`)

App Shell · Right Sidebar · Header · Breadcrumbs · Page Header · KPI Card · Data Table · Filters Bar · Search Input · Status Badge · Empty State · Loading State · Error State · Form Field · Date Picker · User Selector · Department Selector · Permission Guard · Confirmation Dialog · Drawer · Modal · File Upload · Timeline · Audit History Panel

Rules:

- Generic components live in `shared/`; module-specific components stay in their module.
- Every list page uses Data Table + Filters Bar + pagination; every destructive action uses Confirmation Dialog.
- Status Badge colors are consistent across modules (same status semantics → same color).
- Permission Guard hides/disables unauthorized UI — **UX only**; backend authorization remains mandatory.
- Prefer **Drawer** for straightforward create/edit forms (e.g. user account); use full pages for dense matrices (e.g. role permission matrix). See [02-users-and-authorization/UI.md](../09-modules/02-users-and-authorization/UI.md).
- Authenticated but unauthorized routes: dedicated **403 / access denied** page — do not send users back to login.

## Accessibility

- Keyboard navigable forms and dialogs; visible focus states; labels on all inputs.
- Color contrast meeting WCAG AA as a baseline (formal target TBD).

## Language

- UI wording follows [GLOSSARY.md](../00-project/GLOSSARY.md) Arabic terms.

## TBD

- Arabic typeface and typography scale: TBD.
- Exact color token values (deep green / gold palette): TBD.
- Component library build-vs-adopt on top of Tailwind: TBD.
- Secondary language (English LTR) support level: TBD.
- Dark theme: not planned for MVP; light theme initially.

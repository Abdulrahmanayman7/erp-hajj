# Out of Scope

> **Status:** Approved
> **Last updated:** 2026-10-04

## Purpose

List what is explicitly NOT part of the current MVP and NOT part of approved Phase 2. Those items stay future scope. Do not implement, partially build, or design for them.

Approved Phase 2 (documentation only until an implementation sprint) is **not** in the lists below: Season and Pilgrim foundation, Transportation and fleet, Accommodation and camps. See [CHANGE_REQUEST_PHASE2_FOUNDATION.md](CHANGE_REQUEST_PHASE2_FOUNDATION.md) and [ADR-0017](../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md). That approval does **not** rewrite the original 21-module MVP.

## Future Scope (not in the MVP and not in approved Phase 2)

### Mobile and external users

- Android application
- iOS application
- Pilgrim application (تطبيق الحجاج) — the Phase 2 pilgrim **records** used by staff workflows are in scope; a pilgrim-facing app is not

### Operations domains

- GPS bus tracking (not part of the approved Transportation boundaries transcribed in-repo)
- Procurement and suppliers (automatic supplier purchasing included)
- Financial management / accounting
- Emergency and crisis management
- Full Command and Control Center

### Intelligence and integrations

- Artificial intelligence
- Predictive analytics
- Advanced KPI engine
- Ministry of Hajj integrations
- Government integrations
- Smart GPS wristbands
- Health monitoring

### Platform capabilities

- Full offline mode (a later Accommodation slice for selected field operations is documented in [18-accommodation-and-camps](../09-modules/18-accommodation-and-camps/); it is not a platform-wide offline mode and is not implemented)
- Full dynamic form builder
- Full dynamic module builder
- Advanced project management
- Complaints and suggestions (الشكاوى والاقتراحات)
- Knowledge management
- Policies and procedures module
- Full seasonal cloning automation (unless approved later)

## Rules

- Any item above requires an approved **Change Request** before any design or code work.
- Never describe these items as part of the current MVP or as part of approved Phase 2.
- The API should not be shaped speculatively around these features.
- Phase 2 module docs may not invent rules for items that remain in this file (GPS, finance, pilgrim app, extra notification channels, government integrations, AI).

# Accommodation and Camps — API

> **Status:** Not implemented. Groups are purposes, not a frozen URL list.
> **Last updated:** 2026-10-04

Base `/api/v1`. [API_STANDARDS.md](../../04-api/API_STANDARDS.md). No `tenant_id` input. 401 / 403 / 404 cross-tenant / 422.

## Groups (purpose only)

| Group | Purpose |
|---|---|
| Places and positions | Capacity-based and position-based inventory of space |
| Assignments | Time-bounded draft/confirmed assignment; actions for arrive, check-in, check-out, relocate, extend, cancel-before-check-in |
| Availability and capacity | Read whether a new assignment would exceed capacity or overlap |
| Demand and allocation | Planning, queue, auto/incremental allocation — **payload TBD** |
| Current accommodation | Read model for a SeasonPilgrim (planned vs operational) |
| Operational timeline | Append-only operational events (`occurred_at`, `recorded_at`) |
| Notification configuration | Business rules for which events notify — not a new delivery API |
| Offline sync | **Later slice.** Accept queue items with idempotency; return conflicts; do not apply last-write-wins |
| QR | **TBD** after payload/security decision |

Exact paths and bodies are **TBD**.

## Hard rules for any future handler

- Must not set Pilgrim current-room fields.
- Must not set `SeasonPilgrim.status`.
- Must not mark check-in because a transportation arrival was saved.
- A combined relocate + check-in request, if one endpoint exists, still persists two timeline events.
- Create-assignment rejects over capacity and overlap.
- Read model omits device-local pending operations that the server has not accepted.

Stable error codes **TBD** for: overlap, over capacity, illegal operational transition, cross-season SeasonPilgrim, sync conflict.

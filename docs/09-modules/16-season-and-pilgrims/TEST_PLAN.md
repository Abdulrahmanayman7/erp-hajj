# Season and Pilgrim Foundation — Test Plan

> **Status:** Backend tests implemented in `backend/tests/Feature/SeasonPilgrims/` (Season, Pilgrim, SeasonPilgrim, Program, Family group, Pilgrim group, Season Context incl. jobs, cross-tenant suite, schema). Frontend (Vitest) pending with the UI.
> **Last updated:** 2026-10-05

Binding: [TESTING_STRATEGY.md](../../07-testing/TESTING_STRATEGY.md). Pest for API, Vitest for UI. Factories and two-tenant fixtures. Do not weaken cross-tenant tests.

## When implementation starts

| Area | Required cases |
|---|---|
| Season lifecycle | Draft → Active → Closed success; invalid transition 422; second Active Season rejected |
| Closed history | Starting another season does not delete Closed seasons |
| SeasonPilgrim uniqueness | Second participation for same pilgrim + season rejected |
| Snapshot | **TBD** once the field list exists: current Pilgrim edits do not rewrite frozen snapshot fields |
| Delete vs cancel | Unreferenced mistaken row deletable with permission; referenced row 422 and cancel stores reason, actor, timestamp |
| Cancel side effects | Pilgrim row remains; transportation and accommodation history remains (fixtures **TBD** when those modules exist) |
| Family Group | Optional; no forced singleton group; at most one group per SeasonPilgrim per season; move is audited; removing a member does not delete the group |
| Status purity | API rejects attempts to store accommodation/transport/finance/visa states in `SeasonPilgrim.status` |
| Auth | 401, 403 per permission, 404 cross-tenant on every endpoint group |
| Audit | Critical changes write `audit_logs` with correlation id; no secrets |
| UI | Permission visibility; loading / empty / error; form validation once fields exist |

## Not yet covered

Vitest/UI cases (no frontend yet). Transportation/Accommodation history-retention fixtures (those modules do not exist).

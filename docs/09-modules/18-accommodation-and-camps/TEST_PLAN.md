# Accommodation and Camps — Test Plan

> **Status:** Planned. No tests in this sprint.
> **Last updated:** 2026-10-04

Binding: [TESTING_STRATEGY.md](../../07-testing/TESTING_STRATEGY.md).

## Required when the corresponding behavior is implemented

| Case | Expectation |
|---|---|
| Cross-tenant id | 404 |
| 401 / 403 | Per endpoint and permission |
| Overlapping assignment on one place or position | 422 |
| Sequential assignments for one SeasonPilgrim | Allowed (example: hotel, then camp bed, then another hotel) |
| Capacity-based place | Assignment without a position row succeeds while capacity remains |
| Position-based place | Assignment without a position rejected |
| New assignment over actual capacity | 422 |
| Capacity lowered below existing occupancy | Existing rows remain; a further assignment that exceeds capacity is rejected |
| Transport arrival | Does not create check-in |
| Relocate + check-in together | Two timeline events |
| `occurred_at` vs `recorded_at` | Both stored; validation uses occurrence time where specified |
| Timeline | Historical event not silently rewritten |
| Read model | Reports assigned / arrived / checked-in / checked-out; no write to Pilgrim current-room columns |
| Pending offline item | Absent from global read model until server accept |
| Sync conflict | Not last-write-wins for occupancy |
| Family audience | Uses foundation Family Group id; this module does not create the group |
| SeasonPilgrim status | Unchanged by accommodation actions |
| Audit | Critical mutations produce audit rows; timeline is separate |
| UI | Permission visibility; loading / empty / error |

Offline and QR cases are for their later slices only.

No Pest/Vitest files in the documentation sprint.

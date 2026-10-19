# Accommodation Sprint 2 — Gate D Verification (Confirmation Frontend)

> **Date:** 2026-10-19  
> **Scope:** Gate D only — Vue confirmation UI for individual + atomic family confirm, permission/season guards, D7 Arabic errors, Vitest/type-check.  
> **Out of scope:** Gate E packaging, backend changes, check-in/out, arrival, relocate, extend, staff cancel, role-template grants, occupancy dashboards, ADR acceptance.  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Verdict:** **PASS**

Gate E was **not** started.

---

## 1. Files created / modified

### Created

| File | Role |
|---|---|
| `frontend/src/modules/accommodation/components/BookingStatusBadge.vue` | Draft vs Confirmed badges |
| `frontend/src/modules/accommodation/mutations/useAccommodationConfirmMutations.test.ts` | Invalidate-on-success / no invalidate-on-failure |
| `docs/reviews/ACCOMMODATION_SPRINT2_GATE_D_VERIFICATION.md` | This report |

### Modified

| File | Change |
|---|---|
| `frontend/src/modules/accommodation/api/accommodationApi.ts` | `confirmAssignment`, `confirmFamilyAssignments` |
| `frontend/src/modules/accommodation/api/accommodationApi.test.ts` | Confirm request/payload/error tests |
| `frontend/src/modules/accommodation/api/seasonLookupsApi.ts` | Family-group lookup (existing Foundation API) |
| `frontend/src/modules/accommodation/mutations/useAccommodationMutations.ts` | Confirm mutations + query invalidation |
| `frontend/src/modules/accommodation/pages/DraftAssignmentsPage.vue` | Individual + family confirm UX |
| `frontend/src/modules/accommodation/types/accommodation.ts` | `BookingStatus`, family payload types, `confirm` in ANY permissions |
| `frontend/src/modules/accommodation/stores/seasonContextStore.ts` | `canConfirm` (Active only) |
| `frontend/src/modules/accommodation/stores/seasonContextStore.test.ts` | Active vs Draft/Closed confirm gate |
| `frontend/src/modules/accommodation/validation/accommodationValidation.ts` | D7 maps, `canShowConfirmAction`, `buildConfirmFamilyPayload` |
| `frontend/src/modules/accommodation/validation/accommodationValidation.test.ts` | Visibility + payload + D7 tests |
| `frontend/src/modules/accommodation/components/AccommodationSubnav.vue` | Confirm permission can open assignments |
| `frontend/src/locales/accommodationAr.ts` | Confirm copy + D7 Arabic messages |
| `frontend/src/app/router/index.ts` | `accommodation.confirm` on overview/drafts |
| `frontend/src/shared/composables/useAppNavigation.ts` | Nav visible with confirm |
| `docs/09-modules/18-accommodation-and-camps/UI.md` | Gate D implemented note |

No backend files changed.

---

## 2. Screens and components

| Surface | Change |
|---|---|
| Assignments page (`/app/accommodation/drafts`) | Confirm button on eligible drafts; family confirm drawer; Draft/Confirmed badges; reserved capacity label; edit/delete only for drafts |
| Season bar | Existing; confirm disabled when not Active via `canConfirm` |
| Subnav / sidebar / router | `accommodation.confirm` included in visibility |

Family drawer: select Foundation family group (remote select if `season_family_groups.view`, else numeric id) + checkboxes for draft assignment ids on the current page. Explicit confirm dialog explains all-or-nothing. No client-side partial confirm.

---

## 3. API calls integrated

| Call | Body |
|---|---|
| `POST /api/v1/accommodation/assignments/{id}/confirm` | `{}` (no `booking_status`) |
| `POST /api/v1/accommodation/assignments/confirm-family` | `{ season_family_group_id, assignment_ids }` |

Both send existing `X-Season-Id` via season store headers. Success invalidates `accommodationQueryKey` (lists refresh without full page reload). Failure does not invalidate and shows toast/form error — no success toast.

---

## 4. Permission and season guards

| Guard | Behavior |
|---|---|
| `accommodation.confirm` | Confirm actions wrapped in `PermissionGuard` / `canShowConfirmAction` |
| Active season | `canConfirm` true only when `status === 'active'`; Draft/Closed show notice and disable actions |
| Facility scope | Not invented client-side; backend re-checks; 403 mapped to Arabic forbidden |
| Role templates | Untouched |

---

## 5. Error localization (D7)

| Code | i18n key |
|---|---|
| `ACCOMMODATION_PILGRIM_OVERLAP` | `accommodation.errors.pilgrimOverlap` |
| `ACCOMMODATION_CAPACITY_CONFLICT` | `accommodation.errors.capacityConflict` |
| `ACCOMMODATION_FAMILY_EXCLUSIVITY` | `accommodation.errors.familyExclusivity` |
| `SEASON_NOT_ACTIVE` | `accommodation.errors.seasonNotActive` |

Also preserves 403/404/422/network/generic handling via `useAccommodationErrors`.

---

## 6. Tests executed and results

```text
npm test -- src/modules/accommodation
npm run type-check
```

| Suite | Result |
|---|---|
| Accommodation Vitest (4 files, 17 tests) | **PASS** |
| `vue-tsc -b` | **PASS** |

Coverage includes: confirm permission/season visibility, family payload shape, individual/family API requests, D7 localization mapping, invalidate-on-success, no invalidate on failure (no partial success), Active vs Draft/Closed `canConfirm`.

Pending duplicate prevention: confirm buttons disabled while `confirmMutation` / `confirmFamilyMutation` `isPending`.

---

## 7. Build result

| Check | Result |
|---|---|
| C: free space at run time | ~58 MB (`61034496` bytes) |
| Production build (`npm run build`) | **NOT RUN** (insufficient disk; threshold &lt; 2 GB) |

Build is **not** claimed as PASS.

---

## 8. Remaining UX / verification gaps

| Gap | Notes |
|---|---|
| Family member filtering | UI does not auto-resolve family membership; user selects drafts + group; backend validates |
| Pagination | Family checkbox list is current page drafts only |
| Confirmed cancel / check-in UI | Explicitly out of Gate D |
| E2E browser proof | Not in scope; Vitest + type-check only |
| Production build artifact | Blocked by local disk; re-run when space available |
| Gate E | Not started |

---

## 9. Completion report

| Item | Detail |
|---|---|
| Files changed | See §1 |
| Migrations added | None |
| Endpoints added | None (consumed Gate C) |
| Permissions added | Frontend vocabulary only (`accommodation.confirm`) |
| Audit events added | None |
| Tests added | Confirm API/mutation/validation/season tests |
| Documentation updated | This verification + Module 18 `UI.md` |
| Assumptions | Family confirm uses frozen Gate C body; no capacity math in FE |
| Remaining risks | See §8 |

---

## Final verdict

**PASS**

Do **not** start Gate E automatically.

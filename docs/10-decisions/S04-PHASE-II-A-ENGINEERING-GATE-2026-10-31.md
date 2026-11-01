# S04 Phase II-A — Engineering Pre-Implementation Gate

> **Status:** `II-A ENGINEERING PRE-IMPLEMENTATION GATE — COMPLETE`  
> **Date:** 2026-10-31  
> **Coding authorization:** **NOT GRANTED**  
> **Design approval:** [S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md](S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md) (C1–C6)  
> **Clarifications:** [S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md](S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md) (II-D1–II-D4)  
> **Contracts:** [API.md §12](../09-modules/19-suppliers-and-procurement/API.md) · [DATA_MODEL.md](../09-modules/19-suppliers-and-procurement/DATA_MODEL.md) · [PHASE_II_A_ELIGIBILITY_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_II_A_ELIGIBILITY_DESIGN.md)

## 1. Gate verdict

**COMPLETE** — required II-A engineering questions for eligibility/documents/activation (first activation path) are resolved in documentation. Implementation remains unauthorized.

## 2. Resolved engineering items

| Topic | Resolution |
|---|---|
| API contracts | Exact paths, bodies, Resources, HTTP/codes, audit events in API.md §12 (**C5**) |
| DB constraints | Version uniqueness; generated-column NULL-safe applicability unique; immutability via app; supersession FKs; indexes; transaction boundaries in DATA_MODEL |
| Expiration | Tenant-timezone inclusive DATE; live recheck beats stale status; scheduled job supplementary; no auto-suspend; keep `SUPPLIER_NOT_ELIGIBLE` |
| Activation edge | **`under_review` → `active`** only for II-A; eligibility + lockForUpdate + `activated_at`; no PATCH bypass |
| Security | C1–C6 coverage recorded; cross-tenant 404; sensitive approve; SoD; Documents immutability; host-delete; fail-closed; no banking for activation |

## 3. Recorded Owner follow-up (non-blocking for II-A first activation)

| Item | Notes |
|---|---|
| Reactivation from `temporarily_suspended` | Phase I graph has **no** edge to `active` or `under_review` from `temporarily_suspended` (only → `blocked` / `archived`). Direct `temporarily_suspended` → `active` (eligibility-gated) is **not** authorized without a new Owner decision. Do **not** invent this edge in II-A. |

## 4. Governance status (unchanged classes)

| Item | Status |
|---|---|
| Phase I | **COMPLETE — OWNER ACCEPTED** |
| II-A design | **APPROVED** |
| II-A engineering pre-impl gate | **COMPLETE** |
| Phase II implementation | **NOT_STARTED** |
| II-A coding | **NOT AUTHORIZED** |
| Overall S04 | **IN PROGRESS** |
| S05 | **NOT_STARTED** |
| ADR-0019 | **PROPOSED** |
| ADR-0022 / 0023 | **APPROVED** |

## 5. Next authorization gate

Separate Owner act: **II-A coding authorization** (branch/DB bounds; no II-B/II-C/S05; no push/merge without further auth).

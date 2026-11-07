# S06 Part 4 — Implementation Evidence

> **Status:** `IMPLEMENTED — PENDING OWNER REVIEW`
> **Date:** 2026-11-07
> **Worktree:** `D:\erp-hajj-s06-season-projects` @ `feature/s06-season-projects`
> **Does not:** claim Owner acceptance, Part 5, commit/push/merge/deploy

## Owner decisions incorporated

| Decision | Implementation |
|---|---|
| **E-Q04 = A** | Same-tenant PO link allowed regardless of season match |
| **E-Q07 = A** | Actual = Σ (`posted` GRN line qty × PO line `unit_price`); draft GRNs excluded; S05 has no `accepted` status — **posted ≡ accepted** |
| **E-Q08 = A** | Outstanding = Σ ((ordered − received) × unit_price) for non-cancelled / non-closed; closed → outstanding 0; cancelled → 0; remaining = budget − outstanding − actual |
| **E-Q09 = A** | Currency mismatch → `422 SEASON_PROJECT_PO_CURRENCY_MISMATCH`; no FX |
| **E-Q13 = A** | Independent `manage_budget` / `link_po` (not on operational templates) |
| **F-Q01 = A** | Posted GRN × PO `unit_price` valuation |
| **F-Q02 = A** | Closed PO outstanding = 0 |
| **F-Q03 = A** | `manage_budget`/`link_po` **removed** from `sensitiveExplicitOnly` so `tenant_owner` template includes them; complete/cancel remain sensitive; sensitive ops still audited |

## Financial contract matrix (summary)

| Source | Use | Classification |
|---|---|---|
| `purchase_orders.currency` vs `season_projects.currency_code` | E-Q09 gate | **SUPPORTED** |
| PO statuses issued/partially_received/received/closed | linkable | **SUPPORTED** |
| PO draft/cancelled | not linkable | **SUPPORTED** |
| `goods_receipts.status=posted` | only spend source | **SUPPORTED** (maps Owner “accepted”) |
| GRN line money columns | none | valuation via PO `unit_price` **SUPPORTED as S05 join** |
| GRN cancel/reverse | does not exist | N/A |
| Invoices | out of S05 | not used |
| Link `commitment_amount`/`actual_amount` columns | left null; live calc authoritative | **SUPPORTED** |

## Valuation basis (documented)

`posted_grn_qty_x_po_unit_price` — returned in budget payload.
If a PO/GRN line lacks `unit_price`, financials return `available=false` + stable `unavailable_reason` (not fabricated zeros).

## APIs

| Method | Path | Permission |
|---|---|---|
| GET | `/season-projects/{id}/budget` | `season_projects.view` |
| PATCH | `/season-projects/{id}/budget` | `season_projects.manage_budget` |
| POST | `/season-projects/{id}/budget/po-links` | `link_po` + `purchase_orders.view` |
| DELETE | `/season-projects/{id}/budget/po-links/{id}` | `link_po` |

Unlink removes association only; PO/GRN preserved. Blocked on terminal project status.

## Tests

| Suite | Result |
|---|---|
| `tests/Feature/SeasonProjects` + `TaskTest` | **55 passed**, **1 skipped**, **0 failed** (sqlite `:memory:`) |
| MySQL 8 | **NOT RUN / DEFERRED** |

## Blockers / Owner follow-ups (non-blocking for delivered scope)

1. Confirm permanently that S05 **`posted`** is the sole synonym for Owner “accepted GRN”.
2. Confirm closed-PO outstanding = 0 (remainder released) is desired product behavior.
3. Soft-delete restore / E-Q02 reopen remain unresolved.
4. Whether tenant_owner should receive `manage_budget`/`link_po` by default (currently explicit-only).

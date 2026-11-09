# S09-D14 — Precision & UAT Matrix

> **Status:** `OWNER_APPROVED` (2026-11-08) via `S09_D10_D15_OWNER_APPROVAL_2026-11-08.md`  
> Closes D02-Q18 / D06-Q8 presentation deferral.  
> Does **not** change formulas (D02) or empty/undefined semantics (D06).

---

## Binding presentation rules

| KPI / class | Machine `value` | Server `display_value` (or equivalent) | UI |
|---|---|---|---|
| All COUNT KPIs (T01,T02,T08,A02,A03,P01–P06,P08,J01–J03,R01–R05,R09, …) | Integer JSON number | Same integer string | Arabic locale optional; no fractional digits |
| **P07** SUM | Numeric with calculation precision | **ROUND_HALF_UP** then format: **max 4** fraction digits, **strip trailing zeros** | «غير متاح» if undefined (should not apply to empty SUM — empty SUM = `0` per D06) |
| **J07** AVG | Numeric / `null` if undefined | If defined: **ROUND_HALF_UP** to **1** fraction digit | «غير متاح» if undefined |
| **J09** rate % | Numeric / `null` if undefined | If defined: **ROUND_HALF_UP** to **1** fraction digit | «غير متاح» if undefined |

### Rounding

- Algorithm: **ROUND_HALF_UP** (half away from zero toward +∞ for positive values; document edge cases in tests).  
- Authority: **server** produces display fields.  
- Client: locale grouping / Eastern vs Western digits per product chrome — **must not** apply a second conflicting round.

### Null / undefined (unchanged D06)

| Case | `value` | state | UI |
|---|---|---|---|
| Empty COUNT/SUM | `0` | `ok` | `0` |
| Empty AVG / zero denom | `null` | `undefined` (or agreed machine code) | «غير متاح» |
| Technical isolation error | `null` | `error` | Safe non-disclosing error UX |

---

## UAT acceptance samples (fixtures)

| Case | Expected |
|---|---|
| COUNT = 12 | display `12` |
| P07 = 10.5000 | display `10.5` (trailing zeros removed) |
| P07 = 10.12345 → after half-up to 4 dp | display `10.1235` then trim → `10.1235` |
| P07 = 10.1000 | display `10.1` |
| J07 = 33.333… | display `33.3` |
| J07 = 33.35 | display `33.4` (half-up) |
| J09 = 0 denom | «غير متاح», value null |
| J09 = 50.04% raw | display `50.0` or `50`? → **1 dp** → `50.0` (keep one decimal for rates unless Owner later amends; trim rule applies only to P07) |

**Note:** Trailing-zero trim is **P07-specific** per Owner wording. J07/J09 keep **exactly one** decimal place for defined values.

---

## Test requirements (when D16 authorizes code)

- Unit: ROUND_HALF_UP helpers for P07/J07/J09.  
- Feature: response includes both raw/calc-safe value and display field (shape finalized at implementation).  
- FE: renders server display; does not invent second rounding.  
- RTL/Arabic: «غير متاح» for undefined.

---

## Status

```
S09-D14 = OWNER_APPROVED
PRESENTATION_PRECISION_FROZEN
NO_APPLICATION_CODE
```

# S09-D05 — Owner Binding Clarifications (2026-11-08)

> **Authorization boundary:** DESIGN DOCUMENTATION ONLY.  
> Does **not** authorize application code, migrations, commits, push, merge, PR, or deploy.  
> Does **not** approve D06…D16 or authorize S09 implementation.

---

## Authorization

| Field | Value |
|---|---|
| Date | **2026-11-08** |
| Prior record | `S09_D05_OWNER_APPROVAL_2026-11-08.md` (initial Q1–Q9) |
| Binding clarifications | **C1=R1 · C2=404 · C3=yes** |
| Result | `S09-D05 = OWNER_APPROVED_TECHNICALLY_FROZEN` |

---

## Exact clarifications

### D05-C1 = R1 — Season selection (KPI API)

| Rule | Binding |
|---|---|
| KPI API | **Requires** explicit `season_id` |
| Missing `season_id` | **`422 SEASON_REQUIRED`** |
| Server fallback to Active | **Forbidden** for S09 KPI API |
| UI | May **suggest** Active season (see C3) |
| Historical Q1=B | Preserved as originally selected; **clarified/superseded for API semantics** by C1=R1 (UI suggestion only; no silent API fallback) |

**Resolved:** former `D05-ISSUE-01` (Q1=B vs Q2=A). Q2=A remains binding and is consistent with C1=R1.

### D05-C2 = 404 — Cross-tenant isolation

| Rule | Binding |
|---|---|
| Foreign-tenant season | **`404`** — no disclosure of existence |
| Historical Q3=B (403) | Preserved historically; **superseded** by C2=404 |
| ADR / code | Aligns with `MULTI_TENANCY.md` and `SeasonContextResolver` / `SeasonContextTest` |

**Resolved:** former `D05-ISSUE-02`.

### D05-C3 = yes — Explicit UI confirmation

The UI must:

1. Suggest the active season where available.  
2. Require **explicit** user confirmation.  
3. Send the **confirmed** `season_id` to the KPI API.  
4. **Never** silently select a season on the user’s behalf.

**Resolved:** former `D05-ISSUE-03` (confirmation vs silent fallback). Compatible with C1=R1 and Q4=B.

---

## Preserved D05 decisions (unchanged)

| Q | Choice | Binding |
|---|---|---|
| Q2 | A | Omit → `422 SEASON_REQUIRED` (reinforced by C1) |
| Q4 | B | UI suggests Active; explicit confirmation (reinforced by C3) |
| Q5 | A | Closed seasons visible with «مغلق»; authorized read-only |
| Q6 | A | Procurement null `season_id` excluded; no join inference |
| Q7 | A | Page gate + domain `*.view` card visibility; detail → **D08** |
| Q8 | A | Empty eligible counts → `0`; undefined ratios → **D06/D14** |
| Q9 | A | No seasons → «لا مواسم» (not KPI zeros) |

---

## Compatibility verification

| Check | Result |
|---|---|
| D01 — 24 KPI IDs | Compatible |
| D02 — formulas / Q17 | Compatible (Q6) |
| D03 — enums | Compatible |
| D04 — closed read + season row scope + snapshot | Compatible (Q5 / season_id filter) |
| Q2=A + C1=R1 | Consistent |
| Q4=B + C3=yes + C1=R1 | Consistent |
| ADR cross-tenant 404 + C2 | Consistent |
| Existing season domain resolver Active-fallback | **Out of scope for S09 KPI API** — domain APIs unchanged; KPI API must not copy silent fallback |
| Material contradictions remaining | **None** |

---

## Freeze verdict

`S09-D05 = OWNER_APPROVED_TECHNICALLY_FROZEN`

Remaining **non-blocking** dependencies (not D05 blockers):

- **D06 / D14** — ratio / N/A / precision (Q8 partial; D02-Q18)  
- **D08** — detailed RBAC keys (Q7)  
- **D16** — implementation authorization  

---

## No-code boundary

| Action | Authorized? |
|---|---|
| Document C1–C3 in S09 design docs | **Yes** |
| Application / migrations / commit / push / deploy | **No** |
| Change domain `SeasonContextResolver` behavior | **No** |
| Approve D06 | **No** |

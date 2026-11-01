# S04 Phase V — Owner Decision Register (V-D1…V-D8)

> **Status:** `OWNER APPROVED` — all eight decisions (2026-11-01)  
> **Owner act:** [S04-PHASE-V-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-V-OWNER-DECISIONS-2026-11-01.md)  
> **Detailed design:** [PHASE_V_DETAILED_DESIGN.md](PHASE_V_DETAILED_DESIGN.md) — **FINAL DESIGN OWNER ACCEPTED** ([S04-PHASE-V-FINAL-DESIGN-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-V-FINAL-DESIGN-ACCEPTANCE-2026-11-01.md))  
> **Implementation:** **COMPLETED (engineering PASS)** — [PHASE_V_FINAL_IMPLEMENTATION_AND_ACCEPTANCE_EVIDENCE.md](PHASE_V_FINAL_IMPLEMENTATION_AND_ACCEPTANCE_EVIDENCE.md)  
> **Functional acceptance:** **OWNER ACCEPTED — FINAL** (2026-11-01) — [S04-PHASE-V-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-V-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md)  
> **Does not:** reopen ADR-0022 **101–130** · authorize deploy · close S04 overall

Historical recommendation rows below are retained for audit; **Approved** column is binding.

---

## Decision index (binding)

| ID | Topic | Approved | Blocks coding until design accept? |
|---|---|---|---|
| **V-D1** | Membership grain + exclusivity | **A** — PR-header; exclusive active membership | Yes (schema) |
| **V-D2** | Season / OU scope | **B** — both optional; mixed + warnings; no new OU ACL | Yes |
| **V-D3** | Lifecycle | **A** — `draft → active → frozen → closed` | Yes |
| **V-D4** | Cancel-approved | **A** — auto-unlink + history + totals | Yes |
| **V-D5** | Engagement | **A** — freeze membership edits; no Phase V engagement writes | Yes |
| **V-D6** | Compliance report | **A** — RPT-V01…V04 all mandatory | Soft→Yes for V-WP04 |
| **V-D7** | Visibility | **B** — mutate `consolidate_planning`; read per PR `view` contract | Soft→Yes for Policy |
| **V-D8** | CSV export | **A** — CSV for **all four** reports (**overrides** prior defer recommendation) | Soft→Yes for export WP |

---

## V-D1 — Membership grain and exclusivity — **APPROVED A**

**Question:** Are demand-group items whole PRs or lines? May one PR sit in multiple active groups?

| Option | Description |
|---|---|
| **A (APPROVED)** | Items = **PR headers**; at most **one active** group membership per PR |
| **B** | Items = **PR lines**; exclusivity per line |
| **C** | PR headers; **multi-group** allowed |

**Sources:** ADR-0022 **123**; API.md; DATA_MODEL. Engineering: EC-V01.

---

## V-D2 — Season and organizational scope — **APPROVED B**

**Question:** Must a demand group bind a Hajj season and/or beneficiary org unit?

| Option | Description |
|---|---|
| **A** | Season **required**; OU optional |
| **B (APPROVED)** | Season **optional**; OU **optional**; mixed allowed with warnings |
| **C** | Season required when any member is seasonal; else optional |

**Sources:** ADR-0022 **118**; III-D5. Engineering: EC-V02.

---

## V-D3 — Lifecycle statuses — **APPROVED A**

**Question:** Which statuses does a demand group have?

| Option | Description |
|---|---|
| **A (APPROVED)** | `draft` → `active` → `frozen` → `closed` |
| **B** | `active` / `closed` only |
| **C** | No status field; soft-delete only |

Engineering: EC-V03.

---

## V-D4 — Cancel-approved PR already in a group — **APPROVED A**

**Question:** When an approved PR in a group is cancelled under Phase IV `cancel_approved`, what happens to membership?

| Option | Description |
|---|---|
| **A (APPROVED)** | Auto-unlink; preserve history; exclude from totals |
| **B** | Keep link flagged invalid |
| **C** | Block `cancel_approved` while grouped |

Must not weaken Phase IV cancel/engagement. Engineering: EC-V04.

---

## V-D5 — Procurement engagement — **APPROVED A**

**Question:** If S05 sets `procurement_engaged_at` on a member PR, may planners still edit the group?

| Option | Description |
|---|---|
| **A (APPROVED)** | Freeze membership changes while any member engaged |
| **B** | Allow unlink only |
| **C** | No special rule until S05 designs it |

Phase V must not write engagement fields. Engineering: EC-V03.

---

## V-D6 — Compliance report scope — **APPROVED A**

**Question:** Is RPT-V04 mandatory in Phase V?

| Option | Description |
|---|---|
| **A (APPROVED)** | Include RPT-V01…V04 (compliance mandatory) |
| **B** | Only three API.md endpoints |
| **C** | Audit deep-link only |

**Sources:** ADR-0022 **130**. Engineering: EC-V06.

---

## V-D7 — Visibility of demand groups — **APPROVED B**

**Question:** Who may list/show demand groups?

| Option | Description |
|---|---|
| **A** | Only `consolidate_planning` |
| **B (APPROVED)** | Mutate: `consolidate_planning`; read: planning users **or** `purchase_requests.view` under existing PR visibility contract; no leak of hidden PR info |
| **C** | Also grant read via `view_control_reports` |

Engineering: EC-V05 (notes III-D5 tenant-wide `view`).

---

## V-D8 — Export — **APPROVED A** *(supersedes prior C recommendation)*

**Question:** Are CSV/XLSX exports in Phase V?

| Option | Description |
|---|---|
| **A (APPROVED)** | **CSV export for all four control reports** |
| **B** | CSV for outcomes only |
| **C** *(prior recommendation — REJECTED)* | Defer export |

**No XLSX** unless separately authorized. Engineering: EC-V07. Update all docs that said “Export DEFERRED”.

---

## Related non-Phase-V follow-up

**`fr_count=2`** — Phase IV non-blocking follow-up; not a Phase V V-D.

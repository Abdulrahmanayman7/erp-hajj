# S04 Phase II-C — Owner Decisions (IIC-D1–IIC-D8 · IIC-F1–IIC-F3)

> **Status:** `APPROVED — OWNER DECISIONS` (documentation) — **all eleven decisions**  
> **Owner decision date:** 2026-11-01  
> **Design pack:** [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md)  
> **Detailed design status:** `DESIGN DRAFT — DECISIONS COMPLETE — NOT YET OWNER ACCEPTED AS FINAL DESIGN`  
> **Does not:** authorize coding, migrations, seeding, tests-as-implementation, commit, push, merge, deploy, Phase III PR, or S05.  
> **Does not:** mark Phase II-C design OWNER ACCEPTED or COMPLETE.  
> **Does not:** reopen ADR-0022 **101–130**, G1 **D1–D5**, II-D1–II-D4, IIB-D1–IIB-D12, or Phase I / II-A / II-B acceptance.

## 1. Context

Project Owner approved **IIC-D1–IIC-D8** (design preparation) and subsequently approved **IIC-F1–IIC-F3** (2026-11-01) to close the remaining design follow-ups. All **eleven** decisions are **binding** for the II-C design pack. The detailed design pack itself remains subject to a **separate** Owner **final design-acceptance** act before any coding authorization.

**Phase II-C identity:** Supplier **Evaluations** (ADR-0022 **112–116**). Not purchase requests.

## 2. Approved decisions — IIC-D1–IIC-D8

| ID | Choice | Decision text |
|---|---|---|
| **IIC-D1** | **A** | Criteria follow Draft → independent approval → immutable published version. Only approved published criteria may be used for scoring. Explicit permissions and hard SoD for criteria preparation vs approval. No platform default criteria pack. |
| **IIC-D2** | **A** | Active criteria weights must total **100 ±0.01**. Evaluation score range **0–100**. Snapshot approved criteria version and scoring inputs at evaluation **submission**. Later criteria edits must not change submitted/approved scores. |
| **IIC-D3** | **A** | Manual `monitoring_recommended` decision. No platform numeric low-score threshold. No automatic supplier suspension/archival/lifecycle transition. Monitoring recommendation and supplier status remain separate. |
| **IIC-D4** | **A** | Dedicated supplier evaluation appeals register. Accepted corrections produce a **new** evaluation version. Previously approved versions remain immutable. Independent appeal review and correction authorization. No in-place modification of approved scores. |
| **IIC-D5** | **A+C** | Supplier must not be archived. Evaluation category must be associated with the supplier. Season is required for **selected** evaluation kinds (map frozen by **IIC-F1**). |
| **IIC-D6** | **A** | Provide both dedicated `/app/supplier-evaluations` and supplier-details evaluations tab (Arabic RTL, permission-aware). |
| **IIC-D7** | **A** | Emit an **internal** notification when a monitoring recommendation is recorded, via existing Notifications module. No external channels. Refined by **IIC-F2** / **IIC-F3**. |
| **IIC-D8** | **A** | Optional evidence documents linked to evaluations via Documents patterns. File not required for every evaluation. |

## 3. Approved decisions — IIC-F1–IIC-F3 (2026-11-01)

| ID | Choice | Decision text |
|---|---|---|
| **IIC-F1** | **A** | `post_season` evaluations **MUST** have a valid same-tenant `season_id`. `qualification`, `periodic`, and `operational` **MAY** supply `season_id` (optional). No additional mandatory season kinds. |
| **IIC-F2** | **A** | Monitoring notifications go only to same-tenant users holding explicit `supplier_evaluations.approve`. Broad supplier-management roles (e.g. `suppliers.transition` alone) are **not** sufficient. In-app only; use existing dedupe/retry. |
| **IIC-F3** | **A** | Preparer **MAY propose** `monitoring_recommended` on draft. Approver sets the **final** value on approve. Final approved value is immutable for that version. Notification fires **only** for the final approved recommendation (when true). Preparer proposals must **not** notify. No automatic supplier lifecycle changes. Hard preparer≠approver SoD preserved. |

### IIC-F1=A — operational rules (binding)

1. If `evaluation_kind = post_season` and `season_id` is null/missing → `422 EVALUATION_SEASON_REQUIRED`.  
2. If `season_id` is present for any kind → must resolve to a Season in the **same tenant**; else `404` or `422` tenant-safe validation (cross-tenant → **404**).  
3. Do **not** require season for `qualification` / `periodic` / `operational`.

### IIC-F2=A — operational rules (binding)

1. Recipient query: active users in tenant with permission `supplier_evaluations.approve`.  
2. **Exclude** the approving actor from recipients **(Eng recommendation — non-blocking)**.  
3. No cross-tenant recipients.  
4. Dedupe via existing Notifications infrastructure keyed by `(tenant_id, evaluation_id, type)`.  
5. No email / SMS / external channels.

### IIC-F3=A — operational rules (binding)

1. Draft/PATCH may store a **proposed** `monitoring_recommended` (preparer).  
2. On **approve**, request may include final `monitoring_recommended`; if omitted, Eng default = carry forward the proposed value as the final value (**non-blocking Eng**).  
3. Persist final flag on the approved version; immutable thereafter.  
4. Emit monitoring notification **only after successful approve** when final `monitoring_recommended === true`.  
5. Submit / draft saves must **not** emit the monitoring notification.  
6. Never call supplier lifecycle transitions from monitoring logic.

## 4. Traceability

| Decision | Primarily constrains |
|---|---|
| IIC-D1 | **II-D4**, ADR-0022 **112**, AC-E01 |
| IIC-D2 | **112**, AC-E01; immutability of scores |
| IIC-D3 | **115**, **II-D3**, AC-E04 |
| IIC-D4 | **116**, AC-E05 |
| IIC-D5 + **IIC-F1** | **113**, AC-E02 |
| IIC-D6 | UI.md evaluations |
| IIC-D7 + **IIC-F2** + **IIC-F3** | ADR-0013; AC-E04 alert path |
| IIC-D8 | ADR-0010; **112** supporting evidence |

## 5. Preserved bindings

- **II-D3:** Document expiry / low evaluation must **not** auto-change supplier lifecycle.  
- **II-D4:** Tenant-managed criteria; **no** platform default pack; **no** platform numeric low-score threshold.  
- **II-D2:** Banking remains independent of evaluations and activation.  
- Phase I / II-A / II-B acceptance and functional closures **unchanged**.  
- Phase III PR / Phase IV approval engine / S05 **out of II-C**.

## 6. Open Owner decisions

**None** among the eleven identified II-C decisions. Remaining items in the design pack marked **(Eng)** are non-blocking engineering recommendations pending final design acceptance (not new Owner decision IDs unless a material ambiguity appears).

## 7. Governance after these decisions

| Item | Status |
|---|---|
| Phase I | COMPLETE — OWNER ACCEPTED |
| Phase II-A | COMPLETE — OWNER ACCEPTED |
| Phase II-B | COMPLETE — FUNCTIONALLY CLOSED — OWNER ACCEPTED |
| Phase II-C Owner decisions (IIC-D1–D8, IIC-F1–F3) | **APPROVED** (2026-11-01) — **11/11** |
| Phase II-C detailed design | **DRAFT — NOT YET OWNER ACCEPTED** (awaiting final design-acceptance act) |
| Phase II-C implementation | **NOT_STARTED — NOT AUTHORIZED** |
| Commit / push / merge / deploy | **NOT AUTHORIZED** |
| Phase III–V / S05 | **NOT_STARTED** |
| S04 overall | **IN PROGRESS** |

## 8. References

- [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md)  
- [API.md §14](../09-modules/19-suppliers-and-procurement/API.md)  
- [S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md](S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md) (II-D3, II-D4)  
- [ADR-0022](ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) **112–116**  
- [ADR-0023](ADR-0023-S04-ENGINEERING-DESIGN.md) §2.6  
- [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md)

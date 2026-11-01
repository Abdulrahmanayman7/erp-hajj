# S04 Phase IV — Owner Decisions (IV-D1–IV-D15)

> **Status:** `APPROVED — OWNER DECISIONS` (documentation) — **all fifteen decisions**  
> **Owner decision date:** 2026-11-01  
> **Design pack:** [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_IV_APPROVAL_ENGINE_DESIGN.md)  
> **Engineering closure (non-substantive):** [PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md](../09-modules/19-suppliers-and-procurement/PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md) (**EC-01…EC-04**) — does **not** alter IV-D1–IV-D15  
> **Detailed design status:** `FINAL DESIGN OWNER ACCEPTED` (2026-11-01) — [S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md](S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md)  
> **WP01 plan:** [PHASE_IV_WP01_IMPLEMENTATION_PLAN.md](../09-modules/19-suppliers-and-procurement/PHASE_IV_WP01_IMPLEMENTATION_PLAN.md) — **IV-WP01 implementation NOT AUTHORIZED**  
> **Does not:** authorize coding, migrations, seeding, tests-as-implementation, commit, push, merge, deploy, Phase V, or S05.  
> **Does not:** mark Phase IV functionally IMPLEMENTED or COMPLETE.  
> **Does not:** reopen ADR-0022 **101–130**, G1 **D1–D5**, III-D1–D8, or Phase I / II-A / II-B / II-C / III functional acceptance.

## 1. Context

Project Owner approved **IV-D1–IV-D15** (2026-11-01) to freeze Phase IV Purchase Request **Approval Engine** product choices for detailed engineering design.

**Phase IV identity:** Multi-level PR approvals and controls (ADR-0022 **124–130**) wired to Phase III PR contracts (**117–123** + financial review **D2** + SoD **D5** + approved cancel **D3**).

**Not Phase IV:** Demand groups / control reports (Phase V); RFQ/PO/receive (S05); payments; budget reservation; inventing global default financial bands or SLA durations.

**Phase III handoff (preserved):** Submit → `submitted` with **no** matrix/steps (**III-D1**); structured versions (**III-D7**); independent version-bound financial reviews (**III-D2**/**III-D3**); no approve UI in III (**III-D8**).

## 2. Approved decisions — IV-D1–IV-D15

| ID | Choice | Binding summary |
|---|---|---|
| **IV-D1** | **A** | Automatic routing: resolve published matrix, create instance/steps, transition `submitted` → `under_review`. Phase III submit still only sets `submitted`. Idempotent; fail closed if config incomplete. |
| **IV-D2** | **B** | Unpriced lines force tenant unknown/unpriced or elevated tier — never lower authority via priced subtotal alone. FR still mandatory before final approve. No invented global bands. |
| **IV-D3** | **A** | Deterministic most-specific rule (OU / season / kind / band); min-amount tie-break; residual ambiguity → fail closed. |
| **IV-D4** | **B** | Eligible **approver pool** per level; explicit `approve` + authority + SoD; first valid concurrent action wins atomically. |
| **IV-D5** | **A** | Creator (`requested_by`) must not approve own PR at **any** level; roles/multi-perm/delegation cannot bypass. Creator may still FR if explicitly permitted (III). |
| **IV-D6** | **A** | Incomplete matrix/authority/eligible path → keep `submitted` + actionable blocked reason; notify config admins; no zero-step workflow; safe retry after fix. |
| **IV-D7** | **A** | Current active-step eligible actor may **approve**, **reject**, or **return** (explicit perms + SoD). Reject → `rejected`; return → `returned`; cancel remaining pending steps; reasons + immutable audit. |
| **IV-D8** | **A** | After return + revise + resubmit: new version; **re-resolve** current published matrix; restart all levels; never reuse prior-version approvals; preserve history. |
| **IV-D9** | **A** | FR allowed on current version while `under_review` until final approve; final gate uses **latest** qualifying review for current `version_no`; D2 + D5 apply. Append-only III model preserved. |
| **IV-D10** | **C** | Auto-exclude financial reviewer from final-approver pool; another eligible actor may approve; if pool empty → block + notify approval admin; never grant new authority or auto-approve. |
| **IV-D11** | **C** | Constrained self-delegation **and** central admin delegation; time-bounded; ceiling ≤ delegator; acting user must satisfy eligibility/SoD; sensitive admin actions explicit. |
| **IV-D12** | **A** | Tenant-configured SLA durations + reminders + escalation **notifications** only; notify pool + approval admins; **no** auto-approve / skip / reassign on expiry; no global default duration. |
| **IV-D13** | **A** | `cancel_approved` + mandatory reason + audit; block when procurement engagement lock set; no S05 actions. |
| **IV-D14** | **A** | Operational in-app notifications set (pool entry, due, SLA escalate, returned, rejected, approved, cancel-approved, SoD/missing final approver); tenant-isolated; no daily digest / Phase V reports. |
| **IV-D15** | **B** | Detailed IV design may proceed while III governance docs sync; **before IV coding/migrations:** III acceptance accurately documented + IV design Owner-accepted + **separate** IV coding authorization. |

## 3. Operational rules (binding)

### IV-D1=A — Automatic routing

1. Phase III / submit path: `draft`|`returned` → `submitted` only; `matrix_id` null; no instance (**III-D1**).  
2. Phase IV engine processes `submitted` PRs: resolve matrix, snapshot refs on PR, create instance + steps, set `under_review`.  
3. Routing is **idempotent** (safe retry).  
4. Incomplete config → **IV-D6** (remain `submitted`, blocked reason).

### IV-D2=B — Unpriced / elevated path

1. If any working line is unpriced (`unit_estimate` null / `has_unpriced_lines`), matching **must not** use priced-only subtotal to select a **lower** band.  
2. Use tenant-configured **unknown/unpriced** rule path or elevated tier.  
3. Tenants configure bands; **no** platform-default monetary thresholds.

### IV-D3=A — Rule specificity

1. Prefer rules with more concrete predicates (non-null OU, season, kind) over wildcards.  
2. Among peers, apply approved **minimum-amount** specificity / tie-break.  
3. Irreducible ambiguity → fail closed (`PR_APPROVAL_RULE_AMBIGUOUS` or equivalent).

### IV-D4=B — Pool claim

1. Eligibility = `purchase_requests.approve` + authority ceiling covers required amount path + tenant + SoD (**IV-D5**, **D5**/**IV-D10**).  
2. Concurrent approve attempts: row-lock step; one success; others deterministic conflict (`409` / stable code).

### IV-D5=A — Creator SoD

1. `requested_by` excluded from **every** step pool.  
2. Delegation to/from creator cannot place creator as acting approver on own PR.

### IV-D6=A — Fail closed

1. No published matrix / no matching rule / no authority / empty eligible pool after SoD → PR stays `submitted`.  
2. Persist actionable `routing_block_reason` / code (Eng field names in design pack).  
3. Notify `manage_approval_config` (and/or dedicated escalate recipients).  
4. Retry routing after config repair is safe and idempotent.

### IV-D7=A — Step actions

1. Distinct permissions: `approve`, `reject`, `return` (all sensitive / explicit — **D4**).  
2. Approve on non-final → next pending level; on final → `approved` after **D2**/**D5** gates.  
3. Reject → `rejected`; return → `returned`; cancel other pending steps.  
4. Reject/return require reason (≥ Eng minimum length).

### IV-D8=A — Resubmit re-route

1. Edit only in `returned` (and `draft`); resubmit creates new structured version (**III-D7**).  
2. New routing against **currently published** matrix; new instance; prior instance retained historically.

### IV-D9=A — FR during under_review

1. Product: FR allowed until final approve on current version.  
2. **Integration note:** Phase III action currently allows FR only on `draft`|`submitted`. Extending to `under_review` is a **narrow Phase IV coding change** when IV is authorized — not a Phase III contract reopen.  
3. Final approve reads latest review for current `version_no` with outcome ∈ {`cleared`,`cleared_with_conditions`}.

### IV-D10=C — FR exclusion from final pool

1. Final-level eligibility excludes current-version FR `reviewer_id`.  
2. Other eligible pool members may approve without manual reassignment.  
3. Empty final pool → block + notify approval administrator; never auto-approve.

### IV-D11=C — Delegation

1. Self-delegation within own ceiling + admin `manage_delegations`.  
2. Acting user identity is checked for SoD/eligibility (not “as if” delegator for SoD).  
3. No chains that raise ceiling; prevent cycles; time-bound; revocable; audited.

### IV-D12=A — SLA notify-only

1. Tenant SLA policies may be empty.  
2. Reminders/escalation = notifications only.

### IV-D13=A — Cancel approved

1. Permission `purchase_requests.cancel_approved`; reason required; audit.  
2. Status outcome: existing enum **`cancelled`** (same as non-approved cancel) with `cancelled_*` fields; distinguish via permission + audit event (no new status invented).  
3. Block when `procurement_engaged_at` set.

### IV-D14=A — Notification catalog

Mandatory IV MVP events listed in §2 table (pool entry, due, SLA escalate, returned, rejected, approved, cancel-approved, SoD/missing final approver).

### IV-D15=B — Governance gate

1. Design docs may proceed now.  
2. Coding/migrations require: III acceptance reflected in authoritative docs + IV design Owner acceptance + **separate** IV coding auth.

## 4. Traceability

| Decision | Constrains |
|---|---|
| IV-D1 / D6 | ADR-0022 **124**/**125**; III-D1 handoff |
| IV-D2 / D3 | ADR-0022 **124**/**125**; no default bands |
| IV-D4 / D5 / D7 | ADR-0022 **126**/**128**; PERMISSIONS |
| IV-D8 | ADR-0022 **121**/**128**; III-D7 |
| IV-D9 / D10 | Owner **D2**/**D5**; III-D2/D3 |
| IV-D11 | ADR-0022 **127**; Owner **D4** |
| IV-D12 / D14 | ADR-0022 **129**; ADR-0013 notifications |
| IV-D13 | Owner **D3** |
| IV-D15 | Coding authorization boundary |

## 5. Deferred / out of scope

- Phase V demand groups and control reports (**123** planning UI / **130** reports UIs).  
- S05 RFQ/PO/GRN and engagement writers (lock columns only).  
- Platform-default money bands or SLA hours.  
- Auto-approve on SLA, conflict, or missing config.

## 6. Implementation authorization status

| Item | Status |
|---|---|
| Phase IV Owner decisions IV-D1–D15 | **APPROVED** (2026-11-01) — **15/15** |
| Phase IV detailed design | **FINAL DESIGN OWNER ACCEPTED** (2026-11-01) |
| IV-WP01 implementation | **NOT AUTHORIZED** |
| Phase IV coding / migrations | **NOT AUTHORIZED** (separate Owner act still required) |
| Phase V / S05 | **NOT AUTHORIZED** |
| Commit / push / merge / deploy | **NOT AUTHORIZED** |

## 7. References

- [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_IV_APPROVAL_ENGINE_DESIGN.md)  
- [ADR-0022](ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) **117–130**  
- [ADR-0023](ADR-0023-S04-ENGINEERING-DESIGN.md) §2.7–2.8  
- [S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md](S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md)  
- Module [API.md](../09-modules/19-suppliers-and-procurement/API.md) · [DATA_MODEL.md](../09-modules/19-suppliers-and-procurement/DATA_MODEL.md) · [BUSINESS_RULES.md](../09-modules/19-suppliers-and-procurement/BUSINESS_RULES.md) · [PERMISSIONS.md](../09-modules/19-suppliers-and-procurement/PERMISSIONS.md)

# ADR-0023 — S04 Suppliers & Purchase Requests — Engineering Design

> **Status:** `APPROVED — ENGINEERING DESIGN`  
> **Date:** 2026-10-31  
> **Owner approval date:** 2026-10-31  
> **Deciders:** Project Owner  
> **Builds on:** [ADR-0022](ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) (functional freeze 101–130), [ADR-0020](ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S04  
> **Module pack:** [docs/09-modules/19-suppliers-and-procurement/](../09-modules/19-suppliers-and-procurement/)  
> **Does not:** authorize application implementation, migrations, seeding, deployment, or S05; invent numeric financial bands or SLA hours; accept ADR-0019; modify MVP 21-module list.  
> **Implementation:** **NOT AUTHORIZED.** Engineering design approval ≠ G2 coding authorization. A separate explicit Owner implementation authorization is required before any application or database work.

## 1. Context

ADR-0022 froze S04 functional decisions. The platform today has **no** supplier/PR tables, **single-step** approvals only (Contracts/Decisions), Documents **without** version tables (ADR-0010), and Inventory receive **without** PO FKs (ADR-0011). This ADR locks the engineering shape that satisfies 101–130 without implementing S05 or finance.

## 2. Decision (approved engineering design)

Adopt the module documentation in `docs/09-modules/19-suppliers-and-procurement/` as the **approved** engineering specification for S04 design, with the architectural choices below. **This approval does not authorize implementation.**

### 2.1 Bounded context

New Laravel module under `backend/app/Modules/…` owning supplier + evaluation + PR + approval-config aggregates. Prefer **one module** with clear internal namespaces (**ENGINEERING PROPOSAL** — name `SuppliersAndProcurement`).

### 2.2 Tenancy

All business tables `TenantOwned` + `UsesTenantScope`. Platform `geo_places` is shared reference only (no tenant data). No `runWithoutTenancy`. Cross-tenant → 404.

### 2.3 Geographic catalog (**106**) — Owner **D1**

**Chosen:** minimal platform `geo_places` (country/region/city/holy_site) + tenant coverage links.  
**Rejected:** world GIS; free-text SoR lists.  
Reuse ISO alpha-2 country codes consistent with pilgrim nationality fields.  
**Initial seed scope (D1):** Makkah, Madinah, Jeddah, Mina, Arafat, Muzdalifah (+ parent country `SA`). Extensible; unique `(country_code, kind, code)` prevents duplicates.  
**Pre-implementation:** validate proposed stable codes/names against authoritative geographic references before shipping seed rows (see DATA_MODEL).

### 2.4 Supplier documents (**109**) vs ADR-0010

**Chosen:** eligibility metadata table referencing immutable `documents` rows; supersede by new upload + new eligibility row.  
**Rejected:** adding Document version_number column (conflicts ADR-0010).

### 2.5 Bank accounts (**110**)

**Chosen:** encrypt IBAN/account with application key; store last4; permissions `view_banking` / `manage_banking` / `verify_banking`; verification workflow.  
**Rejected:** plaintext IBAN; payments.  
Sensitive banking permissions are **not** auto-granted (**D4**).

### 2.6 Evaluations (**112–116**)

Weighted criteria per category; evaluation versions; preparer ≠ approver; monitoring flag ≠ supplier status; no auto-suspend.

### 2.7 PR model (**117–123**) — Owner **D2** / **D3**

Header + lines; seasonal vs administrative; optional supplier; estimates + unpriced reasons; structured version snapshots; planning-only demand groups.  
**Mandatory financial review (D2):** every PR (including unpriced) must have authorized review (reviewer, outcome, timestamp, justification, version) before **final** approval. No accounting entry, budget reservation, or payment.  
**Approved cancel (D3):** dedicated `purchase_requests.cancel_approved`; mandatory reason; full audit; blocked when `procurement_engaged_at` is set (future S05 engagement lock — **no** RFQ/PO implementation in S04).  
**No** stock mutation; optional `inventory_item_id` reference only.

### 2.8 Approval engine (**124–130**) — new subsystem

**Chosen:** tenant matrices with versioned publish + **snapshot on PR**; authority ceilings; step instances; SoD; delegations; SLA policies without auto-approve.  
**Rejected:** reusing Decisions/Contracts single `approve` as the only mechanism.  
**Approver binding:** `purchase_requests.approve` + authority ceiling + matrix level order (no role-name checks in Policies).  
**No invented SLA durations or financial band defaults** — tenant configures before production use.  
Sensitive approve/reject/return/delegation/config permissions are **not** auto-granted (**D4**).

#### Mandatory financial-reviewer ≠ final-approver SoD (**D5** — Owner 2026-10-31)

Hard enforcement (not a warning) for every PR version:

1. The user who recorded the financial review for the current PR version **must not** be the user who completes **final** approval.  
2. Restriction applies even when one user holds multiple roles / permissions.  
3. **Delegation must not bypass** this rule (delegate acting for an approver still fails if the acting user is the financial reviewer for that version).  
4. If the would-be final approver is the financial reviewer, **reassign** the final step to another qualified independent approver (authority + matrix eligibility + SoD).  
5. If **no** eligible final approver exists, **block** final approval and **escalate** to an authorized administrator — never auto-approve, waive, or skip mandatory steps.  
6. Audit assignment changes, conflicts, and resolution/escalation actions.  

Supplements existing SoD (creator ≠ approver, eval preparer ≠ approver) without changing ADR-0022 decisions 101–130.

### 2.9 Integration boundaries

| System | S04 stance |
|---|---|
| Contracts | Preserve behavior; optional future `supplier_id` via **separate** CR; no backfill |
| Inventory | No receive-against-PR; S05 may later call `ReceiveStock` from PO |
| Documents | New morph aliases only |
| Seasons | FK when `request_kind=seasonal` |
| Notifications | In-app events for approval/SLA/eval/bank |
| Audit | AuthorizationSecurity + domain transition tables |
| Future S05 | May set `procurement_engaged_at` / ref on PR; S04 only reads the lock |

### 2.10 S05 boundary

No RFQ/PO/GRN schema or APIs in S04. Downstream cancel guard is a **nullable lock column** only.

### 2.11 Owner design decisions D1–D5 (2026-10-31)

| ID | Decision | Rationale (summary) | Design effect |
|---|---|---|---|
| **D1** | Limited geo catalog: Makkah, Madinah, Jeddah, Mina, Arafat, Muzdalifah; extensible; no duplicates; validate before implement | Enough for Hajj coverage without a geo subsystem | DATA_MODEL seed table; read-only for tenants |
| **D2** | Financial review mandatory for **all** PRs before final approval, including unknown estimates; record actor/outcome/time/justification/version; no ledger/reservation/payment | Control before commitment signal | Gate on final approve |
| **D3** | Approved-PR cancel via dedicated restricted permission; reason + audit; forbid after downstream purchasing starts; define future guard without building S05 | Controlled unwind without opening procurement cycle early | `cancel_approved` + `procurement_engaged_*` lock |
| **D4** | Propose role templates; **do not** auto-grant sensitive perms; explicit admin assignment + SoD | Least privilege | PERMISSIONS §3 / §3.1 |
| **D5** | Financial reviewer ≠ final PR approver — **hard block**; multi-role and delegation cannot bypass; reassign or escalate; never auto-approve | Stronger control integrity | §2.8 SoD; module BUSINESS_RULES / API / AC / tests |

## 3. Alternatives considered

| Topic | Alt A | Alt B (chosen unless noted) |
|---|---|---|
| Geo | Tenant-only places | Platform minimal `geo_places` + D1 seed |
| Financial review | Only when priced | **Mandatory always (D2)** |
| Approved cancel | Forbid entirely | Dedicated permission + downstream lock (**D3**) |
| Role seeds | Owner gets all verbs | Sensitive verbs explicit-only (**D4**) |
| Reviewer SoD | Warn only | **Hard block + reassign/escalate (D5)** |
| Approvals | Fixed 3 roles globally | Tenant matrix + snapshot |
| Banking | Tokenization vendor | App-level encryption (no vendor invented) |

## 4. Consequences

- First multi-level approval engine in the monolith — higher design/test cost.  
- Empty approval matrix / SLA / authority ceilings block production go-live until tenant configures (**125**/**129** — no invented defaults).  
- Sensitive capability requires deliberate admin grants (**D4**).  
- Geo seed validation is a pre-migration checklist item (**D1**).  
- Tenants need ≥2 distinct qualified actors for finance review vs final approve (**D5**) or final approval remains blocked with escalation.  
- **Implementation remains unauthorized** until a separate explicit Owner G2 coding authorization — this ADR approval alone is insufficient.

## 5. Security implications

Deny-by-default; SoD (including **D5** hard enforcement); banking least privilege; audit without secrets; tenant isolation; concurrency locks on numbers and approval steps; no persona bypass; no auto-approve on conflict or SLA timeout.

## 6. Remaining open items (post-approval; non-blocking for design)

| Item | Class |
|---|---|
| Authoritative validation of D1 place codes/names before seed | **ENGINEERING** pre-implementation checklist |
| Exact surrogate DB ids for geo rows | **ENGINEERING** (at migration time) |
| SLA default hours | **DEFERRED** (tenant config; none mandated) |
| Matrix amount band defaults | **DEFERRED** / per-tenant go-live |
| Encryption key rotation runbook | **ENGINEERING PROPOSAL** |
| Audit retention days | **DEFERRED** |
| Contracts.supplier_id migration timing | **DEFERRED** (separate CR) |
| Module PHP namespace final name | **ENGINEERING PROPOSAL** |
| Org-unit row-level PR scoping | **DEFERRED** (tenant-wide + permission in S04) |
| Explicit G2 implementation authorization | **OWNER** — separate from this ADR |

## 7. References

- Module pack `19-suppliers-and-procurement/*`  
- [CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md](../00-project/CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md)  
- ADR-0003, 0010, 0011, 0013, 0015, 0017, 0020, 0022  
- PERMISSION_MODEL · MODULE_TEMPLATE · DEFINITION_OF_DONE

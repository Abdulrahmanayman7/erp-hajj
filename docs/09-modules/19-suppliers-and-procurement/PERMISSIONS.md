# Suppliers & Procurement (S04) — Permissions

> **Status:** Phase I–IV permissions implemented/accepted per stream evidence; Phase V `consolidate_planning` + `view_control_reports` **IMPLEMENTED in catalog** (engineering) — Owner Phase V functional acceptance **PENDING**; S05 / release acts **NOT AUTHORIZED**
> **Last updated:** 2026-11-01
> Rules: [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md) — `module.action`, deny-by-default, no Owner bypass, no direct user↔permission grants.
> Owner **D4**: sensitive permissions are **not** auto-granted via default role templates.
> II-A **C6**: `suppliers.approve_eligibility_config` sensitive. II-B banking trio sensitive (**IIB**; B6). II-C: `approve` / `approve_criteria` / `handle_appeal` sensitive (**Eng** — design pack).

**Seed rule:** Seed permission **catalog** rows when code exists. **Role ↔ permission** template grants follow §3; sensitive set in §3.1 starts **ungranted** on all templates including Tenant Owner.

---

## 1. Proposed catalog

### Suppliers

| Permission | Purpose | Sensitive? |
|---|---|---|
| `suppliers.view` | List/show suppliers, categories (non-banking) | No |
| `suppliers.create` | Create draft suppliers | No |
| `suppliers.update` | Update non-terminal fields; manage categories | No |
| `suppliers.transition` | Lifecycle transitions | No |
| `suppliers.manage_documents` | Link eligibility docs / mark superseded | No |
| `suppliers.manage_eligibility_config` | Draft/edit eligibility requirement set versions | No |
| `suppliers.approve_eligibility_config` | Approve eligibility config version (**C6**) | **Yes** |
| `suppliers.view_banking` | Dedicated audited reveal only (**IIB-D12**, **E1**/**E4**); does **not** unmask list/show | **Yes** |
| `suppliers.manage_banking` | Create/edit pending; supersede (**E5**); set/clear primary; **revoke (E2)**; optional evidence | **Yes** |
| `suppliers.verify_banking` | Verify or reject pending (SoD **IIB-D4**); no primary transfer on verify (**IIB-D11**) | **Yes** |
| `suppliers.delete` | Hard-delete eligible drafts only | No |

### Evaluations (Phase II-C design draft — **IIC-D1**)

| Permission | Purpose | Sensitive? |
|---|---|---|
| `supplier_evaluations.view` | View evaluations / published criteria | No |
| `supplier_evaluations.prepare` | Create/edit draft evaluations; submit; file appeal | No |
| `supplier_evaluations.approve` | Approve/reject evaluation (≠ preparer) | **Yes*** |
| `supplier_evaluations.manage_criteria` | Draft criteria packs & lines | No |
| `supplier_evaluations.approve_criteria` | Publish criteria pack (≠ preparer/editors) | **Yes*** |
| `supplier_evaluations.handle_appeal` | Accept/reject appeals (independent) | **Yes*** |

\*Sensitive = explicit assignment only (**D4**). SoD still enforced in Policy even if granted. See [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md) §5.

### Purchase requests

| Permission | Purpose | Sensitive? | Phase |
|---|---|---|---|
| `purchase_requests.view` | List/show PRs | No | III |
| `purchase_requests.create` | Create draft PR | No | III |
| `purchase_requests.update` | Edit draft / returned PR | No | III |
| `purchase_requests.submit` | Submit / resubmit → `submitted` (**III-D1**) | No | III |
| `purchase_requests.cancel` | Cancel non-approved PRs | No | III |
| `purchase_requests.verify_priority` | Confirm urgent/emergency priority | No | III |
| `purchase_requests.financial_review` | Record financial review (**D2**/**III-D2**; creator allowed **III-D3**) | **Yes** | III |
| `purchase_requests.approve` | Act on approval steps within authority ceiling | **Yes** | **IV** |
| `purchase_requests.reject` | Final reject at an approval step | **Yes** | **IV** |
| `purchase_requests.return` | Return for revision | **Yes** | **IV** |
| `purchase_requests.cancel_approved` | Cancel **approved** PR (**D3**) | **Yes** | **IV** |
| `purchase_requests.manage_approval_config` | Matrices, SLA policies, authority ceilings | **Yes** | **IV** |
| `purchase_requests.manage_delegations` | Create/revoke delegations (**D4**) | **Yes** | **IV** |
| `purchase_requests.consolidate_planning` | Planning-only demand groups (mutate) | No | **V** (**V-D7**) |
| `purchase_requests.view_control_reports` | Control reports JSON **and CSV export** (RPT-V01…V04) | No | **V** (**V-D6**, **V-D8=A**) |

### Geographic catalog

| Permission | Purpose |
|---|---|
| `geo_places.view` | Read minimal catalog (may fold into `suppliers.view`) |
| `geo_places.manage` | **Platform-only** — tenants read-only in S04 (**D1** extensibility via platform ops, not tenant CRUD) |

---

## 2. Separation of duties (policy rules)

| Rule | Enforcement |
|---|---|
| Evaluation preparer ≠ approver | Policy + Action (**114**) |
| PR creator ≠ approver on any step | Policy (**126**) |
| Financial reviewer ≠ final approver on same PR version | **Hard block (D5)** — **Phase IV** Policy/Action; multi-role cannot bypass. Phase III **persists** `reviewer_id` only (**III-D2**/**III-D3**); creator may review |
| Delegation cannot complete final approve if acting user is financial reviewer | **Hard block (D5)** — **Phase IV** |
| No eligible independent final approver | Block + escalate to authorized admin; **never** auto-approve (**D5**) — **Phase IV** |
| Banking verify ≠ submitter of same change | Policy (**110**) |
| Document uploader ≠ verifier on same eligibility record | Policy (**II-A C3**) |
| Eligibility config author/editor ≠ approver of same version | Policy (**II-A C3**) |
| Delegation cannot raise ceiling above delegator | Action (**127**) |
| `cancel_approved` blocked when `procurement_engaged_at` set | Action (**D3**) |

Frontend `can()` is UX only.

---

## 3. Proposed role templates (D4) — **non-sensitive grants only**

Templates below are **documentation targets** for a future seed PR. They must **not** auto-include §3.1.

| Template | Proposed non-sensitive grants |
|---|---|
| Tenant Owner | `suppliers.view/create/update/transition/manage_documents/manage_eligibility_config/delete`; `supplier_evaluations.*` (view/prepare/approve/criteria/appeal); `purchase_requests.view/create/update/submit/cancel/verify_priority/consolidate_planning/view_control_reports`; `geo_places.view` — **not** `approve_eligibility_config` |
| General Manager | Same operational non-sensitive set as Owner for day-to-day ops visibility + supplier transition + eval approve — **not** `approve_eligibility_config` |
| Department Manager | `suppliers.view`; `purchase_requests.view/create/update/submit/cancel`; `supplier_evaluations.view` |
| Procurement officer | `suppliers.view/create/update/transition/manage_documents/manage_eligibility_config`; `supplier_evaluations.view/prepare`; `purchase_requests.view/consolidate_planning/verify_priority` — **not** `approve_eligibility_config` |
| Requester (dept) | `purchase_requests.view/create/update/submit/cancel`; `suppliers.view` |
| Auditor | `suppliers.view`; `supplier_evaluations.view`; `purchase_requests.view`; `purchase_requests.view_control_reports` — **no** banking |

### 3.1 Sensitive set — **explicit assignment only** (D4)

Never included in default template seeds (including Tenant Owner template for S04):

- `suppliers.approve_eligibility_config` (**II-A C6**)
- `suppliers.view_banking`
- `suppliers.manage_banking`
- `suppliers.verify_banking`
- `purchase_requests.approve`
- `purchase_requests.reject`
- `purchase_requests.return`
- `purchase_requests.financial_review`
- `purchase_requests.cancel_approved`
- `purchase_requests.manage_approval_config`
- `purchase_requests.manage_delegations`
- `geo_places.manage` (platform only)

**Assignment:** an authorized tenant administrator uses existing `roles.assign_permissions` (or equivalent) to grant sensitive verbs **explicitly**, subject to SoD checks at action time. Catalog presence ≠ grant.

---

## 4. Sensitive data

- Full IBAN/account reveal requires `suppliers.view_banking`.
- Audit stores masked banking values only.
- Control reports must not dump ciphertext.

---

## 5. Not in catalog (S04)

`rfq.*`, `purchase_orders.*`, `goods_receipts.*`, payment verbs, supplier portal verbs.

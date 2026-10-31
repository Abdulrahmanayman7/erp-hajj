# Suppliers & Procurement (S04) — Permissions

> **Status:** **ENGINEERING PROPOSAL** — not seeded until implementation authorization  
> **Last updated:** 2026-10-31  
> Rules: [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md) — `module.action`, deny-by-default, no Owner bypass, no direct user↔permission grants.  
> Owner **D4** (2026-10-31): sensitive permissions are **not** auto-granted via default role templates.  
> II-A design (**C6**, 2026-10-31): `suppliers.approve_eligibility_config` is **sensitive** — explicit assignment only; never default templates.

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
| `suppliers.view_banking` | Reveal unmasked bank details | **Yes** |
| `suppliers.manage_banking` | Create/update bank accounts; submit verification | **Yes** |
| `suppliers.verify_banking` | Approve/reject bank verification | **Yes** |
| `suppliers.delete` | Hard-delete eligible drafts only | No |

### Evaluations

| Permission | Purpose | Sensitive? |
|---|---|---|
| `supplier_evaluations.view` | View evaluations | No |
| `supplier_evaluations.prepare` | Create/edit draft evaluations | No |
| `supplier_evaluations.approve` | Approve/reject evaluation (≠ preparer) | No* |
| `supplier_evaluations.manage_criteria` | Criteria & weights per category | No |
| `supplier_evaluations.handle_appeal` | Objection / correction review | No |

\*SoD still enforced in Policy even if granted.

### Purchase requests

| Permission | Purpose | Sensitive? |
|---|---|---|
| `purchase_requests.view` | List/show PRs | No |
| `purchase_requests.create` | Create draft PR | No |
| `purchase_requests.update` | Edit draft / returned PR | No |
| `purchase_requests.submit` | Submit / resubmit | No |
| `purchase_requests.approve` | Act on approval steps within authority ceiling | **Yes** (financial approval) |
| `purchase_requests.reject` | Final reject at an approval step | **Yes** (paired with approve path) |
| `purchase_requests.return` | Return for revision | **Yes** (approval-path action) |
| `purchase_requests.cancel` | Cancel non-approved PRs | No |
| `purchase_requests.cancel_approved` | Cancel **approved** PR (**D3**) | **Yes** |
| `purchase_requests.financial_review` | Mandatory financial review (**D2**) | **Yes** |
| `purchase_requests.verify_priority` | Confirm urgent/emergency priority | No |
| `purchase_requests.consolidate_planning` | Planning-only demand groups | No |
| `purchase_requests.manage_approval_config` | Matrices, SLA policies, authority ceilings | **Yes** |
| `purchase_requests.manage_delegations` | Create/revoke delegations (**D4**) | **Yes** |
| `purchase_requests.view_control_reports` | Compliance / latency reports | No |

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
| Financial reviewer ≠ final approver on same PR version | **Hard block (D5)** — Policy/Action; multi-role cannot bypass |
| Delegation cannot complete final approve if acting user is financial reviewer | **Hard block (D5)** |
| No eligible independent final approver | Block + escalate to authorized admin; **never** auto-approve (**D5**) |
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

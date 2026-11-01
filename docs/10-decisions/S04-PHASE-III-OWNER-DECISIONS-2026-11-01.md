# S04 Phase III — Owner Decisions (III-D1–III-D8)

> **Status:** `APPROVED — OWNER DECISIONS` (documentation) — **all eight decisions**  
> **Owner decision date:** 2026-11-01  
> **Design pack:** [PHASE_III_PURCHASE_REQUESTS_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_III_PURCHASE_REQUESTS_DESIGN.md)  
> **Detailed design status:** `DRAFT — PENDING OWNER DESIGN ACCEPTANCE`  
> **Does not:** authorize coding, migrations, seeding, tests-as-implementation, commit, push, merge, deploy, Phase IV approvals, Phase V, or S05.  
> **Does not:** mark Phase III design OWNER ACCEPTED, IMPLEMENTED, or COMPLETE.  
> **Does not:** reopen ADR-0022 **101–130**, G1 **D1–D5**, II-D*, IIB-D*, IIC-D*/F*, or Phase I / II-A / II-B / II-C acceptance.

## 1. Context

Project Owner approved **III-D1–III-D8** (2026-11-01) to freeze Phase III Purchase Request product choices for detailed design. These decisions refine how ADR-0022 **117–123** and Owner **D2/D3/D5** apply when Phase III is delivered **without** the Phase IV approval engine.

**Phase III identity:** Purchase **Requests** (ADR-0022 **117–123** + financial review **records** per **D2**).  
**Not Phase III:** Multi-level approval matrices/steps/delegation/SLA (**124–130** → Phase IV); demand groups / control reports (Phase V); RFQ/PO/receive (S05).

## 2. Approved decisions — III-D1–III-D8

| ID | Choice | Decision text |
|---|---|---|
| **III-D1** | **A** | Valid `submit` transitions the PR to **`submitted`**. Phase III creates **no** approval instance, matrix snapshot routing, step, or authority assignment. Submitted PRs remain pending the Phase IV engine. Frontend must clearly communicate this pending state. |
| **III-D2** | **A** | Financial reviews may be recorded in Phase III **independently** of Phase IV. Review is allowed on **draft or submitted** PRs (subject to business rules). Each review binds to the applicable PR **version**. Phase IV **must** require a qualifying cleared review (`cleared` \| `cleared_with_conditions`) before **final** approval. |
| **III-D3** | **A** | The PR **creator may** perform financial review if they hold `purchase_requests.financial_review`. D5 still forbids that reviewer from being the **final** approver in Phase IV. Phase III must **not** implement approval transitions. |
| **III-D4** | **A** | Supplier on PR is **optional**. If selected: same-tenant, not archived, and applicable eligibility rules apply. Ineligible/invalid accessible refs → **422**; cross-tenant → fail closed (**404**, no existence leak). |
| **III-D5** | **A** | `beneficiary_organization_unit_id` must be an **active** org unit in the **current tenant**. No requester↔unit membership check and **no** org-unit row-level PR ACL in Phase III. |
| **III-D6** | **A** | Potential duplicates produce a **warning** requiring **explicit acknowledgement**. Similarity alone must **not** hard-block a valid request. |
| **III-D7** | **A** | PR versions are stored in **structured relational tables** (version header + version lines). Submitted/superseded historical versions are preserved immutable. |
| **III-D8** | **A** | Frontend must **not** show approve / reject / return controls before Phase IV. No placeholder workflows that imply approvals are functional. |

## 3. Operational rules (binding)

### III-D1=A — Submission without Phase IV

1. `POST …/submit` (valid draft/returned) → `status=submitted`, increment/bind version snapshot (**III-D7**).  
2. Do **not** write `matrix_id` / approval instance / steps in Phase III (leave null / absent).  
3. List/detail UX: Arabic copy that the request awaits the **approval engine** (Phase IV), not “approved”.  
4. Phase IV later consumes `submitted` (and returned resubmits) to start routing.

### III-D2=A — Independent financial review

1. `POST …/financial-reviews` allowed when PR is `draft` or `submitted` (and not cancelled).  
2. Persist `reviewer_id` = actor, `version_no` = current PR version, outcome, justification, `reviewed_at`.  
3. Multiple reviews per version: **Eng default** — latest review for the version is authoritative for Phase IV gate; prior rows retained for audit.  
4. Phase III does **not** auto-transition status based on review outcome.  
5. Phase IV final approve requires latest review for current version ∈ {`cleared`, `cleared_with_conditions`} (**D2**).

### III-D3=A — Requester may review financially

1. Policy: `purchase_requests.financial_review` + same tenant — **no** creator≠reviewer check in Phase III.  
2. Persist reviewer identity for Phase IV **D5** (`reviewer_id` ≠ final approver, including delegates).  
3. Do not expose or call approve/reject/return APIs in Phase III.

### III-D4=A — Supplier eligibility

1. Omit `optional_supplier_id` → OK.  
2. If present: same-tenant supplier; `status ≠ archived`; EligibilityService (II-A) must pass for “use on PR”.  
3. Cross-tenant id → **404** `PR_SUPPLIER_NOT_FOUND` (or equivalent tenant-safe code).  
4. Same-tenant ineligible/archived → **422** (`PR_SUPPLIER_ARCHIVED` / `PR_SUPPLIER_NOT_ELIGIBLE`).

### III-D5=A — Beneficiary org unit

1. Required on create; must resolve to same-tenant `OrganizationUnit` with **active** status.  
2. Inactive/missing same-tenant → **422** `PR_ORG_UNIT_INVALID`.  
3. Cross-tenant → **404**.  
4. No membership filter; no org-scoped listing beyond tenant + permissions.

### III-D6=A — Duplicate warning

1. Soft match on similar lines / season / need_date window (**Eng**: configurable lookback; suggested default 30 days — not an SLA).  
2. Submit may return warning payload; client must send acknowledgement (`acknowledge_duplicate_warning` / persist `duplicate_warning_acknowledged_at` on that version).  
3. Without acknowledgement when warnings exist → **422** `PR_DUPLICATE_ACK_REQUIRED`.  
4. Never silent-drop or hard-forbid solely due to similarity.

### III-D7=A — Structured versions

1. Tables: `purchase_request_versions` + `purchase_request_version_lines` (names Eng).  
2. On submit/resubmit: write immutable snapshot of header fields + lines for `version_no`.  
3. Working `purchase_request_lines` remain editable only in `draft` / `returned`.  
4. Historical version rows are immutable; corrections only via new version after return (Phase IV return) or Phase III cancel+new PR.

### III-D8=A — Frontend approval controls

1. No approve/reject/return buttons, routes, or disabled “coming soon” approve CTAs.  
2. Settings pages for matrices/SLA remain Phase IV (may omit nav until IV).  
3. Show submitted/pending-engine banner only.

## 4. Traceability

| Decision | Constrains |
|---|---|
| III-D1 | ADR-0022 **121** submit semantics; Phase IV handoff |
| III-D2 / III-D3 | Owner **D2**, **D5**; AC-P05 / AC-P05d (IV) |
| III-D4 | ADR-0022 §5; II-A eligibility; AC-P06 |
| III-D5 | ADR-0022 **117**; ADR-0023 deferred org ACL |
| III-D6 | ADR-0022 **123**; AC-P09 |
| III-D7 | ADR-0022 **121**/**128**; DATA_MODEL versions |
| III-D8 | UI.md; Phase IV boundary |

## 5. Preserved bindings

- Owner **D1–D5**, ADR-0022 **101–130**, ADR-0023 engineering shape.  
- No RFQ/PO/GRN/payments/budget reservation in S04.  
- `procurement_engaged_*` nullable lock columns allowed; S04 writers leave unset (**D3**).  
- Phase I / II-A / II-B / II-C acceptance statuses unchanged by this document.  
- Sensitive PR permissions remain explicit-only (**D4**).

## 6. Open Owner decisions after III-D1–D8

**None** among the eight Phase III product decisions. Remaining items marked **(Eng)** in the design pack are non-blocking unless a material ambiguity appears.

## 7. Governance after these decisions

| Item | Status |
|---|---|
| Phase III Owner decisions III-D1–D8 | **APPROVED** (2026-11-01) — **8/8** |
| Phase III detailed design | **DRAFT — PENDING OWNER DESIGN ACCEPTANCE** |
| Phase III coding / migrations | **NOT AUTHORIZED** |
| Phase IV / V / S05 | **NOT AUTHORIZED** |
| Commit / push / merge / deploy | **NOT AUTHORIZED** |

## 8. References

- [PHASE_III_PURCHASE_REQUESTS_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_III_PURCHASE_REQUESTS_DESIGN.md)  
- [ADR-0022](ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) **117–123**  
- [ADR-0023](ADR-0023-S04-ENGINEERING-DESIGN.md) §2.7 / D2–D5  
- Module [API.md](../09-modules/19-suppliers-and-procurement/API.md) §7 / §15  
- [BUSINESS_RULES.md](../09-modules/19-suppliers-and-procurement/BUSINESS_RULES.md) §3  

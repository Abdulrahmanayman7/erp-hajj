# S04 G2 Phase II-A — Eligibility & Documents Design

> **Status:** `COMPLETE — OWNER ACCEPTED` (2026-11-01) · design **APPROVED** · engineering gate **COMPLETE** · remediation re-audit **ACCEPTABLE**
> **Owner design approval date:** 2026-10-31
> **Owner acceptance:** [S04_PHASE_II_A_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_A_ACCEPTANCE_STATUS.md)
> **Owner remediation M1=A:** 2026-11-01 — [S04-PHASE-II-A-REMEDIATION-M1A-2026-11-01.md](../../10-decisions/S04-PHASE-II-A-REMEDIATION-M1A-2026-11-01.md)
> **Engineering gate:** [S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md](../../10-decisions/S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md)
> **Last updated:** 2026-11-01
> **Owner clarifications:** [II-D1–II-D4](../../10-decisions/S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md)
> **API contracts:** [API.md §12](API.md) · **Schema/constraints:** [DATA_MODEL.md](DATA_MODEL.md)
> **Builds on:** ADR-0022 (**107**, **109**), ADR-0023 §2.4, ADR-0010, Phase I accepted commit `58e5b49`
> **Slice:** II-A only (eligibility rules + Documents integration). Excludes banking (II-B), evaluations (II-C), PR engine (later phases).
> **II-B / II-C / Phase III–V / S05:** **NOT AUTHORIZED.**

Legend: **(Owner)** = II-D* / ADR-0022 / II-A binding controls; **(Eng)** = remaining technical detail consistent with Owner decisions; **(Owner TBD)** = would need a new Owner decision — none introduced silently.

---

## 0. Owner-approved binding engineering/security controls (2026-10-31)

The Project Owner approved the II-A Eligibility & Documents engineering design **including all six controls below**. These are **binding** for any future II-A implementation authorization.

| # | Control | Binding rule |
|---|---|---|
| **C1** | Immutable approved config versions | Once a configuration version is **approved**, it is **immutable**. Changes require a **new draft** and a **separate independent approval**. |
| **C2** | Applicability combination | Applicable requirements are combined by **supplier type**, **country**, and optional **category**, with **deduplication** of equivalent `requirement_key` satisfactions and **no implicit exemptions**. |
| **C3** | Separation of duties | (a) Document **uploader cannot verify** their own document. (b) Eligibility configuration **author/editor cannot approve** their own changes. |
| **C4** | Eligibility recheck timing | Recheck eligibility at **activation** and at **operational use-time**. Scheduled checks (e.g. expiry flips) are **supplementary only** — they do not replace activation/use-time gates and must **not** auto-change lifecycle status (**II-D3**). |
| **C5** | API contracts | Follow existing `/api/v1` conventions; **document exact endpoint contracts** (paths, payloads, errors) **before** implementation begins. |
| **C6** | Sensitive approve permission | Treat `suppliers.approve_eligibility_config` as a **sensitive** permission requiring **explicit assignment**. **Never** grant it through default role templates. |

---

## 1. Purpose

Replace Phase I’s **unconditional** activation hard-block with **real eligibility enforcement** without weakening fail-closed behavior:

- Keep stable code **`SUPPLIER_NOT_ELIGIBLE`** when checks fail.
- Never activate because “no requirements configured.”
- Integrate immutable Documents (ADR-0010) via eligibility metadata rows.
- Banking remains **out of activation** (**II-D2**).
- No automatic lifecycle status change on expiry (**II-D3**).

---

## 2. Functional rules (II-A)

### 2.1 Configuration pack (Owner II-D1 + **C1**)

| Concept | Rule |
|---|---|
| Ownership | Tenant-owned requirement definitions |
| Applicability keys | `supplier_type` (required) + optional `country_code` + optional `supplier_category_id` |
| Platform matrix | **Forbidden** — no universal mandatory list |
| Approval state | Each configuration **set/version** is `draft` \| `approved`; only **approved** versions are used for activation/eligibility |
| Immutability (**C1**) | Approved versions are **immutable**; edits = new draft + independent approval |
| Empty / missing / draft pack | Supplier is **not** eligible → `SUPPLIER_NOT_ELIGIBLE` (+ reason code) |
| Who publishes | `suppliers.approve_eligibility_config` (**C6** sensitive; SoD **C3** / **M5** editors table) |
| Mandatory floor (**Owner M1=A**) | Approve requires ≥1 `is_mandatory=true` def; evaluation never silently eligible when mandatory keys empty |

### 2.2 Applicability resolution (**C2**)

Given supplier `type`, `country_code`, and linked category ids:

1. Collect all requirement rows from **approved immutable** versions whose applicability matches:
   - type equals supplier type;
   - country is null (wildcard) **or** equals supplier country;
   - category is null (applies to all categories of that type/country) **or** equals one of the supplier’s linked categories.
2. Union mandatory keys; **deduplicate** by `requirement_key` — one valid satisfaction covers overlapping wildcard and specific rows.
3. **No implicit exemptions** — absence of a matching row for a key does not waive another mandatory key; zero approved applicable rows → **ineligible** (fail closed).
4. Operational implication of fail-closed: activation cannot succeed until an applicable approved version exists.

### 2.3 Document records & verification (**C3**, **C4**)

| Field / state | Rule |
|---|---|
| Link | `document_id` → immutable `documents` row (ADR-0010) |
| Eligibility statuses (**Eng** names) | `pending_verification` \| `valid` \| `rejected` \| `expired` \| `superseded` |
| Activation-quality docs | Only `valid` (or stale `expired` status) with live `expires_at` OK **and** intact backing Document (**H1**: active, same tenant, still linked to this supplier) |
| Verification SoD (**C3**) | Verifier **must not** be the uploader of the same document record |
| Replacement | New Document upload + new eligibility row; prior row → `superseded` via `supersedes_id` |
| Expiry | See §2.3.1 — live timestamp evaluation; scheduled flip supplementary (**C4**); **no** lifecycle auto-change (**II-D3**) |

### 2.3.1 Expiration semantics (engineering gate — locked)

1. Eligibility gates (activation and operational use-time) evaluate against **`expires_at`** and tenant-local **calendar today** (`tenants.timezone`, fallback `Asia/Riyadh`), not solely against stored `verification_status`.
2. Inclusive rule: document remains non-expired through end of calendar day `expires_at`; expired when tenant-local date **>** `expires_at`.
3. A stale stored `valid` **cannot** override an elapsed `expires_at`. A stale stored `expired` **cannot** override a still-valid `expires_at` (e.g. after clock/timezone correction) — recompute wins.
4. Scheduled job may set `verification_status=expired` for UX/reporting — **supplementary only** (**C4**).
5. Expiration **never** auto-transitions supplier lifecycle (**II-D3**).
6. Activation/use-time failures preserve envelope code **`SUPPLIER_NOT_ELIGIBLE`** (with reason `SUPPLIER_DOCUMENT_EXPIRED` in `reasons[]` when applicable).

### 2.4 Activation (lifecycle) (**C4**) — locked for II-A

Phase I today: `SupplierLifecycle::assertAllowed` throws `SUPPLIER_NOT_ELIGIBLE` for **any** `to=active`, and the Phase I `allowedTransitions()` map contains **no** inbound edges to `active`.

**II-A approved inbound transition (sole):**

| From | To | Gate |
|---|---|---|
| `under_review` | `active` | EligibilityService + `suppliers.transition` |

**Requirements before success:**

1. Actor has `suppliers.transition`; Policy allow.
2. DB transaction + `lockForUpdate` on supplier.
3. Graph allows only the edge above (replace Phase I unconditional Active hard-block with eligibility-gated edge).
4. Approved applicable immutable config + complete verified currently-valid mandatory docs (**II-D1**, **C1–C4**); banking **not** required (**II-D2**).
5. Set `status=active`, `activated_at=now()` (UTC instant); transition history + audit.
6. Failure → `422 SUPPLIER_NOT_ELIGIBLE` + `reasons[]`.
7. **No** activation via PATCH/update/other endpoints.

**Owner follow-up (not invented):** `temporarily_suspended → active` is **not** in Phase I graph and is **not** authorized for II-A without a new Owner decision. Current Phase I path from suspension is only → `blocked` / `archived`.

### 2.5 Use-time eligibility (future PRs) — design now (**C4**)

When a PR (or other future consumer) **references** a supplier (**II-D3** / **109**):

- Supplier must be `active` **and** currently eligible under the same rules (valid mandatory docs + approved config) — **operational use-time recheck**.
- Failure → block linking/use with `SUPPLIER_NOT_ELIGIBLE` or `SUPPLIER_DOCUMENT_EXPIRED` as appropriate — **do not** auto-suspend.
- Scheduled expiry jobs may update document-record status and emit warnings; they **do not** replace this gate.

Until PR module exists, implement the shared `EligibilityService` and call it from activation; document the PR hook as a mandatory consumer contract.

### 2.6 Explicit non-goals for II-A

- Bank accounts / verify (**II-B**).
- Evaluations / criteria packs (**II-C**; **II-D4** recorded but not built in II-A).
- Purchase requests / approval engine.
- Automatic suspension jobs that mutate lifecycle status.

---

## 3. Documents integration (ADR-0010)

| Topic | Design |
|---|---|
| Morph alias | Add `supplier` to `DocumentLinkableType` (**Eng** name) |
| Upload path | Existing Documents upload API + link to supplier; eligibility row created/updated in supplier domain |
| Immutability | Never mutate file bytes or Document “version”; supersede eligibility only |
| Host delete | Extend `DocumentReferenceValidator` / supplier delete guards: block supplier hard-delete while eligibility docs linked (**Eng**, parity with Contracts/Inventory) |
| Download | Existing `documents.download` Policy; no path leakage |
| Alias `supplier_evaluation` | Deferred to II-C |
| Alias `purchase_request` | Deferred to PR phases |

---

## 4. Proposed schema (additive — not authored)

### 4.1 `supplier_eligibility_requirement_sets` (tenant-owned)

| Column | Notes |
|---|---|
| `tenant_id`, `name`, `status` (`draft`/`approved`), `approved_at`, `approved_by`, `version_no` | **C1:** approve freezes version; further edits require new draft row/version |
| `authored_by` / last editor | Used for SoD on approve (**C3**) |
| timestamps | |

### 4.2 `supplier_document_requirement_defs` (tenant-owned)

| Column | Notes |
|---|---|
| `tenant_id`, `requirement_set_id` (FK) | Belongs to one versioned set |
| `requirement_key` | Stable string key within set |
| `label_ar` / description | |
| `supplier_type` | Required |
| `country_code` | Nullable = wildcard |
| `supplier_category_id` | Nullable = wildcard |
| `is_mandatory` | Boolean (II-A focuses on mandatory) |
| `default_validity_days` | Nullable guidance only |
| timestamps | |
| Unique | `(tenant_id, requirement_set_id, requirement_key, supplier_type, country_code, supplier_category_id)` with null-safe strategy (**Eng**) |

### 4.3 `supplier_document_records` (tenant-owned)

| Column | Notes |
|---|---|
| `tenant_id`, `supplier_id`, `requirement_def_id` | |
| `document_id` | FK documents |
| `verification_status` | pending_verification / valid / rejected / expired / superseded |
| `expires_at` | DATE NULL |
| `uploaded_by`, `verified_by`, `verified_at`, `rejection_reason` | SoD: `verified_by` ≠ `uploaded_by` (**C3**) |
| `supersedes_id` | Self FK |
| timestamps | |

### 4.4 Migration order (**Eng**)

1. requirement_sets
2. requirement_defs
3. document_records
4. (no change to Contracts/Inventory tables)

Rollback: drop in reverse; non-destructive additive ups.

---

## 5. Permissions (**C3**, **C6**; G1 D4 sensitive pattern)

| Permission | Purpose | Sensitive? | Template seed? |
|---|---|---|---|
| `suppliers.manage_documents` | Link docs / request verification / supersede | No | Allowed on Owner/procurement (per PERMISSIONS) when Phase II seeds |
| `suppliers.manage_eligibility_config` | Draft/edit requirement sets (new drafts) | No | May seed on Owner/procurement templates (**Eng**) |
| `suppliers.approve_eligibility_config` | Approve config version | **Yes — C6** | **Never** via default role templates; **explicit assignment only** |
| Existing `documents.upload|view|download|…` | File bytes | Existing | Unchanged |
| Banking / eval perms | Out of II-A | — | Do not seed in II-A |

SoD enforcement (**C3**) is application-enforced in addition to permission checks (same actor may hold both perms but cannot approve own draft / verify own upload).

---

## 6. API contracts (**C5**) — locked

Exact contracts: **[API.md §12](API.md)** (paths, JSON shapes, HTTP status, reason codes, audit events, Documents upload integration).

---

## 7. Audit events (**Eng**)

`SUPPLIER_ELIGIBILITY_CONFIG_CREATED|UPDATED|APPROVED`
`SUPPLIER_DOCUMENT_RECORD_CREATED|SUPERSEDED|VERIFIED|REJECTED`
`SUPPLIER_TRANSITIONED` (existing) with eligibility snapshot ids (no file secrets)
`SUPPLIER_ELIGIBILITY_CHECK_FAILED` (blocked activation / use-time)
`SUPPLIER_SOD_VIOLATION` (optional security audit)

---

## 8. Arabic RTL UI (II-A behavior — design only)

- Eligibility status banner on supplier details (eligible / ineligible + reasons).
- Requirements checklist vs uploaded/verified docs.
- Upload via existing Documents patterns; verification actions permission-gated + SoD.
- Config screens for requirement sets (draft → approve by different user; approved versions read-only).
- Activation CTA: call transition; surface `SUPPLIER_NOT_ELIGIBLE` reasons (no fake success).
- Banking/eval tabs remain Phase II-B/C placeholders until those slices.

---

## 9. Security & regression test matrix (required at II-A implementation)

| # | Case | Expect |
|---|---|---|
| T1 | Phase I supplier suite still green | PASS |
| T2 | Documents `DocumentTest` D01–D12 isolated batch | PASS |
| T3 | Contracts + Inventory regression batches | PASS |
| T4 | Cross-tenant config/docs/activation | 404 |
| T5 | Unauthorized manage/approve/transition | 403 |
| T5b | Default templates lack `approve_eligibility_config` (**C6**) | PASS |
| T5c | Author cannot approve own draft; uploader cannot verify own doc (**C3**) | 403 / SoD code |
| T6 | No approved config → cannot activate | `SUPPLIER_NOT_ELIGIBLE` |
| T7 | Draft-only config → cannot activate | fail closed |
| T7b | Approved version immutable; mutate attempt fails (**C1**) | 422 / 403 |
| T8 | Missing / expired / rejected / pending / superseded mandatory | block activation |
| T9 | Valid verified docs + approved config → activate OK | 200 + `active` |
| T9b | Use-time eligibility recheck fails after expiry without lifecycle change (**C4**, **II-D3**) | block use; status unchanged |
| T10 | Supersede preserves prior Document + prior eligibility row | immutable history |
| T11 | Expiry does **not** auto-change lifecycle status | II-D3 |
| T12 | Activation does **not** require bank account | II-D2 |
| T13 | `SUPPLIER_NOT_ELIGIBLE` still returned on failure | no regression |
| T14 | Morph `supplier` host-delete guard | entity in use |

**Phase I gap (documented, not reopened):** primary-contact multi-process race test not executed; locks + unique remain. Optional hygiene before II-B bank primary uniqueness.

---

## 10. Distinction table

| Item | Class |
|---|---|
| II-D1–II-D4 | **Owner approved** |
| Binding controls **C1–C6** | **Owner approved** (2026-10-31) |
| Fail closed without config; keep `SUPPLIER_NOT_ELIGIBLE` | **Owner** (II-D1) |
| Banking not required for activation | **Owner** (II-D2) |
| No auto-suspend on expiry | **Owner** (II-D3) |
| Exact REST contracts | **Locked** — API.md §12 (**C5**) |
| Expiry calendar + live recheck | **Locked** — §2.3.1 (**C4**) |
| Inbound to `active` for II-A | **Locked** — `under_review` → `active` only |
| `temporarily_suspended` → `active` | **Owner follow-up** — not authorized |
| NULL-safe unique indexing | **Locked** — physical `*_norm` columns + UNIQUE; MySQL/MariaDB triggers (migration `190015`; MariaDB Hostinger cannot use STORED GENERATED with IFNULL here); SQLite model hook — see DATA_MODEL |
| Numeric default validity days mandated platform-wide | **Not authorized** — guidance only |
| Platform-wide document matrix | **Forbidden** (II-D1) |

---

## 11. Stop gate for future II-A coding authorization

**Engineering pre-implementation gate:** COMPLETE (2026-10-31).

Owner must separately authorize **II-A implementation only**, naming:

- Branch from documentation commit that includes this gate (successor of Phase I `58e5b49`).
- Dev/test DB only.
- Implement per API.md §12 + DATA_MODEL constraints + C1–C6.
- No II-B/II-C, no S05, no push/merge without further authorization.
- Sensitive `approve_eligibility_config` never template-seeded (**C6**).
- Do not add `temporarily_suspended` → `active` without Owner decision.

Until that act: **coding remains NOT GRANTED.**

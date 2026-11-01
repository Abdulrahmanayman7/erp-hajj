# S04 G2 Phase II-C — Supplier Evaluations Design

> **Status:** `DESIGN DRAFT — ALL OWNER DECISIONS IIC-D1–D8 + IIC-F1–F3 APPROVED — DETAILED DESIGN NOT YET OWNER ACCEPTED`  
> **Owner decisions:** 2026-11-01 — [S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md) (**11/11**)  
> **API contracts:** [API.md §14](API.md)  
> **Last updated:** 2026-11-01  
> **Builds on:** ADR-0022 **112–116**, ADR-0023 §2.6, **II-D3**, **II-D4**, Phase I suppliers/categories, Phase II-A Documents patterns, Notifications (ADR-0013)  
> **Excludes:** Phase III PR, Phase IV approval engine, S05 RFQ/PO, payments, accounting, banking changes, platform default criteria  
> **Coding / migrations / commit / push / merge / deploy:** **NOT AUTHORIZED**  
> **Phase II-C implementation status:** **NOT_STARTED**

Legend: **(Owner)** = IIC-D* / IIC-F* / II-D* / ADR binding; **(Eng)** = non-blocking technical recommendation consistent with Owner decisions (not silently Owner-approved).

---

## 0. Binding Owner decisions (2026-11-01)

| # | Choice | Rule |
|---|---|---|
| **IIC-D1** | A | Criteria: Draft → independent approval → **immutable published** version. Only published criteria used for scoring. Hard SoD prep≠approve. **No** platform default pack. |
| **IIC-D2** | A | Active weights sum **100 ±0.01**; scores **0–100**; **snapshot** at evaluation **submit**; later criteria edits cannot alter submitted/approved scores. |
| **IIC-D3** | A | Manual `monitoring_recommended`; **no** platform threshold; **no** auto lifecycle; monitoring ≠ supplier status. |
| **IIC-D4** | A | Appeals register; accepted correction → **new** evaluation version; approved versions immutable; independent appeal review. |
| **IIC-D5** | A+C | Supplier not archived; category associated with supplier; season required for **selected** kinds (**IIC-F1**). |
| **IIC-D6** | A | Dedicated `/app/supplier-evaluations` **and** supplier-details evaluations tab. |
| **IIC-D7** | A | Internal notification when monitoring recommendation recorded; Notifications module; no external channel (**IIC-F2**/ **IIC-F3** refine timing/recipients). |
| **IIC-D8** | A | Optional evidence Documents; not mandatory per evaluation. |
| **IIC-F1** | A | Only `post_season` requires `season_id`; other kinds optional; same-tenant season validation. |
| **IIC-F2** | A | Notify only same-tenant holders of `supplier_evaluations.approve` (not broad supplier roles alone). |
| **IIC-F3** | A | Preparer may propose monitoring; approver finalizes on approve; notify only on approved final `true`; immutable thereafter. |

### Preserved clarifications

| # | Rule |
|---|---|
| **II-D3** | Low evaluation / expiry never auto-changes supplier lifecycle. |
| **II-D4** | Tenant configures criteria before first evaluation; no platform pack; no platform numeric threshold; validate approved criteria before scoring. |

---

## 1. Business scope and exclusions

### In scope (II-C)

- Tenant-owned evaluation **criteria packs** per supplier category (versioned publish).  
- Supplier **evaluations**: draft, submit, approve, reject.  
- Weighted scoring with immutable score snapshots after submit.  
- Appeals / corrections producing new evaluation versions.  
- Manual monitoring recommendation + in-app notification.  
- Optional evidence Documents.  
- Arabic RTL UI (list route + supplier tab).  
- Audit of critical transitions.

### Out of scope

- Purchase requests / financial review / D5 SoD (Phase III+).  
- Approval matrices / SLA / delegation (Phase IV).  
- S05 RFQ/PO/receive; payments; accounting.  
- Auto-suspend / auto-block on score.  
- Supplier self-service portal (**116**).  
- Platform default criteria or platform low-score threshold.  
- Changing Phase I/II-A/II-B accepted behavior beyond additive eval surfaces.  
- External email/SMS/push for monitoring alerts.

---

## 2. Domain model

### 2.1 Concepts

| Concept | Meaning |
|---|---|
| **Criteria pack version** | One versioned set of weighted criteria for a `(tenant, supplier_category)` |
| **Published pack** | Immutable pack with `status=published` usable for new evaluations |
| **Evaluation** | One scored assessment of a supplier for a category/kind |
| **Score snapshot** | Frozen criterion id/name/weight + awarded score rows captured at **submit** |
| **Appeal** | Formal objection against an **approved** evaluation; may spawn a correction version |
| **Monitoring recommendation** | Boolean descriptor on evaluation (**115**); not a supplier status |

### 2.2 Table: `supplier_evaluation_criteria_packs` (tenant-owned) **(Eng)**

| Column | Notes |
|---|---|
| `id` | BIGINT PK |
| `tenant_id` | FK; never from request |
| `supplier_category_id` | FK `supplier_categories` same tenant |
| `version_no` | UNSIGNED INT ≥ 1; monotonic per `(tenant_id, supplier_category_id)` |
| `status` | `draft` \| `published` \| `archived` |
| `prepared_by` | FK users |
| `approved_by` / `approved_at` | NULL until publish |
| `supersedes_pack_id` | Self FK NULL |
| `notes` | TEXT NULL |
| timestamps | |

**Rules (Owner IIC-D1):**

1. Only `draft` is editable.  
2. Publish requires independent approver ≠ preparer **and** ≠ any recorded pack editor (**SoD**).  
3. On publish: validate active lines weight sum ∈ [99.99, 100.01]; require ≥1 active criterion; set immutable.  
4. At most **one** `published` pack per `(tenant_id, supplier_category_id)` at a time **(Eng)**: publishing a new pack sets prior published → `archived` (immutable history retained).  
5. **No** platform seed pack.

**Editors table** `supplier_evaluation_criteria_pack_editors` **(Eng):** `(pack_id, user_id)` additive SoD set (parallel II-A M5 / II-B editors).

### 2.3 Table: `supplier_evaluation_criteria` (tenant-owned; lines of a pack)

| Column | Notes |
|---|---|
| `id` | BIGINT PK |
| `tenant_id` | |
| `pack_id` | FK packs |
| `name` | VARCHAR required |
| `weight` | DECIMAL(8,4) — percentage points; sum of **active** lines = 100 ±0.01 |
| `sort_order` | INT |
| `is_active` | BOOL |
| timestamps | |

Inactive lines excluded from sum and from new snapshots.

### 2.4 Table: `supplier_evaluation_number_sequences`

`(tenant_id PK, next_value)` — pattern of `supplier_number_sequences` with `FOR UPDATE`.

### 2.5 Table: `supplier_evaluations` (tenant-owned)

| Column | Notes |
|---|---|
| `id` | BIGINT PK |
| `tenant_id` | |
| `evaluation_number` | `SEV-######` immutable |
| `supplier_id` | FK suppliers same tenant |
| `supplier_category_id` | FK; **must** be linked to supplier via category coverage/link table used in Phase I |
| `evaluation_kind` | `qualification` \| `periodic` \| `operational` \| `post_season` |
| `season_id` | Nullable FK seasons; **required iff** `evaluation_kind=post_season` (**IIC-F1**); same-tenant |
| `status` | `draft` \| `pending_approval` \| `approved` \| `rejected` |
| `criteria_pack_id` | FK pack used (must be `published` at bind time) |
| `criteria_pack_version_no` | Denormalized snapshot of pack `version_no` at bind/submit |
| `total_score` | DECIMAL(6,2) NULL until submit; then 0–100 |
| `monitoring_recommended` | BOOL default false (**IIC-D3**) |
| `monitoring_set_by` / `monitoring_set_at` | NULL until first true **(Eng)** |
| `prepared_by` | FK users |
| `submitted_at` | NULL until submit |
| `approved_by` / `approved_at` | |
| `rejected_by` / `rejected_at` / `rejection_reason` | |
| `version_no` | INT ≥ 1 within correction family |
| `evaluation_family_key` | UUID string **(Eng)** — stable across correction versions |
| `supersedes_evaluation_id` | Self FK NULL — prior version this corrects |
| `origin_appeal_id` | FK appeals NULL — set when created from accepted appeal |
| timestamps | |

**Uniques:** `(tenant_id, evaluation_number)`; `(tenant_id, supplier_id, evaluation_family_key, version_no)`.

### 2.6 Table: `supplier_evaluation_score_snapshots` (tenant-owned)

Captured **at submit** (**IIC-D2**). Immutable after insert.

| Column | Notes |
|---|---|
| `id` | |
| `tenant_id` | |
| `evaluation_id` | FK |
| `source_criterion_id` | Original criteria line id (informational; may later be archived) |
| `criterion_name` | Snapshot string |
| `weight` | Snapshot DECIMAL(8,4) |
| `score` | DECIMAL(6,2) 0–100 awarded for this criterion |
| `note` | TEXT NULL |
| `sort_order` | |

**Formula (Eng — binding for design acceptance unless Owner revises):**

\[
total = \mathrm{round}_{0.01}\!\left(\sum_i score_i \times \frac{weight_i}{100}\right)
\]

- Intermediate products use DECIMAL; final `total_score` half-up (PHP `ROUND_HALF_UP` / SQL `ROUND`) to **2** decimal places.  
- Clamp display to `[0, 100]`; reject input scores outside `[0, 100]`.  
- Snapshot weights must sum to 100 ±0.01 at submit or fail `EVALUATION_WEIGHTS_INVALID`.

### 2.7 Table: `supplier_evaluation_appeals` (tenant-owned) (**IIC-D4**)

| Column | Notes |
|---|---|
| `id` | |
| `tenant_id` | |
| `evaluation_id` | FK — must reference `status=approved` evaluation |
| `reason` | TEXT required; trimmed length ≥ 10 **(Eng)** |
| `status` | `open` \| `accepted` \| `rejected` |
| `filed_by` / `filed_at` | |
| `reviewed_by` / `reviewed_at` | |
| `review_outcome_note` | TEXT NULL |
| `resulting_evaluation_id` | FK NULL — new draft/version when accepted |
| timestamps | |

### 2.8 Evidence links (**IIC-D8**)

| Approach | Rule |
|---|---|
| Morph | Add Documents alias `supplier_evaluation` **(Eng)** pointing at `supplier_evaluations.id` |
| Optionality | Zero or more documents; **not** required to submit/approve |
| Ownership | Document tenant = evaluation tenant; link target must resolve same tenant |
| Retention | Links remain on historical evaluation versions; superseding versions do not delete prior links |
| Visibility | Requires `supplier_evaluations.view` **and** Documents view rules for the file |

Optional junction `supplier_evaluation_evidence` **(Eng alternative)** if morph alone is insufficient for ordered captions — prefer morph-first to reuse ADR-0010.

### 2.9 Sequences & concurrency

- Allocate `evaluation_number` inside transaction with `FOR UPDATE` on sequence row (supplier number pattern).  
- Publish pack: lock category’s packs; enforce single published.  
- Submit evaluation: lock evaluation row; re-read pack `published`; build snapshots atomically.  
- Approve/reject: lock evaluation; assert `pending_approval`; SoD check; idempotent if already terminal with same actor outcome **(Eng)**: second approve → `409 EVALUATION_INVALID_STATUS` or no-op with identical state — prefer **409** for clarity.  
- Appeal accept: lock approved eval + open appeal; create new evaluation version in same family; link `origin_appeal_id`.

---

## 3. State machines

### 3.1 Criteria pack

```
draft ──publish──► published ──(new publish of successor)──► archived
  ▲                     │
  └── edit only while draft
```

- `archived` and `published` are immutable.  
- New draft may `supersedes_pack_id` → prior published.

### 3.2 Evaluation

```
draft ──submit──► pending_approval ──approve──► approved
                         │
                         └──reject──► rejected
```

- `draft`: preparer edits scores/notes/kind/season; may **propose** `monitoring_recommended` (**IIC-F3**); may attach optional evidence. Draft monitoring must **not** notify.  
- `submit`: validates preconditions + pack + weights + snapshots + season rules (**IIC-F1**); sets `submitted_at`; **no** monitoring notification.  
- `pending_approval`: **not** editable. **Default (Eng):** no withdraw-to-draft in MVP II-C; reject/approve only.  
- `approve`: independent approver sets **final** `monitoring_recommended` (**IIC-F3**); if final true → emit notification after commit (**IIC-F2**); never mutate supplier lifecycle.  
- `approved` / `rejected`: immutable scores, snapshots, and final monitoring flag.  
- Correction path: appeal **accepted** → new `draft` with `version_no+1`, `supersedes_evaluation_id` — **not** a mutation of prior row.

### 3.3 Appeal

```
open ──accept──► accepted (spawns new evaluation version)
  └──reject──► rejected
```

- File only against `approved` evaluations.  
- Reviewer ≠ `filed_by` and ≠ original `prepared_by` **(Eng SoD)**; must hold `supplier_evaluations.handle_appeal`.  
- At most one `open` appeal per evaluation **(Eng)**.

---

## 4. Preconditions (**IIC-D5**)

| Check | Rule |
|---|---|
| Supplier | Exists in tenant; `status ≠ archived` |
| Category | `supplier_category_id` must be associated with supplier (Phase I category link/coverage) |
| Pack | Category must have a `published` criteria pack; else fail closed `EVALUATION_CRITERIA_MISSING` |
| Season | Per **IIC-F1** (binding map below); if present, same-tenant Season |
| Kind | One of the four enums |

### 4.1 Kind → season map — **IIC-F1=A (OWNER APPROVED)**

| Kind | Season rule (**Owner**) |
|---|---|
| `qualification` | Optional |
| `periodic` | Optional |
| `operational` | Optional |
| `post_season` | **Required** — valid same-tenant `season_id` |

No additional kinds require season. Cross-tenant season ids → **404**.

---

## 5. Permissions and SoD

### 5.1 Permission catalog (to seed when coding authorized)

| Permission | Purpose | Sensitive? **(Eng)** |
|---|---|---|
| `supplier_evaluations.view` | List/show | No |
| `supplier_evaluations.prepare` | Create/edit draft; submit | No |
| `supplier_evaluations.approve` | Approve/reject pending | **Yes** — explicit grant (**D4** spirit) |
| `supplier_evaluations.manage_criteria` | Draft criteria packs/lines | No |
| `supplier_evaluations.approve_criteria` | Publish criteria pack | **Yes** — explicit grant |
| `supplier_evaluations.handle_appeal` | Review appeals | **Yes** — explicit grant |

**Not** auto-granted via default role templates for sensitive verbs (**ADR-0023 D4**).

### 5.2 Hard SoD

| Action | Forbidden when |
|---|---|
| Publish criteria pack | Actor ∈ pack preparer/editors |
| Approve evaluation | Actor = `prepared_by` |
| Reject evaluation | Actor = `prepared_by` (same SoD) |
| Accept/reject appeal | Actor = `filed_by` **or** evaluation `prepared_by` **(Eng)** |

Policy enforces even if user holds multiple permissions (no persona bypass).

---

## 6. Monitoring recommendation (**IIC-D3**, **IIC-D7**, **IIC-F2**, **IIC-F3**)

| Topic | Design (**Owner**) |
|---|---|
| Field | `monitoring_recommended` boolean on evaluation |
| Proposal | Preparer **MAY** set on draft / before approve (**IIC-F3**) — **does not notify** |
| Final | Approver determines final value on **approve**; immutable on approved version (**IIC-F3**) |
| Lifecycle | Setting true **never** calls supplier transition APIs (**IIC-D3**, **II-D3**) |
| Audit | Audit proposed changes on draft; audit final value on approve (actor, evaluation id, flag, correlation ID) |
| Notification | **Only** after successful **approve** when final flag is `true` (**IIC-F3** + **IIC-D7**) |

### 6.1 Notification contract

| Item | Rule |
|---|---|
| Type key | `SUPPLIER_EVALUATION_MONITORING_RECOMMENDED` (add to `NotificationType`) **(Eng name)** |
| Timing (**Owner**) | After successful DB commit of **approve** with final `monitoring_recommended=true` |
| Recipients (**Owner IIC-F2**) | Same-tenant active users holding explicit `supplier_evaluations.approve` only — **not** `suppliers.transition` alone or other broad roles |
| Actor exclusion | Exclude approving actor from recipients **(Eng — non-blocking)** |
| Idempotency | Dedupe key `(tenant_id, evaluation_id, type)` via existing `NotificationDedupe` — one family per evaluation |
| Channels (**Owner**) | In-app only — no email/SMS/external |
| Payload | evaluation id/number, supplier id/number, total_score if present — **no** secrets |
| Failure | Notification failure must not roll back approve (**Eng** ops expectation — queue/best-effort after commit) |

---

## 7. Appeals & corrections (**IIC-D4**)

1. Actor with `handle_appeal` **or** `prepare` may **file** appeal on `approved` evaluation (filing permission: Eng default = `prepare` **or** `handle_appeal`).  
2. Reviewer with `handle_appeal` accepts/rejects.  
3. **Accept:** create new evaluation `draft` in same `evaluation_family_key`, `version_no = prior+1`, `supersedes_evaluation_id = prior`, `origin_appeal_id` set; copy supplier/category/kind/season defaults; **do not** copy prior score snapshots as final — preparer must re-enter scores against **current published** pack (or frozen reference pack id optional). **Eng default:** new draft binds to **current published** pack for the category (may differ from original); UI warns if pack version changed.  
4. Prior approved row remains readable/immutable.  
5. Reject appeal: no new evaluation; audit outcome.

---

## 8. API contracts (summary)

Authoritative detail: **[API.md §14](API.md)**. Summary:

| Area | Paths |
|---|---|
| Criteria packs | `GET/POST /supplier-evaluation-criteria-packs`, `GET/PATCH /{id}`, `POST /{id}/publish`, `POST /{id}/new-draft` (Eng — copy active lines into next draft; detail in API.md §14.1) |
| Criteria lines | Nested under pack or `…/criteria` |
| Evaluations | `GET/POST /supplier-evaluations`, `GET/PATCH /{id}`, `POST /{id}/submit\|approve\|reject` |
| Appeals | `GET/POST /supplier-evaluations/{id}/appeals`, `POST /appeals/{id}/accept\|reject` |
| Evidence | Documents upload + link `type=supplier_evaluation` |

Errors: `EVALUATION_SOD_VIOLATION`, `EVALUATION_WEIGHTS_INVALID`, `EVALUATION_NOT_DRAFT`, `EVALUATION_CRITERIA_MISSING`, `EVALUATION_SUPPLIER_ARCHIVED`, `EVALUATION_CATEGORY_NOT_LINKED`, `EVALUATION_SEASON_REQUIRED`, `EVALUATION_INVALID_STATUS`, `EVALUATION_APPEAL_NOT_OPEN`, `CRITERIA_PACK_SOD_VIOLATION`, `CRITERIA_PACK_NOT_DRAFT`, …

Auth: `401` unauthenticated; `403` missing permission; `404` cross-tenant / unknown; `409` conflicts; `422` validation/domain.

---

## 9. UI / RTL (**IIC-D6**)

| Surface | Behavior |
|---|---|
| Nav | «تقييم الموردين» → `/app/supplier-evaluations` if `supplier_evaluations.view` |
| List | Filters: supplier, kind, status, season, monitoring flag, category; Arabic columns |
| Detail / form | Criteria weight table (read-only from snapshot after submit); score inputs 0–100; monitoring checkbox; optional evidence |
| SoD UX | Hide/disable اعتماد if current user is preparer |
| Criteria admin | Separate settings drawer/page gated by `manage_criteria` / `approve_criteria` |
| Supplier tab | Replace Phase II lock placeholder; deep-link to evaluation detail |
| RTL | Mirror existing suppliers module patterns (II-A/II-B) |

---

## 10. Audit events (domain)

Emit via AuthorizationSecurity / module audit helpers (no secrets):

- `CRITERIA_PACK_CREATED` / `_UPDATED` / `_PUBLISHED`  
- `EVALUATION_CREATED` / `_UPDATED` / `_SUBMITTED` / `_APPROVED` / `_REJECTED`  
- `EVALUATION_MONITORING_SET`  
- `EVALUATION_APPEAL_FILED` / `_ACCEPTED` / `_REJECTED`  
- `EVALUATION_EVIDENCE_LINKED` (document id only)

Include `tenant_id`, actor, correlation ID, evaluation/pack ids, version numbers.

---

## 11. Acceptance & test matrix (AC-E01–E05 + security)

| ID | Requirement | Design enforcement | Tests (when authorized) |
|---|---|---|---|
| AC-E01 | Weighted 0–100; history | Snapshot + packs | Score formula; pack history; immutability after submit |
| AC-E02 | Kinds + season rules | Kind enum + **IIC-F1=A** | `post_season` requires season; others optional; same-tenant season |
| AC-E03 | Preparer ≠ approver | Policy SoD | Positive + negative |
| AC-E04 | Monitoring/alerts; no auto status | Final flag on approve + notify if true (**IIC-F2/F3**); no lifecycle | Draft proposal does not notify; approve+true notifies once; status unchanged |
| AC-E05 | Appeal → new version; no portal | Appeals table | Accept spawns version; prior immutable |
| SEC-T1 | Cross-tenant 404 | Tenant scope | Pack/eval/appeal |
| SEC-T2 | Criteria prep/approve SoD | Editors + approve_criteria | |
| SEC-T3 | Appeal independence | handle_appeal SoD | |
| SEC-T4 | Missing/unpublished criteria fail-closed | | |
| SEC-T5 | Weight ±0.01 boundaries | 99.98 fail; 99.99/100.00/100.01 pass | |
| SEC-T6 | Duplicate submit/approve races | Locks + 409 | MySQL concurrency |
| SEC-T7 | Evidence ownership | Document tenant/target checks | |
| SEC-T8 | FE RTL permission CTAs | Vitest | |

---

## 12. Proposed work packages (non-executable)

| WP | Objective | Dependencies | Deliverables | Complexity |
|---|---|---|---|---|
| **WP-01** | Criteria foundations | Design acceptance + coding auth; **IIC-D1/D2** | Migrations packs/lines/editors; publish Action; permissions | M |
| **WP-02** | Evaluation domain | WP-01; **IIC-D3/D5/F1/F3** | Evaluations, snapshots, sequence, submit/approve/reject; season rules; monitoring finalization | M–H |
| **WP-03** | Appeals & versions | WP-02; **IIC-D4** | Appeals Actions; correction drafts | M |
| **WP-04** | HTTP API & policies | WP-01–03 | Controllers, Form Requests, Resources, routes §14 | M |
| **WP-05** | Frontend RTL | WP-04; **IIC-D6** | List/form + supplier tab; Vitest | M |
| **WP-06** | Notifications, evidence, acceptance | WP-02–05; **IIC-D7/D8/F2/F3** | Notification type + approve-only emit; Documents morph; Pest/Vitest/tsc matrix | M |

**Do not start WPs** until: **detailed design OWNER ACCEPTED** (separate act) → **separate coding authorization**. Owner decisions **IIC-D1–D8 + IIC-F1–F3** are complete.

---

## 13. Security summary

- Deny-by-default permissions; sensitive verbs explicit.  
- Tenant isolation fail-closed; cross-tenant **404**.  
- Hard SoD on criteria publish, evaluation approve/reject, appeal review.  
- Approved scores immutable; corrections via new version only.  
- Monitoring never mutates supplier lifecycle.  
- Notifications in-app only; deduped; tenant-scoped recipients.  
- Evidence via private Documents; no plaintext secrets beyond normal business notes.

---

## 14. Integration boundaries

| System | II-C stance |
|---|---|
| Suppliers / categories | Read + association checks; **no** status writes from eval |
| Banking (II-B) | Unrelated |
| Eligibility (II-A) | Not a gate unless Owner later adds (out of current IIC-D*) |
| Seasons | FK; required only for `post_season` (**IIC-F1**) |
| Documents | Optional evidence morph |
| Notifications | Monitoring event after approve+true only; recipients = `supplier_evaluations.approve` (**IIC-F2/F3**) |
| PR / S05 | None |

---

## 15. Quality-gate classification

| Class | Items |
|---|---|
| **Binding Owner** | IIC-D1–D8; IIC-F1–F3; II-D3; II-D4; ADR-0022 112–116 |
| **Architecture constraints** | TenantOwned; Sanctum; Form Requests; Resources; ADR-0010/0013/0015; D4 sensitive seeding |
| **Engineering recommendations (non-blocking)** | Exact table/column names; DECIMAL rounding half-up to 2dp; single published pack; appeal SoD vs preparer; exclude approver from notification recipients; no withdraw-in-MVP; approve body omit → carry proposed monitoring; Documents morph alias name; notification type string |
| **Owner TBD** | **None** among identified II-C decisions |

---

## 16. Follow-up Owner decision register

**Closed (2026-11-01):** **IIC-F1=A**, **IIC-F2=A**, **IIC-F3=A** — see [S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md) §3. Historical option text retained only in Owner decisions record; no open F-register items.

---

## 17. Governance

| Item | Status |
|---|---|
| IIC-D1–D8 + IIC-F1–F3 | **APPROVED** (11/11) |
| Detailed design | **DRAFT — NOT OWNER ACCEPTED** (awaiting final design-acceptance act) |
| Implementation | **NOT AUTHORIZED** |
| Commit / push / merge / deploy | **NOT AUTHORIZED** |
| Phase I / II-A / II-B | Unchanged accepted / functionally closed |

---

## 18. References

- [S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md)  
- [API.md §14](API.md) · [DATA_MODEL.md](DATA_MODEL.md) §4 · [PERMISSIONS.md](PERMISSIONS.md) · [UI.md](UI.md) · [TEST_PLAN.md](TEST_PLAN.md) · [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md)  
- [S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md](../../10-decisions/S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md)  
- ADR-0022 §B · ADR-0023 §2.6 · ADR-0010 · ADR-0013

# S04 Phase IV — IV-WP01 Implementation Plan (Configuration Foundation)

> **Status:** `WP01-A IMPLEMENTED — AWAITING OWNER REVIEW` · **WP01-B…F NOT AUTHORIZED**  
> **Final design acceptance:** [S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md) — **FINAL DESIGN OWNER ACCEPTED**  
> **Owner decisions:** IV-D1–IV-D15 (unchanged) · **EC-01…EC-04** closed  
> **Design pack:** [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](PHASE_IV_APPROVAL_ENGINE_DESIGN.md)  
> **WP01-A evidence:** [PHASE_IV_WP01A_ACCEPTANCE_EVIDENCE.md](PHASE_IV_WP01A_ACCEPTANCE_EVIDENCE.md)  
> **IV-WP01-B…F coding:** **NOT AUTHORIZED**  
> **Last updated:** 2026-11-01  
> **Note:** Owner later authorized **WP01-A only** (schema & models). This plan remains the authority for B–F scope when separately authorized.

---

## 1. Final design acceptance reference

| Item | Reference |
|---|---|
| Design acceptance | `S04 Phase IV — FINAL DESIGN OWNER ACCEPTED` (2026-11-01) |
| Decisions | [S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md) |
| EC closures | [PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md](PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md) |
| Binding design | [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](PHASE_IV_APPROVAL_ENGINE_DESIGN.md) §§3–4, §10–12, §19 |
| Phase III handoff | [S04_PHASE_III_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_III_ACCEPTANCE_STATUS.md) — `submitted` only; `matrix_id` null until WP02 |

**IV-D15:** Design accepted. **WP01-A** received limited Owner coding authorization (2026-11-01). **WP01-B…F** still require separate authorization.

---

## 2. WP01 scope and exclusions

### 2.1 In scope (configuration foundation only)

| Area | Deliverables when coding later authorized |
|---|---|
| **A. Matrices** | Tenant-scoped draft/published/retired lifecycle; immutable published versions; publish validation |
| **B. Rules** | Predicates, bands, `applies_when_unpriced`, specificity, ambiguity detection; **no** platform default bands |
| **C. Levels** | Ordered levels; pool eligibility requirements (no named assignee); sequencing validation |
| **D. Financial authority** | Ceilings, `covers_unpriced`, validity windows, OU scope optional; revoke/supersede |
| **E. Permissions** | Catalog `purchase_requests.manage_approval_config` as **sensitive explicit-only**; Policies deny-by-default; **no** auto-grants / template seed |
| **F. Configuration API** | CRUD + publish per §6 below |

### 2.2 Explicitly out of WP01

| Deferred to | Items |
|---|---|
| **WP02** | Routing job, instances/steps, PR `matrix_id` fill, routing-block columns, snapshots at route time |
| **WP03** | Approve / reject / return / resubmit re-route behavior |
| **WP04** | FR gate under_review; creator/FR SoD at action time |
| **WP05** | Delegations; SLA policies; SLA scanners |
| **WP06** | `cancel_approved` |
| **WP07** | Arabic RTL config UI & operational notifications |
| **WP08** | Full stress / final verification |

**Do not** implement approve/reject/return, inbox, route job, delegations, SLA, or FE in WP01.

---

## 3. Proposed architecture

```
HTTP (Sanctum + tenant middleware)
  → Form Requests (manage_approval_config)
  → Policies (deny-by-default)
  → Actions / Services (DB::transaction)
       ├─ Create/Update Matrix Draft
       ├─ Manage Rules / Levels (draft-only writes)
       ├─ PublishMatrix (validate → freeze → retire prior)
       └─ Manage ApproverAuthority
  → API Resources
  → AuthorizationSecurity / domain audit (publish, authority mutate)
```

**Patterns:** Match Phase III module layout under `App\Modules\SuppliersAndProcurement\` (`Actions\`, `Models\`, `Http\`, `Policies\`, `Enums\`). All models `TenantOwned` + `UsesTenantScope`. Never trust `tenant_id` from payload.

**Copy-on-publish (Eng binding for WP01):**

1. Working **draft** matrix owns editable rules/levels.  
2. `PublishMatrix`: validate → within one TX lock matrix → increment/set `version_no` → set `status=published`, `published_at/by`, `effective_from` → **reject further writes** to that matrix’s rules/levels.  
3. Prior `published` matrix for tenant (if any) → `retired` (immutable history).  
4. To change config: create **new draft** (optional Eng: clone from published) and publish later.  

Alternative single-row “edit after publish” is **rejected** (breaks immutability **125**).

**Active routing source for WP02:** latest tenant matrix with `status=published` (Eng: at most one published at a time after retire-others).

---

## 4. Schema and migration sequence (proposal only)

**Target DB:** MariaDB **10.4.27** (same as Phase III stress baseline).  
**Proposed migration count for WP01:** **4 create + 1 optional alter** = **5** (max).  
**Do not** create migration files in this planning task.

### 4.1 Compatibility with Phase III

| Existing | WP01 impact |
|---|---|
| `purchase_requests.matrix_id` `UNSIGNED BIGINT NULL` (no FK today) | Leave nullable; **do not** write from WP01. Optional WP01-final migration: add FK `restrictOnDelete` once matrices exist |
| `purchase_requests.matrix_version_no` | Untouched by WP01 writers |
| PR statuses / FR / versions | Untouched |
| No approval config tables yet | Greenfield creates |

**MariaDB 10.4 notes:** Prefer unique indexes + app validation over CHECK (enforcement historically weak). Use `DECIMAL(14,2)`, `TINYINT(1)` bools, `VARCHAR` status enums, InnoDB, named indexes ≤64 chars.

### 4.2 Migration M1 — `purchase_approval_matrices`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `tenant_id` | FK tenants RESTRICT | |
| `name` | VARCHAR(255) | Required |
| `status` | VARCHAR(32) | `draft` \| `published` \| `retired` |
| `version_no` | UNSIGNED INT | Starts 1 on first publish; draft may use 0 or null Eng |
| `effective_from` | TIMESTAMP NULL | Set on publish |
| `published_at` | TIMESTAMP NULL | |
| `published_by` | FK users NULL RESTRICT | |
| `created_by` | FK users RESTRICT | |
| timestamps | | |
| soft deletes | optional Eng | Prefer no soft-delete on published; use `retired` |

**Indexes:** `(tenant_id, status)`, `(tenant_id, name)` unique among non-retired Eng optional, `(tenant_id, version_no)` unique among published Eng.

### 4.3 Migration M2 — `purchase_approval_matrix_rules`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `tenant_id` | FK | |
| `matrix_id` | FK matrices RESTRICT | |
| `min_amount` | DECIMAL(14,2) NULL | Tenant band; null = open low |
| `max_amount` | DECIMAL(14,2) NULL | Null = open high |
| `request_kind` | VARCHAR(32) NULL | `seasonal` \| `administrative` \| null wildcard |
| `organization_unit_id` | FK org_units NULL RESTRICT | Same-tenant |
| `season_id` | FK seasons NULL RESTRICT | Same-tenant |
| `applies_when_unpriced` | BOOLEAN default false | **EC-03** |
| `specificity_hint` | UNSIGNED INT NULL | Optional cached score Eng |
| timestamps | | |

**Indexes:** `(tenant_id, matrix_id)`, `(matrix_id, applies_when_unpriced)`.

### 4.4 Migration M3 — `purchase_approval_matrix_levels`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `tenant_id` | FK | |
| `matrix_id` | FK RESTRICT | |
| `level_order` | UNSIGNED INT | 1..N contiguous on publish |
| `name` | VARCHAR(255) | Arabic-capable |
| timestamps | | |

**Unique:** `(matrix_id, level_order)`.  
**No** `assignee_user_id` (pool model **IV-D4**).

### 4.5 Migration M4 — `purchase_approver_authorities`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `tenant_id` | FK | |
| `user_id` | FK users RESTRICT | Same tenant |
| `max_amount` | DECIMAL(14,2) | Ceiling |
| `currency` | CHAR(3) default `SAR` | |
| `covers_unpriced` | BOOLEAN default false | **EC-03** |
| `organization_unit_id` | FK NULL | Optional scope |
| `effective_from` | TIMESTAMP | |
| `effective_to` | TIMESTAMP NULL | Null = open |
| `revoked_at` | TIMESTAMP NULL | |
| `created_by` | FK users | |
| timestamps | | |

**Indexes:** `(tenant_id, user_id)`, `(tenant_id, revoked_at, effective_to)`.  
**Eng validation:** no overlapping active windows per `(tenant_id, user_id)` (app-enforced; optional generated uniqueness later).

### 4.6 Migration M5 (optional WP01) — PR FK

`ALTER purchase_requests`: add FK `matrix_id` → `purchase_approval_matrices.id` `NULL` `RESTRICT`.  
If risky with uncommitted III work, defer FK to WP02 — plan records either choice; **prefer M5 in WP01** for integrity once matrices exist.

### 4.7 Safe ordering

`M1 → M2 → M3 → M4 → M5(optional)`.  
Down migrations reverse. No data backfill. No writes to `erp_hajj` without separate ops auth (when coding: testing DB only).

---

## 5. Configuration API contracts (plan)

Base: `/api/v1`. Perm unless noted: **`purchase_requests.manage_approval_config`**. AuthN required. XT → **404**. Correlation ID. Audit on mutating publish/authority.

### 5.1 Matrices

| Method | Path | Body (summary) | Response | Validation / errors |
|---|---|---|---|---|
| GET | `/purchase-approval-matrices` | filters: status | paginated list | — |
| POST | `/purchase-approval-matrices` | `{ name }` | 201 draft | `422` name required; audit create |
| GET | `/purchase-approval-matrices/{id}` | — | matrix + nested rules/levels | 404 XT/missing |
| PATCH | `/purchase-approval-matrices/{id}` | `{ name }` | draft only | `409 MATRIX_NOT_DRAFT` if published/retired |
| POST | `/purchase-approval-matrices/{id}/publish` | `{ effective_from? }` | published matrix | See §7; idempotent if already published same version → 200 no-op Eng; concurrent → lock |

**Idempotency:** Publish under `lockForUpdate`; second publish of already-published → `409 MATRIX_ALREADY_PUBLISHED` (must clone new draft).

### 5.2 Rules (draft matrix only)

| Method | Path | Notes |
|---|---|---|
| GET | `/purchase-approval-matrices/{id}/rules` | List |
| POST | `…/rules` | Create; validate ranges `min≤max` when both set; OU/season same-tenant else 422 |
| PATCH | `…/rules/{ruleId}` | Draft only |
| DELETE | `…/rules/{ruleId}` | Draft only |

Errors: `MATRIX_NOT_DRAFT`, `PR_ORG_UNIT_INVALID`, `PR_SEASON_INVALID` (reuse III vocabulary where fit), `RULE_AMOUNT_RANGE_INVALID`.

### 5.3 Levels (draft matrix only)

| Method | Path | Notes |
|---|---|---|
| GET/POST/PATCH/DELETE | `/purchase-approval-matrices/{id}/levels[/{levelId}]` | `level_order` unique per matrix; rename OK |

Errors: `LEVEL_ORDER_CONFLICT`, `MATRIX_NOT_DRAFT`.

### 5.4 Authorities

| Method | Path | Body | Notes |
|---|---|---|---|
| GET | `/purchase-approver-authorities` | — | List tenant authorities |
| POST | `/purchase-approver-authorities` | user_id, max_amount, covers_unpriced, effective_*, OU? | User same-tenant active; overlap check |
| PATCH | `/purchase-approver-authorities/{id}` | ceiling / covers_unpriced / dates | |
| POST | `/purchase-approver-authorities/{id}/revoke` | — | Sets `revoked_at`; audit |

Errors: `AUTHORITY_USER_INVALID`, `AUTHORITY_OVERLAP`, `AUTHORITY_AMOUNT_INVALID`.  
**Does not** grant `purchase_requests.approve` — authority ≠ permission (**EC-02/WP05** later; WP01 documents the split).

### 5.5 Out of WP01 API

Delegations, SLA CRUD, route/approve/reject/return, inbox — **not** WP01.

### 5.6 Audit events (WP01)

| Event | When |
|---|---|
| `purchase_approval_matrix.created` | POST matrix |
| `purchase_approval_matrix.updated` | PATCH draft |
| `purchase_approval_matrix.published` | Publish success (include version_no) |
| `purchase_approval_matrix.retired` | Prior published retired |
| `purchase_approval_rule.*` / `level.*` | Mutate draft |
| `purchase_approver_authority.granted` / `updated` / `revoked` | Authority lifecycle |

No secrets in audit values. Include `tenant_id`, actor, correlation ID.

---

## 6. Permissions and authorization

### 6.1 Catalog integration plan (when coding authorized)

1. Add to `PermissionCatalog` (module `purchase_requests`):  
   - `purchase_requests.manage_approval_config` — **sensitive** (same lists as `financial_review`).  
2. **Do not** add `approve`/`reject`/`return`/`cancel_approved`/`manage_delegations` in WP01 unless needed for compile-time catalog completeness — **prefer WP01 only `manage_approval_config`**; remaining IV perms in the WP that first uses them (document in WP02/03/05/06 plans).  
   - Eng recommendation: register **all** IV permission **names** in catalog in WP01 for one sync migration of permissions table, but mark all sensitive and **grant none** in role templates.  
3. Sync permissions seeder/command: insert missing names; **zero** role_template attachments for sensitive.  
4. Policy: `PurchaseApprovalMatrixPolicy` / `PurchaseApproverAuthorityPolicy` — `manage_approval_config` only.  
5. Existing III `PurchaseRequestPolicy` unchanged.

### 6.2 Security controls

- Deny-by-default.  
- No Tenant Owner / role-name bypass.  
- Cross-tenant id → 404.  
- Published immutability enforced in Actions (not only UI).

---

## 7. Publish-time validation

### 7.1 Must block publication (`422` / domain codes)

| Check | Code (Eng) |
|---|---|
| Matrix not `draft` | `MATRIX_NOT_DRAFT` |
| Zero levels | `MATRIX_LEVELS_REQUIRED` |
| `level_order` not unique or not contiguous 1..N | `MATRIX_LEVELS_INVALID` |
| Zero rules | `MATRIX_RULES_REQUIRED` |
| Any rule `min_amount > max_amount` (both set) | `RULE_AMOUNT_RANGE_INVALID` |
| Ambiguous rule set (IV-D3 residual multi-match possible for same specificity) | `PR_APPROVAL_RULE_AMBIGUOUS` / `MATRIX_RULES_AMBIGUOUS` |
| No rule with `applies_when_unpriced=true` | `MATRIX_UNPRICED_RULE_REQUIRED` (**EC-03**) |
| OU / season FK not same-tenant or missing | `PR_ORG_UNIT_INVALID` / season invalid |
| Currency non-SAR if platform SAR-only Eng | `MATRIX_CURRENCY_UNSUPPORTED` |

**Ambiguity algorithm (Eng):** For each pair of rules that are not strictly ordered by specificity score (non-null predicate count + band tightness + higher `min_amount` tie-break), if their predicate spaces can both match the same PR shape → fail publish. Prefer fail-closed over silent pick.

**No permanent named approver required** for levels (pool model).

### 7.2 Not required at publish (WP02+ runtime fail-closed)

| Concern | Runtime (WP02+) |
|---|---|
| Whether any user currently holds authority / `approve` | Empty pool → IV-D6 block on route |
| Whether `covers_unpriced` holders exist | IV-D6 / empty pool |
| Creator/FR SoD for a future PR | WP03/WP04 action-time |
| Delegation / SLA | WP05 |

Publish validates **configuration shape**; it does **not** require a live eligible human for every hypothetical PR.

---

## 8. Services / Actions inventory (plan only)

| Action | Responsibility | TX | Failure |
|---|---|---|---|
| `CreatePurchaseApprovalMatrix` | Insert draft | Yes | validation |
| `UpdatePurchaseApprovalMatrix` | Rename draft | Yes + lock | `MATRIX_NOT_DRAFT` |
| `UpsertMatrixRule` / `DeleteMatrixRule` | Draft rules | Yes + lock matrix | draft/range/FK |
| `UpsertMatrixLevel` / `DeleteMatrixLevel` | Draft levels | Yes + lock | order conflict |
| `PublishPurchaseApprovalMatrix` | Validate §7; publish; retire prior published | Yes; lock all candidate published rows FOR UPDATE | validation codes |
| `GrantApproverAuthority` | Create authority | Yes | overlap/user |
| `UpdateApproverAuthority` | Patch | Yes + lock | |
| `RevokeApproverAuthority` | `revoked_at=now` | Yes + lock | |

**Concurrency:** Publish and draft mutations take `lockForUpdate` on matrix row. Concurrent publish of two drafts: both may succeed as sequential publishes if Eng allows multiple historical published→retired; **active** published = latest `published_at` / highest `version_no` (document single-active invariant: retire others in same TX).

**Tenant validation:** `TenantContext::require()`; all FKs scoped.

---

## 9. Implementation sub-packages (planning units — not authorization)

### WP01-A — Schema and models

- **Scope:** M1–M4 (+ optional M5); Eloquent models; enums `MatrixStatus`; factories.  
- **Deps:** Design acceptance + **coding auth**.  
- **Files (proposed):** migrations under `backend/database/migrations/`; `Models/PurchaseApprovalMatrix.php`, `…Rule`, `…Level`, `PurchaseApproverAuthority.php`.  
- **Tests:** migration smoke on `erp_hajj_testing` only; model tenant scope unit.  
- **AC:** Tables exist; TenantOwned fails closed without context.  
- **Rollback:** `migrate:rollback` step-wise on testing DB.  
- **Gate:** Schema review.

### WP01-B — Matrix drafts, rules, levels

- **Scope:** Create/update/delete draft Actions; immutability guards.  
- **Deps:** WP01-A.  
- **Files:** Actions + Form Requests.  
- **Tests:** draft CRUD; published write denied.  
- **AC:** Cannot mutate published rules/levels.  
- **Gate:** Action review.

### WP01-C — Publication and versioning

- **Scope:** `PublishPurchaseApprovalMatrix` + §7 validator; retire prior.  
- **Deps:** WP01-B.  
- **Tests:** happy publish; each block check; concurrent publish; unpriced rule required; ambiguity.  
- **AC:** Published immutable; prior retired; audit published.  
- **Gate:** Owner optional spot-check of validation list.

### WP01-D — Financial authority and permissions

- **Scope:** Authority Actions; PermissionCatalog + sync; sensitive flag; Policies.  
- **Deps:** WP01-A (can parallel B after A).  
- **Tests:** grant/revoke; overlap; XT; perm denial; sensitive not in templates.  
- **AC:** No implicit grants; `covers_unpriced` persisted.  
- **Gate:** Security review of catalog diff.

### WP01-E — API and audit

- **Scope:** Controllers, routes `/api/v1`, Resources, wire audit.  
- **Deps:** WP01-B/C/D.  
- **Tests:** Feature API matrix §10; 401/403/404/422.  
- **AC:** Matches §5 contracts.  
- **Gate:** API review.

### WP01-F — Regression, security, acceptance evidence

- **Scope:** Full WP01 Pest suite; XT adversarial; evidence doc; no FE.  
- **Deps:** WP01-E.  
- **Tests:** §10 matrix complete.  
- **AC:** Evidence file on testing DB identity verified.  
- **Gate:** Owner coding-acceptance for WP01 (separate) — **not** Phase IV complete.

---

## 10. Test and acceptance matrix (do not execute now)

| ID | Case | Type |
|---|---|---|
| T-M01 | Create draft matrix | Feature |
| T-M02 | Patch draft / reject patch published | Feature |
| T-M03 | Rules/levels CRUD draft only | Feature |
| T-M04 | Publish success freezes + retires prior | Feature |
| T-M05 | Publish blocked: no levels / no rules / bad range | Feature |
| T-M06 | Publish blocked: no `applies_when_unpriced` | Feature |
| T-M07 | Publish blocked: ambiguous rules | Unit+Feature |
| T-M08 | Specificity ordering fixtures | Unit |
| T-A01 | Authority grant with ceiling + covers_unpriced | Feature |
| T-A02 | Authority revoke | Feature |
| T-A03 | Overlapping active authority rejected | Feature |
| T-P01 | Missing `manage_approval_config` → 403 | AuthZ |
| T-P02 | Sensitive not template-seeded | Unit/Catalog |
| T-X01 | Cross-tenant matrix/authority id → 404 | XT |
| T-C01 | Concurrent publish | Concurrency |
| T-C02 | Concurrent authority update | Concurrency |
| T-R01 | TX rollback leaves draft unpublished | Feature |
| T-U01 | Audit rows for publish + authority | Audit |

**DB rule for future execution:** every writing process verifies `SELECT DATABASE() = erp_hajj_testing` (or phpunit.mysql.xml force) before writes.

---

## 11. Integration contracts with WP02 (expose, do not implement)

WP01 must leave these **readable** contracts for WP02:

| Interface | Contract |
|---|---|
| Active matrix | Query: tenant’s `status=published` matrix (latest version) |
| Rules/levels | Immutable rows for that `matrix_id` |
| Rule match inputs | PR: `estimated_total_priced`, `has_unpriced_lines`, `request_kind`, `beneficiary_organization_unit_id`, `season_id` |
| Unpriced match | Only `applies_when_unpriced=true` when `has_unpriced_lines` (**EC-03**) |
| Authority read API/model | `covers_unpriced`, `max_amount`, validity, user_id for pool calc |
| Publish immutability | WP02 never edits matrix rows; only stores `matrix_id` / `matrix_version_no` on PR + instance |
| Missing config | WP02 IV-D6 — WP01 may leave zero published matrices (valid empty tenant) |

**WP01 does not:** dispatch route jobs, create instances, set PR status to `under_review`, or notify pools.

---

## 12. Risk register

| Risk | Mitigation |
|---|---|
| Ambiguity validator too strict/loose | Fixtures from EC-03/IV-D3; fail closed; refine in WP01-C tests |
| PermissionCatalog missing IV names today | WP01-D registers + sync; no grants |
| FK on `purchase_requests.matrix_id` conflicts with uncommitted III | Optional M5; verify on testing DB first |
| Copy-on-publish UX friction | Clone-from-published helper Eng in WP01-B or WP07 |
| Scope creep into WP02/05 | Hard exclusions §2.2; review gate |
| MariaDB index name length | Short custom names |

### Additional Owner decisions required?

**None** for WP01 planning — no new product choices beyond accepted IV-D* / EC-*.  
Tenant go-live still requires each tenant to configure bands (ADR-0022 **125**) — operational, not a new Owner decision for this plan.

---

## 13. Preconditions for coding authorization

Before WP01-A begins, Owner must explicitly authorize **Phase IV IV-WP01 coding and migrations**, including:

1. Final design acceptance (✓ recorded).  
2. Separate **coding** authorization naming IV-WP01 (or Phase IV WP01–…).  
3. Confirmation testing DB = `erp_hajj_testing` only for writes.  
4. No commit/push/merge/deploy unless separately authorized.

---

## 14. Proposed implementation order (after coding auth)

1. WP01-A → 2. WP01-B ∥ WP01-D → 3. WP01-C → 4. WP01-E → 5. WP01-F  

Then stop for Owner WP01 functional review before WP02 coding auth.

---

## 15. Explicit no-code status

| Activity | This task |
|---|---|
| Application / FE / test / migration / seeder / config code | **Not performed** |
| SQL / migrate / DB writes | **Not performed** |
| Test execution | **Not performed** |
| Git commit / push / merge / deploy | **Not performed** |
| IV-WP01-A…F implementation | **Not started** |

---

## 16. References

- [S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md)  
- [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](PHASE_IV_APPROVAL_ENGINE_DESIGN.md)  
- [PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md](PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md)  
- [API.md](API.md) §6 · [DATA_MODEL.md](DATA_MODEL.md) §5 · [PERMISSIONS.md](PERMISSIONS.md) · [BUSINESS_RULES.md](BUSINESS_RULES.md) §4 · [TEST_PLAN.md](TEST_PLAN.md) · [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md)  
- ADR-0022 **124–125** · ADR-0023 §2.8 · ADR-0003 tenancy  

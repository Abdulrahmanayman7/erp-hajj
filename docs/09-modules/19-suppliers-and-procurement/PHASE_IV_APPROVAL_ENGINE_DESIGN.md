# S04 Phase IV — Approval Engine Detailed Design

> **Status:** `FINAL DESIGN OWNER ACCEPTED` (2026-11-01) — [S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md)  
> **Owner decisions:** 2026-11-01 — [S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md) (**IV-D1–IV-D15 APPROVED — 15/15**; unchanged)  
> **Engineering closure:** [PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md](PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md) (**EC-01…EC-04**)  
> **WP01 plan:** [PHASE_IV_WP01_IMPLEMENTATION_PLAN.md](PHASE_IV_WP01_IMPLEMENTATION_PLAN.md) — **IV-WP01 implementation NOT AUTHORIZED**  
> **Coding / migrations:** **NOT AUTHORIZED** (design accepted; separate coding authorization still required per **IV-D15**)  
> **Last updated:** 2026-11-01  
> **Builds on:** ADR-0022 **124–130** (+ preserved **117–123**), ADR-0023 §2.7–2.8 / **D2–D5**, Phase III PR contracts (**III-D1–D8**), ADR-0013 notifications, Tenancy job middleware, module API/DATA_MODEL/BUSINESS_RULES/PERMISSIONS/UI/TEST_PLAN/ACCEPTANCE_CRITERIA  
> **Excludes:** Phase V demand groups / control reports; S05 RFQ/PO/GRN; payments; budget reservation; inventing global money bands or SLA defaults; auto-approve on SLA/conflict  
> **Not claimed:** Phase IV functionally implemented/complete; IV-WP01 coding started

Legend: **(Owner)** = IV-D* / D* / ADR binding; **(Eng)** = engineering closure contract (EC-*) unless marked otherwise.

---

## 0. Binding Owner decisions (2026-11-01)

| # | Choice | Rule |
|---|---|---|
| **IV-D1** | A | Auto-route `submitted` → resolve published matrix → instance/steps → `under_review`. Submit itself remains Phase III (`submitted` only). Idempotent; fail closed. |
| **IV-D2** | B | Unpriced lines → unknown/unpriced or elevated path; never lower authority via priced subtotal alone. FR mandatory before final approve. No global bands. |
| **IV-D3** | A | Most-specific deterministic rule; min-amount tie-break; residual ambiguity → fail closed. |
| **IV-D4** | B | Per-level eligible **pool**; first valid concurrent action wins atomically. |
| **IV-D5** | A | Creator must not approve own PR at **any** level (roles/delegation cannot bypass). Creator may FR if permitted (III). |
| **IV-D6** | A | Incomplete config → stay `submitted` + blocked reason; notify config admins; no zero-step workflow; safe retry. |
| **IV-D7** | A | Active-step pool may approve / reject / return (explicit perms + SoD). Reject→`rejected`; return→`returned`; cancel pending steps; reasons + audit. |
| **IV-D8** | A | Resubmit → new version + re-resolve current published matrix; restart levels; never reuse prior approvals; keep history. |
| **IV-D9** | A | FR while `under_review` until final approve; final gate uses latest qualifying review for current version. |
| **IV-D10** | C | Auto-exclude FR reviewer from final pool; other eligible may approve; empty pool → block + notify admin; never auto-approve. |
| **IV-D11** | C | Constrained self-delegation **and** central admin; time-bounded; ceiling ≤ delegator; acting user SoD/eligibility; sensitive admin explicit. |
| **IV-D12** | A | Tenant SLA + reminders + escalation **notifications only**; no auto-approve/skip/reassign; no global default duration. |
| **IV-D13** | A | `cancel_approved` + reason + audit; engagement lock blocks; no S05. |
| **IV-D14** | A | Operational in-app notification catalog (see §7); no digests / Phase V reports. |
| **IV-D15** | B | Design may proceed during III doc sync; coding needs III docs accurate + IV design Owner-accepted + **separate** coding auth. |

Preserved: **D2** (FR before final), **D3** (cancel approved + lock), **D4** (sensitive perms explicit), **D5** (FR ≠ final approver), **III-D1** (submit handoff), **III-D7** (structured versions).

### Documented ADR reconciliation (do not silent-override)

| Topic | Existing text | Phase IV binding |
|---|---|---|
| Final SoD when reviewer would approve | ADR-0023 §2.8 / DATA_MODEL Eng: “reassign” final step | **IV-D10=C** binds **automatic pool exclusion** of FR reviewer; another eligible actor may act without manual reassignment; empty pool → block + notify. Escalation clause of ADR-0023 remains. |
| FR status window | III-D2 / Phase III action: `draft`\|`submitted` | **IV-D9=A** requires FR while `under_review`. **Proposed coding change (when authorized):** extend `RecordPurchaseRequestFinancialReview` status allow-list to include `under_review`. Not a Phase III reopen. |
| Cancel approved representation | Enum has `cancelled` only | **IV-D13**: same `cancelled` status + `cancelled_*` fields; distinguish via `cancel_approved` permission + audit event — **no new status**. |

---

## 1. Business scope

### In scope (Phase IV)

- Tenant approval matrices (draft/publish), immutable published versions, rules, levels.  
- Financial authority ceilings.  
- Routing engine: `submitted` → `under_review` (**IV-D1**) or blocked (**IV-D6**).  
- Approval instances/steps; pool eligibility; approve/reject/return (**IV-D4/D7**).  
- Creator SoD all levels (**IV-D5**); FR vs final SoD (**D5**/**IV-D10**).  
- FR during `under_review` (**IV-D9**) + final gate (**D2**).  
- Resubmission re-routing (**IV-D8**).  
- Delegation (**IV-D11**); SLA notify-only (**IV-D12**); operational notifications (**IV-D14**).  
- Cancel approved (**IV-D13**).  
- Arabic RTL UI for inbox, timeline, config, delegation, SLA, blocked routing, SoD messaging.  
- Audit + concurrency + multi-tenant adversarial tests (design only).

### Out of scope

| Deferred | Items |
|---|---|
| **Phase V** | Demand groups; control reports (**130** reporting UIs) |
| **S05** | Writing `procurement_engaged_*`; RFQ/PO/GRN |
| **Platform** | Default financial bands; default SLA hours |
| **Product** | Auto-approve, skip levels, auto-reassign on SLA |

---

## 2. Domain state machine

### 2.1 Status vocabulary (existing enum — verified)

`draft` · `submitted` · `under_review` · `returned` · `approved` · `rejected` · `cancelled`

`PurchaseRequestStatus` already includes all seven values. Non-approved cancel covers `draft`/`submitted`/`returned`/`under_review`. Approved cancel uses **same** `cancelled` outcome (**IV-D13**).

### 2.2 Valid transitions (Phase IV writers)

| From | To | Trigger | Notes |
|---|---|---|---|
| `submitted` | `under_review` | Routing success (**IV-D1**) | Only after instance+≥1 step created |
| `submitted` | `submitted` | Routing blocked (**IV-D6**) | Idempotent; set/update block reason |
| `under_review` | `approved` | Final-level approve after **D2**/**D5** | |
| `under_review` | `rejected` | Reject on active step (**IV-D7**) | Cancel remaining pending steps |
| `under_review` | `returned` | Return on active step (**IV-D7**) | Cancel remaining pending steps |
| `under_review` | `cancelled` | Non-approved `cancel` | Existing III perm |
| `returned` | `draft` fields editable | Update/lines | Status stays `returned` until resubmit (**Eng**) |
| `returned` | `submitted` | Resubmit (**III-D1** + **IV-D8**) | New version; then routing again |
| `approved` | `cancelled` | `cancel_approved` (**IV-D13**) | Blocked if engagement lock |
| `rejected` / `cancelled` | — | Terminal | No reopen without new Owner decision |

### 2.3 Invalid transitions (non-exhaustive)

- `submitted` → `approved`/`rejected`/`returned` without routing.  
- Any → `under_review` without published matrix + valid steps.  
- `approved` → `returned` / re-open.  
- Zero-step “approve”.  
- Approve by creator (**IV-D5**) or FR reviewer on final (**IV-D10**).  
- `cancel_approved` when not `approved` or when engaged.

### 2.4 Concurrency

- Route: lock PR `FOR UPDATE`; if already `under_review` with active instance for `version_no` → no-op success (**idempotent**).  
- Step action: lock step + PR; require `pending` + current active level; first writer wins; losers → `409 PR_STEP_NOT_PENDING` / `PR_CONCURRENT_DECISION`.  
- Cancel approved vs future S05 engagement: lock PR; if `procurement_engaged_at` set → `422 PR_PROCUREMENT_ENGAGED`.

### 2.5 Retry semantics

- Routing retry after config fix: allowed while `submitted` and block reason set or missing instance.  
- Do not create a second instance for the same `(pr_id, version_no)` — unique constraint (**Eng**).

---

## 3. Matrix configuration and publication

### 3.1 Lifecycle

| State | Meaning |
|---|---|
| `draft` | Editable rules/levels |
| `published` | Immutable snapshot version; becomes the tenant’s active routing source |
| (optional Eng) `retired` | Superseded by newer publish; retained for history |

**Publish (Owner 125):** controlled change with effective timestamp, `approved_by`/`approved_at`, audit. New publish increments `version_no` and freezes rule/level rows for that version (copy-on-publish **Eng**).

### 3.2 Rule dimensions (**IV-D3**)

Predicates (nullable = wildcard):

- `organization_unit_id`  
- `season_id` or `season_required` / season match (**Eng**: prefer explicit `season_id` nullable + kind)  
- `request_kind` (`seasonal`\|`administrative`\|null)  
- Financial band: `min_amount`, `max_amount` nullable open end  
- Unpriced path flag: `applies_when_unpriced` / dedicated rule class (**IV-D2**)

**Specificity score (Eng recommendation):** count of non-null predicates + band tightness; among ties prefer **higher `min_amount`** (approved minimum-amount principle). Residual multi-match → fail closed `PR_APPROVAL_RULE_AMBIGUOUS`.

### 3.3 Unpriced handling (**IV-D2** / **EC-03**)

If `has_unpriced_lines` (any line `unit_estimate` null / flag true):

1. Candidate rules = **only** rules with `applies_when_unpriced=true` (tenant unknown/unpriced path **or** tenant elevated unpriced tier encoded as such a rule).  
2. **Never** coerce missing prices to zero.  
3. **Forbidden:** selecting a priced-only rule via `estimated_total_priced` (would bypass elevated/unpriced path).  
4. Among candidates, apply **IV-D3** specificity; residual ambiguity → fail closed.  
5. If no candidate / empty eligible authority pool → stay `submitted` (**IV-D6**) with `PR_UNPRICED_PATH_MISSING` / `PR_ROUTING_BLOCKED`.  

**Publish validation (Eng):** refuse publish unless ≥1 `applies_when_unpriced` rule exists on the matrix version.  
**Authority (Eng):** unpriced-path levels require `purchase_approver_authorities.covers_unpriced=true`; if the matched rule also defines `min_amount`, require `max_amount ≥ min_amount`. No platform-default bands. Full contract: **§19 EC-03**.

### 3.4 Levels

Ordered `level_order` ≥ 1; `is_final` = max order. Each level requires authority coverage for the **authority amount** used for routing (Eng: for unpriced use tenant policy amount or open ceiling match — **must not invent platform default**; if tenant omits unpriced authority basis → **IV-D6**).

### 3.5 Scope

All matrix entities `tenant_id`-scoped (`TenantOwned`). Season/OU FKs same-tenant validated. No cross-tenant publish.

### 3.6 Snapshot on PR

On successful route: set `purchase_requests.matrix_id`, `matrix_version_no`; store instance linked to published version. Later matrix edits do not mutate in-flight instances (**125**).

### 3.7 Publication validation (fail closed)

Reject publish if: no levels; overlapping ambiguous rules that cannot be ranked; unpriced path missing when tenant allows unpriced PRs (**Eng** warn vs hard — **Owner:** fail closed if routing would be incomplete); currency ≠ SAR if platform is SAR-only (**Eng**).

---

## 4. Financial authority

### 4.1 Model — `purchase_approver_authorities`

| Column | Notes |
|---|---|
| `tenant_id`, `user_id` | |
| `max_amount`, `currency` | Ceiling (tenant-configured; no platform defaults) |
| `covers_unpriced` | BOOL — required for unpriced-path eligibility (**EC-03**) |
| `organization_unit_id` NULL | Optional scope (**Eng**) |
| `effective_from` / `effective_to` | Validity |
| soft flags | active |

### 4.2 Eligibility calculation

Actor eligible for level iff:

1. Explicit `purchase_requests.approve` (deny-by-default).  
2. Active **direct** authority covering required amount (and OU scope if used) **or** depth-1 delegation of such authority (**EC-02**).  
3. For unpriced-path levels: `covers_unpriced=true` on the authority used (direct or delegator’s direct row) (**EC-03**).  
4. Not PR `requested_by` (**IV-D5**).  
5. If final level: not current-version FR `reviewer_id` (**IV-D10**).  
6. Tenant membership; user active.  
7. Acting user always needs own `approve` — delegation never substitutes for the permission (**IV-D11**).

### 4.3 Missing authority (**IV-D6**)

If after SoD filters the active level pool is empty at route time → block routing (or for mid-workflow final: block approve + escalate **EC-04**). Never invent authority or bands.

### 4.4 Delegation interaction

See §7 / **EC-02**: depth-1 only; ceiling = min(delegator direct ceiling, delegation.max_amount); SoD on acting user.

---

## 5. Runtime approval engine

### 5.1 Trigger (**IV-D1** / **EC-01**)

**Binding Eng (EC-01):** **After-commit queued routing** — not synchronous inside submit, not a new transactional-outbox table.

1. Phase III / submit Action commits only `status=submitted` (+ version snapshot).  
2. After successful commit (`DB::afterCommit`), dispatch `RoutePurchaseRequestApprovalJob` (`ShouldQueue` + `TenantAware`).  
3. Payload: server-captured `tenant_id`, `purchase_request_id`, `version_no`, correlation ID.  
4. Job restores tenant via `RestoreTenantContext`, then runs the routing transaction (§5.2).  

Full contract, retries, reconciliation, and rejected alternatives: **§19 EC-01**.

### 5.2 Transaction boundary

Single DB transaction:

1. `lockForUpdate` PR.  
2. Assert `submitted` and `version_no` matches.  
3. Resolve published matrix + rule (**IV-D3**/**IV-D2**).  
4. Build eligible pools per level; if any level empty → **IV-D6** path (persist block; notify; commit without status change).  
5. Else create instance + pending steps; clear block reason; set `under_review`; audit; notify pools (**IV-D14**).

### 5.3 Idempotency

Unique `(tenant_id, purchase_request_id, version_no)` on instances. Key: `route:{tenant}:{pr}:{version}`. Duplicate job → success no-op.

### 5.4 Instance / steps

**Instance:** `status` ∈ {`active`,`completed_approved`,`completed_rejected`,`completed_returned`,`superseded`}; links matrix version snapshot ids.

**Step:** `level_order`, `status` ∈ {`pending`,`approved`,`rejected`,`returned`,`cancelled`}; `due_at` from SLA if configured; **no single assignee required** (pool model **IV-D4**). Optional Eng: `acted_by`, `acted_at`, `comment`, `decision_reason`, escalation timestamps.

### 5.5 Active step

Lowest `level_order` with `pending`. Higher levels remain pending but not actionable until prior approved.

### 5.6 Approve / reject / return (**IV-D7**)

| Action | Perm | Effect |
|---|---|---|
| Approve | `approve` | Mark step approved; if more levels → activate next; if final → **§6 gates** then PR `approved`, instance completed |
| Reject | `reject` | Step rejected; cancel other pending; PR `rejected` |
| Return | `return` | Step returned; cancel other pending; PR `returned` |

Reject/return: reason ≥ 10 chars (**Eng**, align III). Audit immutable.

### 5.7 Resubmission (**IV-D8**)

On resubmit from `returned`: III creates new version → `submitted`. Routing creates **new** instance; prior instance `superseded` (historical). Prior decisions never copied forward.

### 5.8 Failure recovery (**EC-01**)

| Failure class | Behavior |
|---|---|
| **Configuration** (no matrix/rule/pool) | Persist **IV-D6** block on PR; job **succeeds** (no poison retry); notify admins (**EC-04**) |
| **Transient technical** | Leave `submitted`; bounded retries with backoff; then `failed_jobs` + durable `PR_ROUTING_TECHNICAL_FAILURE` block code |
| **Duplicate workers** | Unique instance `(tenant_id, pr_id, version_no)` → no-op success |

Scheduled reconciler redispatchs stranded `submitted` PRs lacking an instance for current `version_no`. Manual `POST …/route` by `manage_approval_config`. Never auto-approve. See **§19 EC-01**.

---

## 6. Financial review integration

### 6.1 Version-bound selection (**D2** / **IV-D9**)

At final approve: load latest `purchase_request_financial_reviews` for `(pr_id, current version_no)` by `reviewed_at`/`id` desc.

Qualifying outcomes: `cleared`, `cleared_with_conditions`.  
`not_cleared` / missing → `422 PR_FINANCIAL_REVIEW_REQUIRED` or `PR_FINANCIAL_REVIEW_NOT_CLEARED`.

### 6.2 Recording during `under_review` (**IV-D9**)

**Proposal (coding when authorized):** extend Phase III action allow-list from `{draft, submitted}` to `{draft, submitted, under_review}`. Still append-only; still version-bound; no status mutation from FR.

### 6.3 SoD

| Rule | Enforcement |
|---|---|
| Creator ≠ any approver (**IV-D5**) | Filter pools + action guard |
| FR reviewer ≠ final approver (**D5**/**IV-D10**) | Exclude from final pool; action guard `PR_FINANCIAL_REVIEWER_SOD` |
| Empty final pool | `PR_NO_ELIGIBLE_FINAL_APPROVER`; notify approval admin; do not auto-approve |

### 6.4 Dynamic pool filtering

Eligibility computed at route time **and** re-checked at action time (authority may expire; new FR may appear). Prefer live filter over stale assignee lists (**IV-D4** pool).

---

## 7. Delegation (**IV-D11** / **EC-02**)

### 7.1 Modes

| Mode | Authorization |
|---|---|
| Self-delegation | Actor has `purchase_requests.approve` **and** a **direct** active `purchase_approver_authorities` row; may create a delegation **from self → another user** (not to self) |
| Central admin | `manage_delegations` — create/revoke for others when delegator holds direct authority |

**Right to delegate ≠ right to approve:** `manage_delegations` alone does not place admin in pools. Delegate must independently hold `approve`.

### 7.2 Constraints (**EC-02 = depth-1 only**)

- **Depth 1 only** — no multi-level chains; a user whose only ceiling is via incoming delegation **cannot** create outbound delegations.  
- `from_user_id ≠ to_user_id`; same `tenant_id` only.  
- Time-bounded (`starts_at`/`ends_at`); auto-expire; immediate `revoked_at`.  
- Effective ceiling = `min(delegator.direct_max_amount, delegation.max_amount)`; unpriced path also requires both sides’ `covers_unpriced` when applicable.  
- No overlapping active windows for the same `(tenant_id, from_user_id, to_user_id)`.  
- At action time: re-validate direct delegator authority, delegation window, delegate `approve`, and SoD (**IV-D5**/**IV-D10**) using **acting** user = `to_user_id`.  
- Full contract: **§19 EC-02**.

### 7.3 Audit events

`purchase_request.delegation_created`, `_revoked`, `_expired`; step decisions record `acted_by` (delegate) + optional `delegation_id` / `delegator_user_id` metadata; no secrets.

---

## 8. SLA and notifications

### 8.1 SLA (**IV-D12**)

`purchase_approval_sla_policies`: tenant rows by `priority` × `level_order` → `target_hours`, optional reminder offsets. **Empty catalog allowed.** No platform default hours.

Scheduler: mark `due_at`; emit reminder / escalation **notifications** only. **Never** approve, skip, or reassign.

### 8.2 Notification catalog (**IV-D14** / **EC-04**)

| Event | Recipient class |
|---|---|
| Entered pool / under_review | **Pool:** live-eligible actors for active step |
| Due reminder | **Pool** + **Admin:** `purchase_requests.manage_approval_config` |
| SLA escalation | **Pool** + **Admin** |
| Returned / Rejected / Final approved / Cancel approved | `requested_by` (lifecycle); no role-name fanout |
| Matrix/authority/eligible-path missing; routing technical failure; final SoD empty pool | **Admin only** (`manage_approval_config`) |

**Resolution:** `NotificationRecipientResolver::usersWithPermission(...)` within current tenant; active users only; explicit permission names — **never** role names / Tenant Owner broadcast.  
**Empty admin set:** durable PR block / audit `PR_ADMIN_RECIPIENTS_EMPTY`; workflow unchanged; never auto-approve; never cross-tenant.  
Reuse ADR-0013 dispatcher (`afterCommit`, dedupe_key, swallow delivery faults). Full contract: **§19 EC-04**. Daily digest / Phase V reports **out of scope**.

---

## 9. Approved cancellation (**IV-D13** / **D3**)

| Item | Spec |
|---|---|
| Endpoint | `POST /api/v1/purchase-requests/{id}/cancel-approved` |
| Perm | `purchase_requests.cancel_approved` |
| Precondition | `status=approved` |
| Body | `{ reason: string }` (≥10 Eng) |
| Lock | `procurement_engaged_at` null |
| Effect | `status=cancelled`; set `cancelled_at/by/reason`; audit |
| Race | Lock PR; if engaged mid-flight → `422 PR_PROCUREMENT_ENGAGED` |
| S05 | Do not implement engagement writers |

Errors: `403` no perm; `404` cross-tenant; `409 PR_INVALID_TRANSITION`; `422 PR_CANCEL_REASON_REQUIRED` / `PR_PROCUREMENT_ENGAGED`.

---

## 10. Permissions and security

### 10.1 Explicit Phase IV permissions (already catalogued — **do not seed in this task**)

- `purchase_requests.approve`  
- `purchase_requests.reject`  
- `purchase_requests.return`  
- `purchase_requests.cancel_approved`  
- `purchase_requests.manage_approval_config`  
- `purchase_requests.manage_delegations`  

All **sensitive / D4** — never role-name shortcuts; never Tenant Owner bypass.

### 10.2 Security rules

- Deny-by-default Policies/Gates.  
- Tenant isolation on all config + runtime tables; XT → **404**.  
- Creator SoD + FR SoD as above.  
- Audit: route, step decisions, publish, authority CRUD, delegation, cancel-approved, SLA escalate, blocked routing.  
- Multi-tenant adversarial tests required before merge (when coding authorized).

---

## 11. Data model (proposed)

### 11.1 Migration order (**proposal only — do not create files**)

1. `purchase_approval_matrices` (+ versions if split)  
2. `purchase_approval_matrix_rules`  
3. `purchase_approval_matrix_levels`  
4. `purchase_approver_authorities`  
5. `purchase_approval_delegations`  
6. `purchase_approval_sla_policies`  
7. `purchase_request_approval_instances`  
8. `purchase_request_approval_steps`  
9. Alter `purchase_requests`: routing block columns (**Eng**): `routing_blocked_at`, `routing_block_code`, `routing_block_message`  
10. **No transactional outbox table** (**EC-01**); rely on `afterCommit` job dispatch + unique instances + reconciler command + existing notifications module

### 11.2 Core tables (columns)

**`purchase_approval_matrices`:** `id`, `tenant_id`, `name`, `status` (`draft`/`published`/`retired`), `version_no`, `effective_from`, `published_at`, `published_by`, timestamps, soft deletes as appropriate.

**`purchase_approval_matrix_rules`:** `matrix_id`, `tenant_id`, predicates (§3.2), `applies_when_unpriced`, `priority_rank` optional Eng, unique constraints for publish validation.

**`purchase_approval_matrix_levels`:** `matrix_id`, `tenant_id`, `level_order`, `name`, unique `(matrix_id, level_order)`.

**`purchase_approver_authorities`:** §4.1 + **`covers_unpriced` BOOL** (**EC-03**); unique active window per user (**Eng**).

**`purchase_approval_delegations`:** `from_user_id`, `to_user_id`, `max_amount`, `currency`, `starts_at`, `ends_at`, `authorized_by`, `revoked_at`, `tenant_id`; CHECK `from_user_id <> to_user_id`; no overlapping active `(tenant_id, from, to)` (**EC-02**).

**`purchase_approval_sla_policies`:** `priority`, `level_order`, `target_hours`, reminder offsets; unique `(tenant_id, priority, level_order)`.

**`purchase_request_approval_instances`:** `purchase_request_id`, `version_no`, `matrix_id`, `matrix_version_no`, `rule_id`, `status`, `routed_at`; **UNIQUE** `(tenant_id, purchase_request_id, version_no)`.

**`purchase_request_approval_steps`:** `instance_id`, `level_order`, `status`, `due_at`, `escalated_at`, `acted_by`, `acted_at`, `comment`/`reason`, `conflict_code` nullable (for empty-pool block — prefer over reassignment columns under **IV-D10**).

Indexes: tenant+status, instance+level, due_at for SLA jobs, authority user+tenant.

FKs: all tenant-owned; cascade rules Eng — prefer restrict on published matrix delete.

### 11.3 Lifecycle rules

- Published matrix rows immutable.  
- Historical instances/steps immutable after terminal.  
- FR append-only (III).  
- No `runWithoutTenancy`.

---

## 12. API contracts

Base: `/api/v1`. All authenticated Sanctum + tenant middleware. Resources via API Resources. Correlation ID required.

### 12.1 Configuration

| Method | Path | Perm | Notes |
|---|---|---|---|
| GET/POST | `/purchase-approval-matrices` | `manage_approval_config` | List/create draft |
| GET/PATCH | `/purchase-approval-matrices/{id}` | manage… | Draft edit only |
| POST | `/purchase-approval-matrices/{id}/publish` | manage… | Validate; freeze; audit |
| CRUD | `/purchase-approval-matrices/{id}/rules` | manage… | Draft only |
| CRUD | `/purchase-approval-matrices/{id}/levels` | manage… | Draft only |
| CRUD | `/purchase-approver-authorities` | manage… | |
| CRUD | `/purchase-approval-sla-policies` | manage… | Empty OK |
| GET/POST | `/purchase-approval-delegations` | `manage_delegations` **or** self-scope Eng | |
| DELETE/POST revoke | `/purchase-approval-delegations/{id}` | manage… / owner | |

### 12.2 Runtime

| Method | Path | Perm | Preconditions | Idempotency |
|---|---|---|---|---|
| GET | `/purchase-requests/approval-inbox` | `approve` (Eng) | — | — |
| GET | `/purchase-requests/{id}/approval` | `view` | — | Timeline + pool flags without leaking others’ authority secrets |
| POST | `/purchase-requests/{id}/route` | system/job or `manage_approval_config` retry | `submitted` | Idempotent |
| POST | `/purchase-requests/{id}/approve` | `approve` | `under_review`, active pending step, eligible | Optional `Idempotency-Key`; concurrent → 409 |
| POST | `/purchase-requests/{id}/reject` | `reject` | + reason | same |
| POST | `/purchase-requests/{id}/return` | `return` | + reason | same |
| POST | `/purchase-requests/{id}/cancel-approved` | `cancel_approved` | `approved` | Lock + reason |
| POST | `/purchase-requests/{id}/financial-reviews` | `financial_review` | + **`under_review`** when IV coded | Append-only |

### 12.3 Error codes (IV)

`PR_MATRIX_MISSING`, `PR_APPROVAL_RULE_AMBIGUOUS`, `PR_ROUTING_BLOCKED`, `PR_AUTHORITY_EXCEEDED`, `PR_SOD_VIOLATION`, `PR_FINANCIAL_REVIEW_REQUIRED`, `PR_FINANCIAL_REVIEW_NOT_CLEARED`, `PR_FINANCIAL_REVIEWER_SOD`, `PR_NO_ELIGIBLE_FINAL_APPROVER`, `PR_STEP_NOT_PENDING`, `PR_CONCURRENT_DECISION`, `PR_DELEGATION_INVALID`, `PR_PROCUREMENT_ENGAGED`, `PR_INVALID_TRANSITION`.

### 12.4 Audit

Every mutating endpoint above emits domain audit with actor, tenant, correlation ID, PR/version/step ids; never passwords/tokens.

---

## 13. Arabic RTL UI (documentation only)

| Surface | Behavior |
|---|---|
| Approval inbox | RTL list of actionable PRs for current user pool; permission-gated |
| Step timeline | Levels, statuses, actors, due/SLA badges; Arabic copy |
| Matrix management | Draft editor + publish confirmation; show immutable published versions |
| Authority management | Ceilings CRUD |
| Delegation management | Self + admin views; expiry display |
| SLA settings | Optional policies; empty-state Arabic guidance (no invented defaults) |
| Blocked routing | Banner on PR detail when `submitted` + block code; admin CTA |
| Actions | Approve / Reject / Return dialogs with mandatory reason where required |
| SoD messaging | Clear Arabic explanation when excluded or final blocked |
| Cancel approved | Separate dangerous action; reason modal |
| Visibility | Hide all IV CTAs without permissions (**III-D8** preserved until IV FE ships) |

No frontend code in this task.

---

## 14. Testing and acceptance mapping

| Decision | Unit | Feature | AuthZ | XT | Concurrency | Failure/rollback | AC |
|---|---|---|---|---|---|---|---|
| IV-D1 | resolver | route→under_review | — | 404 | dual route jobs | job fail stays submitted | AC-A01 |
| IV-D2 | unpriced matcher | elevated path | — | — | — | — | AC-A01 |
| IV-D3 | specificity | ambiguous→block | — | — | — | — | AC-A01 |
| IV-D4 | pool calc | first wins | approve | XT | **multi-process step claim** | loser 409 | AC-A02 |
| IV-D5 | filter | creator 403/422 | SoD | — | — | — | AC-A03 |
| IV-D6 | — | blocked reason + notify | config | — | retry idempotent | no zero-step | AC-A01 |
| IV-D7 | transitions | approve/reject/return | perms | XT | — | cancel pending | AC-A04/A05 |
| IV-D8 | — | resubmit new instance | — | — | — | history retained | AC-P07b |
| IV-D9 | latest FR | FR under_review | FR perm | — | — | — | AC-P05 / D2 |
| IV-D10 | exclude | empty final escalate | — | — | — | no auto-approve | AC-P05d |
| IV-D11 | ceiling | delegate act | manage vs approve | XT | — | revoke | AC-A06 |
| IV-D12 | scheduler | notify only | — | — | — | — | AC-A07 |
| IV-D13 | — | cancel-approved | cancel_approved | XT | vs engage race | audit | AC-P05c |
| IV-D14 | — | notification emit | eligibility | tenant | dedupe | delivery fail | — |
| IV-D15 | — | gate docs | — | — | — | — | coding auth |

**Independent multi-process approval-step contention tests** are mandatory (mirror PR numbering stress pattern) before Owner coding acceptance.

No test execution authorized in this documentation task.

---

## 15. Work package plan (proposal only)

### IV-WP00 — Owner decisions & design acceptance

- **Scope:** This register + design pack; Owner design acceptance record.  
- **Deps:** IV-D1–D15 approved.  
- **Files:** docs only.  
- **AC:** 15/15 traced; design accepted by Owner.  
- **Tests:** n/a.  
- **Risks:** silent ADR conflict — mitigated by §0 table.  
- **Gate:** Owner design acceptance (not coding).

### IV-WP01 — Configuration schema, models, publication, authority

- **Scope:** migrations 1–6; models; publish validation; authority CRUD APIs.  
- **Deps:** WP00 + **coding authorization**.  
- **AC:** publish immutable; no default bands; tenant scope.  
- **Tests:** feature config + XT + validation.  
- **Risks:** ambiguous rules.  
- **Gate:** Owner review of schema before WP02 if requested.

### IV-WP02 — Routing & immutable snapshots

- **Scope:** job/action route; instance/steps; block reasons; PR status→under_review.  
- **Deps:** WP01.  
- **AC:** IV-D1/D2/D3/D6; idempotent.  
- **Tests:** route success/block; dual-job idempotency.  
- **Risks:** race with submit.  
- **Gate:** demo routing on testing DB.

### IV-WP03 — Approve / reject / return & resubmission

- **Scope:** step actions; IV-D4/D5/D7/D8; inbox API.  
- **Deps:** WP02.  
- **AC:** transitions + history.  
- **Tests:** feature + **multi-process contention**.  
- **Risks:** lost updates.  
- **Gate:** concurrency evidence.

### IV-WP04 — Financial-review gate & SoD

- **Scope:** IV-D9 allow-list change; final gates D2/D5/IV-D10.  
- **Deps:** WP03.  
- **AC:** AC-P05 / AC-P05d.  
- **Tests:** FR under_review; empty final pool.  
- **Risks:** III regression on FR.  
- **Gate:** SoD suite green.

### IV-WP05 — Delegation & SLA

- **Scope:** IV-D11/D12; policies; scheduler notifications.  
- **Deps:** WP03 (pool), WP01.  
- **AC:** notify-only SLA; ceiling inheritance.  
- **Tests:** expiry, revoke, no auto-approve.  
- **Risks:** over-broad admin.  
- **Gate:** Owner spot-check.

### IV-WP06 — Approved cancellation

- **Scope:** IV-D13 endpoint + lock.  
- **Deps:** WP03 (approved state).  
- **AC:** AC-P05c.  
- **Tests:** engaged block; audit.  
- **Risks:** S05 race — document lock contract.  
- **Gate:** AC pass.

### IV-WP07 — Arabic RTL UI & notifications

- **Scope:** IV-D14 surfaces §13.  
- **Deps:** WP03–06 APIs.  
- **AC:** permission visibility; RTL.  
- **Tests:** Vitest visibility; tsc.  
- **Risks:** memory build — follow prior split tsc/vite.  
- **Gate:** UI review.

### IV-WP08 — Audit, concurrency, security, regression

- **Scope:** full AC-A* / cross-phase regression; adversarial XT.  
- **Deps:** WP01–07.  
- **AC:** DEFINITION_OF_DONE for IV slice.  
- **Tests:** Pest + Vitest matrix §14.  
- **Risks:** flaky concurrency — deterministic harness.  
- **Gate:** Owner functional acceptance (separate act).

**Do not implement any WP in this task.**

---

## 16. Engineering questions status

Former open items **EC-01…EC-04** are closed at design level in **§19** and [PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md](PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md).  
No further Owner product decision is required for those four items. IV-D1–D15 remain unchanged.

---

## 17. Consistency checklist

- [x] IV-D1–D15 represented (unchanged)  
- [x] EC-01…EC-04 closed with explicit contracts  
- [x] Phase III contracts preserved (submit, versions, FR model; IV-D9 `under_review` FR extension deferred to coding)  
- [x] No invented monetary bands / SLA durations  
- [x] Creator SoD + FR SoD  
- [x] Sensitive permissions explicit-only  
- [x] Published snapshots immutable  
- [x] Concurrent step safety designed  
- [x] Tenant isolation  
- [x] Phase V / S05 deferred  
- [x] Coding not authorized  
- [x] No unconditional design-acceptance claim on Owner’s behalf  

---

## 18. References

- [S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md)  
- [PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md](PHASE_IV_CONDITIONAL_DESIGN_CLOSURE.md)  
- [ADR-0022](../../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) · [ADR-0023](../../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md) · [ADR-0013](../../10-decisions/ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md)  
- [PHASE_III_PURCHASE_REQUESTS_DESIGN.md](PHASE_III_PURCHASE_REQUESTS_DESIGN.md) · [S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md)  
- [API.md](API.md) · [DATA_MODEL.md](DATA_MODEL.md) · [BUSINESS_RULES.md](BUSINESS_RULES.md) · [PERMISSIONS.md](PERMISSIONS.md) · [UI.md](UI.md) · [TEST_PLAN.md](TEST_PLAN.md) · [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md)

---

## 19. Conditional Design Acceptance — Engineering Closure EC-01…EC-04

> Owner conditional acceptance of this design pack is subject to these closures.  
> **Closure status of EC-01…EC-04:** `PROPOSED ENGINEERING CONTRACT — READY FOR FINAL OWNER DESIGN ACCEPTANCE`  
> Does **not** authorize coding.

### EC-01 — Automatic routing trigger and reliability

**Related:** IV-D1=A, IV-D6=A, IV-D8=A.

#### 1. Problem statement

How to start routing after submit/resubmit reaches `submitted` without duplicating instances, losing work, or merging Phase III submit into approval.

#### 2. Alternatives considered

| Option | Summary | Assessment |
|---|---|---|
| **A. Synchronous in submit request** | Route inside submit TX/response | Couples III submit to IV config latency; worker crash mid-route harder; blurs III-D1 separation |
| **B. After-commit queued job + retry + reconciler** | `DB::afterCommit` → `RoutePurchaseRequestApprovalJob` | Matches existing Notifications/`TenantAware` patterns; durable uniqueness; recoverable |
| **C. Transactional outbox table** | Persist outbox row in submit TX; worker drains | Strongest durability, but **no existing outbox infra**; adds schema/ops complexity unjustified given B + reconciler |

#### 3. Recommended solution

**B — After-commit queued routing with durable retry and reconciliation.** Reject C unless future ops prove B insufficient.

#### 4. Final proposed engineering contract

1. Submit/resubmit Action: commit `submitted` only (III contract).  
2. Register `DB::afterCommit(fn () => RoutePurchaseRequestApprovalJob::dispatch(...))` with `captureTenantContext()` / `TenantAware`.  
3. Optional Laravel `ShouldBeUnique` / `uniqueId = "pr-route:{tenant}:{pr}:{version}"` **plus** DB UNIQUE on instances.  
4. Job handle: lock PR → assert `submitted` + version → resolve → either IV-D6 block (job success) or create instance/steps → `under_review`.  
5. Config blockage ≠ technical failure (distinct codes).  
6. Technical retries: bounded (Eng proposal: 5 tries, exponential backoff); exhausted → `failed_jobs` + `routing_block_code=PR_ROUTING_TECHNICAL_FAILURE`.  
7. Reconciler command (scheduler): select `submitted` PRs with no instance for `version_no` and `submitted_at` older than Eng threshold (e.g. 60s); redispatch; idempotent.  
8. Manual retry: `POST /purchase-requests/{id}/route` (`manage_approval_config`).  
9. Resubmit (**IV-D8**): new version → new after-commit dispatch; prior instance superseded only when new routing succeeds or remains historical.

**Lock order:** `purchase_requests` then insert instance/steps (no lock on matrix publish rows beyond read of published version).

#### 5. Data / API / worker / UI implications

- Job class + scheduler reconciler (when coding authorized).  
- PR routing block columns already proposed.  
- UI: blocked-routing banner; admin “retry route” CTA.  
- **No** outbox migration.

#### 6. Failure handling

See §5.8 table. Never create zero-step instance. Never auto-approve.

#### 7. Security and tenant isolation

Trusted `tenant_id` on job only; `RestoreTenantContext` + `runAsTenant`; XT impossible via job payload forgery (server-set). Audit route success/block/technical failure with correlation ID.

#### 8. Acceptance tests

- Submit leaves `submitted` before job runs; job → `under_review`.  
- Dual concurrent jobs → one instance.  
- Kill worker mid-job → reconciler recovers.  
- Missing matrix → IV-D6, no infinite retries.  
- After config fix + retry → routes.  
- Cross-tenant job metadata rejected/isolated.

#### 9. Residual risks

Queue downtime delays routing until reconciler/manual retry (visible via block/age). Notification of admins best-effort (ADR-0013 swallow) — mitigated by durable PR block fields.

#### 10. Closure status

**CLOSED (engineering)** — no Owner product decision required.

---

### EC-02 — Delegation depth and cycle prevention

**Related:** IV-D4=B, IV-D5=A, IV-D10=C, IV-D11=C.

#### 1. Problem statement

Whether Phase IV allows multi-level delegation chains and how to prevent cycles, ceiling inflation, and SoD bypass.

#### 2. Alternatives considered

| Option | Summary | Assessment |
|---|---|---|
| **A. Direct one-level only** | Resolve only direct `from→to` rows; no re-delegation | Simpler audit/SoD; fits MVP |
| **B. Multi-level chains** | Walk A→B→C with cycle detection | Higher defect surface; not required by IV-D11 |

#### 3. Recommended solution

**A — Direct one-level delegation only** for Phase IV MVP.

#### 4. Final proposed engineering contract

| Rule | Contract |
|---|---|
| Re-delegate | **Forbidden.** Creator of a delegation must hold a **direct** active authority row (not only an incoming delegation). |
| Overlapping | **Forbidden** for same `(tenant_id, from_user_id, to_user_id)` with overlapping `[starts_at, ends_at]` and `revoked_at` null. |
| Self-to-self | **Forbidden** (`from_user_id ≠ to_user_id`). |
| “Self-delegation” (IV-D11) | Means user delegates **own** authority to **another** user without admin; not self-as-recipient. |
| Cycles | No transitive walk → no chain cycles; still enforce `from ≠ to`. Mutual A↔B allowed as two direct rows; each uses only the counterpart’s **direct** ceiling (not transitive). |
| Tenant | Same tenant only; FKs tenant-scoped. |
| Delegate eligibility | Delegate must independently hold `purchase_requests.approve` + pass SoD/pool rules as **acting** user. |
| Delegator authority | Must be active **at create** and **re-checked at action**; if lapsed/revoked → delegation ineffective. |
| Time bounds | `starts_at < ends_at`; expire automatically; `revoked_at` immediate. |
| Ceiling | `min(delegator.direct.max_amount, delegation.max_amount)`; never above delegator. Unpriced: require `covers_unpriced` on delegator direct authority when path needs it. |
| Permission revoke mid-flight | Delegate loses `approve` or authority → excluded from pool; in-flight action fails closed. |
| Audit | Record delegator, delegate, `authorized_by`, window, amounts; step `acted_by` = delegate; optional `delegation_id`. |

#### 5. Data / API / worker / UI implications

- CHECK + exclusion constraint / validation on overlapping windows.  
- Create APIs: self (`approve` + direct authority) vs admin (`manage_delegations`).  
- UI: no chain visualization beyond direct rows.

#### 6. Failure handling

Invalid create → `422 PR_DELEGATION_INVALID` (subcodes Eng). Action-time miss → treat as not eligible (`403`/`422 PR_AUTHORITY_EXCEEDED` / SoD).

#### 7. Security and tenant isolation

No role shortcuts; XT 404; creator/FR SoD on acting user; delegation cannot grant missing `approve`.

#### 8. Acceptance tests (negative)

- B cannot re-delegate A’s authority.  
- `from == to` rejected.  
- Overlapping window rejected.  
- Ceiling > delegator rejected.  
- Creator-as-delegate on own PR rejected.  
- FR reviewer as final via delegation rejected.  
- Cross-tenant delegation impossible.  
- Revoked/expired ignored at action time.

#### 9. Residual risks

Mutual A↔B may surprise operators — document in UI help. Future chain support would need Owner CR.

#### 10. Closure status

**CLOSED (engineering)** — aligns with IV-D11; no Owner reopen.

---

### EC-03 — Unpriced approval path and fail-closed behavior

**Related:** IV-D2=B, IV-D3=A, IV-D6=A.

#### 1. Problem statement

How to route PRs with unpriced lines without inventing global bands or silently under-authorizing via priced subtotal.

#### 2. Alternatives considered

| Approach | Assessment |
|---|---|
| Treat null as 0 | **Forbidden** (under-authorization) |
| Always use priced subtotal only | **Forbidden** by IV-D2 |
| Platform default elevated band | **Forbidden** (no invented globals) |
| Tenant `applies_when_unpriced` rules + `covers_unpriced` authority | **Selected** — preserves IV-D2 |

#### 3. Recommended solution

Tenant-configured unpriced/elevated rules (`applies_when_unpriced`) + authority capability `covers_unpriced`; publish-time presence check; runtime fail-closed.

#### 4. Final proposed engineering contract

1. **Never** treat missing prices as zero.  
2. If `has_unpriced_lines`: match **only** rules with `applies_when_unpriced=true` (unknown path or elevated tier encoded by tenant).  
3. Do **not** consult priced-only rules for such PRs.  
4. Apply IV-D3 specificity among candidates; ambiguity → `PR_APPROVAL_RULE_AMBIGUOUS` / stay `submitted`.  
5. Publish: require ≥1 `applies_when_unpriced` rule or reject publish (`MATRIX_UNPRICED_RULE_REQUIRED`).  
6. Level eligibility: authority with `covers_unpriced=true`; if rule.`min_amount` not null, also `max_amount ≥ min_amount` (tenant figure).  
7. **All lines unpriced** / **mixed**: same `has_unpriced_lines` path.  
8. **No unpriced rule / no covers_unpriced holders**: IV-D6 (`PR_UNPRICED_PATH_MISSING` / empty pool code); notify admins (**EC-04**); retry after fix.  
9. **Resubmit after reprice:** if no unpriced lines remain, match normal priced rules; new instance (**IV-D8**).  
10. Final approve still requires qualifying FR (**D2**/**IV-D9**).

Does **not** redefine IV-D2=B.

#### 5. Data / API / worker / UI implications

- Rule flag + authority `covers_unpriced`.  
- Publish validation errors in API.  
- UI: explain unpriced path; show block reason when missing.

#### 6. Failure handling

Durable routing block; safe idempotent retry; no silent fallback to priced band.

#### 7. Security and tenant isolation

Tenant-only config; no cross-tenant rule inheritance; no platform seed bands.

#### 8. Acceptance tests

- Mixed/unpriced never route via priced-only rule.  
- Null≠0.  
- Ambiguous unpriced rules fail closed.  
- Missing path → `submitted` + admin notify.  
- Reprice+resubmit uses priced rules.  
- Final approve still needs FR.

#### 9. Residual risks

Tenants that publish without granting any `covers_unpriced` authority will block all unpriced PRs until fixed (intentional fail-closed).

#### 10. Closure status

**CLOSED (engineering)** — no Owner decision required.

---

### EC-04 — Administrative notification recipient resolution

**Related:** IV-D4=B, IV-D6=A, IV-D10=C, IV-D12=A, IV-D14=A.

#### 1. Problem statement

Who receives admin/escalation notifications when routing/config/SoD/SLA fails, without role-name fanout or cross-tenant leakage.

#### 2. Alternatives considered

| Approach | Assessment |
|---|---|
| Role-name (Owner/GM) broadcast | Forbidden (PERMISSION_MODEL / ADR-0013) |
| Dedicated recipient table | Extra MVP surface; not required |
| Explicit permission fanout via existing resolver | **Selected** — `usersWithPermission` already exists |

#### 3. Recommended solution

Resolve **Admin** recipients as active same-tenant users holding `purchase_requests.manage_approval_config`. Keep **Pool** recipients as live eligibility computation. Reuse `NotificationDispatcher` (afterCommit, dedupe, TenantAware fanout job).

#### 4. Final proposed engineering contract

**Recipient-selection algorithm**

1. Require `TenantContext`.  
2. **Pool events:** compute eligible approver user ids for active step (perms + authority + SoD + delegation EC-02); `filterActiveUsers`.  
3. **Admin events:** `NotificationRecipientResolver::usersWithPermission('purchase_requests.manage_approval_config')`.  
4. Union where table says Pool+Admin; dedupe by user id.  
5. Minimize payload: `pr_number`, status, block code — no matrix internals beyond code/message already on PR for authorized viewers.

**Event → recipient map**

| Event | Pool | Admin |
|---|---|---|
| Entered under_review / new active step | ✓ | |
| Due reminder / SLA escalate | ✓ | ✓ |
| IV-D6 routing block (matrix/rule/authority/unpriced/empty pool) | | ✓ |
| Final SoD empty pool (IV-D10) | | ✓ |
| Routing technical failure exhausted | | ✓ |
| Returned / rejected / approved / cancel-approved | requester lifecycle | |

**Empty admin set:** write/keep durable PR block + audit `PR_ADMIN_RECIPIENTS_EMPTY`; log warning; **do not** notify platform users, other tenants, or invent Owner broadcast; **do not** auto-approve. Visibility via PR detail for any future holder of `manage_approval_config` / support runbook.

**Delivery:** ADR-0013 — persist best-effort; unique `(tenant_id, dedupe_key)`; workflow never depends on inbox success.

#### 5. Data / API / worker / UI implications

- New `NotificationType` values (catalog extension at coding time).  
- No new permissions.  
- UI banners for blocks even when inbox empty.

#### 6. Failure handling

Notification insert failures swallowed/logged; durable domain state remains source of truth; reconciler/admin UI for ops.

#### 7. Security and tenant isolation

Permission-based only; active users; XT impossible; no sensitive banking/secrets in body; adversarial tests for wrong-tenant recipients = 0.

#### 8. Acceptance tests

- Admin event reaches only `manage_approval_config` holders.  
- Pool event does not spam all admins unless mapped.  
- Inactive/revoked perm users excluded.  
- Zero admins → audit code, no auto-approve.  
- Dedupe suppresses duplicate SLA spam.  
- Cross-tenant recipient count = 0.

#### 9. Residual risks

Tenants with zero config admins have only durable PR/audit visibility until permission granted (acceptable fail-closed ops).

#### 10. Closure status

**CLOSED (engineering)** — no Owner decision required.

---

### EC closure summary

| EC | Selection | Owner decision impact | Status |
|---|---|---|---|
| EC-01 | After-commit queue + reconciler (not outbox) | None (implements IV-D1/D6/D8) | **CLOSED** |
| EC-02 | Depth-1 delegation only | None (implements IV-D11) | **CLOSED** |
| EC-03 | Unpriced rules + `covers_unpriced` | None (implements IV-D2; no new bands) | **CLOSED** |
| EC-04 | `manage_approval_config` admin fanout | None (implements IV-D6/D10/D12/D14) | **CLOSED** |

# S04 Phase V — Detailed Design (Demand Groups & Control Reports)

> **Status:** `FINAL DESIGN OWNER ACCEPTED` (2026-11-01) — implementation **COMPLETED** — engineering **PASS** — functional acceptance **OWNER ACCEPTED — FINAL** (2026-11-01) — [S04-PHASE-V-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-V-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md) · evidence [PHASE_V_FINAL_IMPLEMENTATION_AND_ACCEPTANCE_EVIDENCE.md](PHASE_V_FINAL_IMPLEMENTATION_AND_ACCEPTANCE_EVIDENCE.md)  
> **Date:** 2026-11-01  
> **Owner decisions:** [S04-PHASE-V-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-V-OWNER-DECISIONS-2026-11-01.md) — **V-D1…V-D8 APPROVED**  
> **Design acceptance:** [S04-PHASE-V-FINAL-DESIGN-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-V-FINAL-DESIGN-ACCEPTANCE-2026-11-01.md)  
> **Does not:** grant functional acceptance · authorize deploy · authorize Git release · authorize S05  
> **Protects:** Phase IV FINAL functional acceptance (cancel_approved, engagement lock, FR gate, routing)  
> **Companions:** [PHASE_V_OWNER_DECISION_REGISTER.md](PHASE_V_OWNER_DECISION_REGISTER.md) · [PHASE_V_FAST_TRACK_IMPLEMENTATION_PLAN.md](PHASE_V_FAST_TRACK_IMPLEMENTATION_PLAN.md) · [PHASE_V_READINESS_AUDIT.md](PHASE_V_READINESS_AUDIT.md)

**Legend:** **OWNER** = binding V-D\* · **ENG** = engineering closure (EC-V\*) without new business choice · **OPEN** = escalated if any

---

## 0. Decision traceability

| ID | Owner choice | Design effect |
|---|---|---|
| **V-D1=A** | PR-header membership; ≤1 active group | EC-V01 membership model + MariaDB uniqueness |
| **V-D2=B** | Optional season/OU; mixed allowed + warnings | EC-V02 |
| **V-D3=A** | `draft→active→frozen→closed` | EC-V03 transition matrix |
| **V-D4=A** | Auto-unlink on `cancel_approved` | EC-V04 |
| **V-D5=A** | Engagement freezes membership edits | EC-V03 |
| **V-D6=A** | Four reports incl. compliance | EC-V06 RPT-V01…V04 |
| **V-D7=B** | Mutate=`consolidate_planning`; read via planning/`view` | EC-V05 |
| **V-D8=A** | CSV for all four reports (overrides defer) | EC-V07 |

ADR-0022 **123** / **130** remain the functional parent. Prior design text that deferred export is **void**.

---

## 1. Demand Groups — business definition

A **Demand Group** is a tenant-owned, planning-only collection of **approved** Purchase Request **headers** so planners can consolidate need **without** merging quantities into RFQ/PO, changing PR approvals, or creating S05 commitments (**123**, **V-D1**).

---

## 2. Data model (ENG — EC-V01)

### 2.1 `purchase_demand_groups`

| Column | Type / notes |
|---|---|
| `id` | PK |
| `tenant_id` | FK tenants; TenantOwned |
| `group_number` | string; unique `(tenant_id, group_number)`; sequence `FOR UPDATE` |
| `title`, `notes` | text |
| `status` | enum: `draft`,`active`,`frozen`,`closed` (**V-D3**) |
| `season_id` | nullable FK (**V-D2**) |
| `beneficiary_organization_unit_id` | nullable FK (**V-D2**) |
| `created_by` | FK users |
| `frozen_at`, `frozen_by`, `freeze_reason` | nullable; reason codes e.g. `manual`, `engagement` |
| `closed_at`, `closed_by` | nullable |
| timestamps; soft deletes **ENG:** soft-delete allowed only when `closed` or empty `draft` |

Indexes: `(tenant_id, status)`, `(tenant_id, season_id)`, `(tenant_id, beneficiary_organization_unit_id)`.

### 2.2 `purchase_demand_group_items` (history)

| Column | Notes |
|---|---|
| `id` | PK |
| `tenant_id` | TenantOwned |
| `demand_group_id` | FK groups |
| `purchase_request_id` | FK PRs (header) |
| `pr_version_no_at_link` | int snapshot of PR `version_no` at link |
| `linked_at`, `linked_by` | required |
| `unlinked_at`, `unlinked_by`, `unlink_reason` | nullable until unlink; reasons: `manual`, `cancel_approved`, `group_closed`, … |
| timestamps | |

**Not** uniquely constrained for history (many historical rows per PR across time).

### 2.3 `purchase_demand_group_active_memberships` (exclusive active index)

MariaDB **10.4** lacks reliable partial unique indexes. **ENG:**

| Column | Notes |
|---|---|
| `tenant_id` | PK part |
| `purchase_request_id` | PK part — **UNIQUE(tenant_id, purchase_request_id)** |
| `demand_group_id` | FK |
| `item_id` | FK history row |
| `created_at` | |

**Link transaction:** insert history item (`unlinked_at` null) → insert active membership row → on duplicate key → domain `DG_PR_ALREADY_GROUPED` (409).  
**Unlink transaction:** delete active row → set history `unlinked_at`/`unlinked_by`/`unlink_reason`.  
**Re-link after unlink:** new history row + new active insert allowed.

Cross-tenant: FKs + TenantOwned; PR must resolve under current tenant or **404**.

### 2.4 Number sequence

`purchase_demand_group_number_sequences` per tenant — same pattern as PR numbers (**ENG**).

---

## 3. Lifecycle & engagement freeze (OWNER V-D3/V-D5 — EC-V03)

### 3.1 Status meanings

| Status | Membership add/remove | Header edit (title/notes/scope) | Meaning |
|---|---|---|---|
| `draft` | Yes* | Yes | Planning draft |
| `active` | Yes* | Yes | In use |
| `frozen` | **No** | Title/notes only (**ENG**); scope fields locked | Membership locked |
| `closed` | **No** | **No** | Terminal historical |

\*Unless engagement lock applies (§3.3).

### 3.2 Authorized transitions

| From → To | Who | Notes |
|---|---|---|
| draft → active | `consolidate_planning` | Optional: require ≥1 active member (**ENG:** warn if empty, allow) |
| active → draft | `consolidate_planning` | Only if **no** engaged members |
| active → frozen | `consolidate_planning` **or** system engagement | Manual or `freeze_reason=engagement` |
| draft → frozen | same | Rare; allowed |
| frozen → active | `consolidate_planning` | **Only if** no member has `procurement_engaged_at`; else 422 `DG_ENGAGEMENT_ACTIVE` |
| frozen → closed | `consolidate_planning` | Terminal |
| active → closed | `consolidate_planning` | Allowed; implies freeze then close (**ENG:** single Action) |
| draft → closed | `consolidate_planning` | Empty or abandon |
| \* → draft from frozen/closed | **Forbidden** | |

All transitions audited.

### 3.3 Engagement-triggered freeze (**V-D5**)

**Trigger (read-only observe):** any **active** member PR with non-null `procurement_engaged_at` (written only by S05).

**Behavior:**

1. **Membership edits** (link/unlink) rejected with `DG_MEMBERSHIP_LOCKED_ENGAGED` while any active member is engaged — regardless of status.  
2. If group is `draft` or `active`, **idempotently** transition to `frozen` with `freeze_reason=engagement` when engagement is first observed on link attempt, unlink attempt, or an optional reconciler/read path (**ENG:** prefer fail-closed on mutate; optional background sync not required for MVP).  
3. If already `frozen` or `closed`: **no status change**; membership remains non-editable.  
4. Phase V Actions **never write** `procurement_engaged_at` / engagement actor fields.

**Race with S05:** S05 sets engagement in its transaction; Phase V mutate locks group row `FOR UPDATE`, re-reads member engagement, then proceeds or rejects.

---

## 4. Scope & mixed membership (OWNER V-D2 — EC-V02)

- `season_id` / `beneficiary_organization_unit_id` on group are **optional filters/labels**, not hard ACLs.  
- Linking PR: must be same tenant, `status=approved`, not soft-deleted.  
- **No** requirement that PR.season equals group.season (mixed allowed).  
- **Warnings (non-blocking)** on link / show when: member season ≠ group season (when both non-null); member OU ≠ group OU (when both non-null); group has both seasonal and administrative members.  
- Planning totals: sum **visible approved active members’** current priced estimates; unpriced lines counted separately — **never** coerce null→0.  
- Cancelled (unlinked) PRs excluded from current totals.  
- **No** new OU row-level PR ACL (**III-D5** preserved).

---

## 5. Cancel-approved auto-unlink (OWNER V-D4 — EC-V04)

**Integration point:** inside Phase IV `CancelApprovedPurchaseRequest` **after** PR successfully transitions to cancelled, **same DB transaction**, before commit.

**Steps:**

1. Find active membership for `purchase_request_id` (if any).  
2. Idempotent: if none → no-op.  
3. Else: delete active index row + stamp history unlink (`unlink_reason=cancel_approved`, actor = cancel actor).  
4. Audit `DEMAND_GROUP_PR_UNLINKED` (+ existing PR cancel audit unchanged).  
5. Do **not** alter cancel permission, engagement-lock preconditions, or cancel audit payload secrets.

**Races:** group link concurrent with cancel — unique active + group/PR locks; cancel wins on committed cancel (link sees non-approved → 422). Engagement + cancel: Phase IV rules unchanged; unlink still runs if cancel succeeds.

---

## 6. Authorization (OWNER V-D7 — EC-V05)

### 6.1 Permissions

| Perm | Use |
|---|---|
| `purchase_requests.consolidate_planning` | Create/update/transition group; link/unlink |
| `purchase_requests.view` | Read groups under §6.2 (existing PR visibility) |
| `purchase_requests.view_control_reports` | JSON reports + **CSV exports** (**V-D8**; **ENG:** same perm, explicit export routes + audit) |

No implicit grant of approve/reject/financial_review/etc.

### 6.2 Group read vs PR visibility contract

**Current binding PR Policy:** `purchase_requests.view` + same tenant ⇒ may view **any** tenant PR (no OU row ACL — III-D5).

Therefore under **current** contracts:

- Actor with `consolidate_planning` **or** `purchase_requests.view` may list/show groups in tenant.  
- Member PR payloads follow existing PR Resource authorization.  
- **Partial visibility is a no-op today.**

**Forward-compatible partial-visibility algorithm (if row ACL is ever added later without reopening III-D5 incorrectly):**

1. Group metadata (number, title, status, scope labels) visible if actor has consolidate_planning **or** can `view` **at least one** active member.  
2. Member list: include only members the actor can `view`; others omitted (not 403 on whole group).  
3. Totals/counts: compute **only** over authorized members; response includes `hidden_member_count` integer **without** identifiers.  
4. Cross-tenant group id → **404**. Unauthenticated → **401**. Missing mutate perm → **403**.  
5. Never expose hidden PR numbers/titles via warnings or export.

### 6.3 Policies

`PurchaseDemandGroupPolicy`: viewAny/view/create/update/link/unlink/transition.  
Report controllers: `view_control_reports` only.

---

## 7. API contracts

### 7.1 Demand groups

| Method | Path | Perm |
|---|---|---|
| GET/POST | `/api/v1/purchase-demand-groups` | view or consolidate (GET); consolidate (POST) |
| GET/PATCH | `/api/v1/purchase-demand-groups/{id}` | view or consolidate / consolidate |
| POST | `/api/v1/purchase-demand-groups/{id}/transition` | consolidate — body `{to, reason?}` |
| POST | `/api/v1/purchase-demand-groups/{id}/items` | consolidate — `{purchase_request_id}` |
| DELETE | `/api/v1/purchase-demand-groups/{id}/items/{purchaseRequestId}` | consolidate |

Stable error codes (ENG): `DG_PR_NOT_APPROVED`, `DG_PR_ALREADY_GROUPED`, `DG_MEMBERSHIP_LOCKED`, `DG_MEMBERSHIP_LOCKED_ENGAGED`, `DG_INVALID_TRANSITION`, `DG_ENGAGEMENT_ACTIVE`.

### 7.2 Control reports (JSON) — **V-D6**

| Method | Path |
|---|---|
| GET | `/api/v1/purchase-control-reports/approval-latency` |
| GET | `/api/v1/purchase-control-reports/overdue` |
| GET | `/api/v1/purchase-control-reports/outcomes` |
| GET | `/api/v1/purchase-control-reports/compliance` |

Common query: `from`,`to` (required, max span **366 days** ENG), `season_id?`, `organization_unit_id?`, `priority?`.

### 7.3 CSV export — **V-D8**

| Method | Path |
|---|---|
| GET | `/api/v1/purchase-control-reports/approval-latency/export.csv` |
| GET | `/api/v1/purchase-control-reports/overdue/export.csv` |
| GET | `/api/v1/purchase-control-reports/outcomes/export.csv` |
| GET | `/api/v1/purchase-control-reports/compliance/export.csv` |

Same filters + same perm `view_control_reports`. Content-Type `text/csv; charset=UTF-8`. **No XLSX.**

---

## 8. Four reports (OWNER V-D6 — EC-V06)

**Timezone:** tenant timezone (fallback `Asia/Riyadh`) for day boundaries; store/compare instants in UTC.

**Unpriced:** never treat null estimate as 0; expose `unpriced_line_count` / flags.

### RPT-V01 — Approval latency

- **Purpose:** time from `submitted_at` (current version) to terminal approve / reject.  
- **Sources:** `purchase_requests`, approval instances/steps.  
- **Metrics:** count; avg/median/p95 duration hours; optional per-level dwell (`acted_at - activated_at` or step created→acted).  
- **Include:** PRs that reached `approved` or `rejected` in range.  
- **Exclude from latency:** still in-flight; use separate in-flight count.  
- **Cancel after approve:** still include original approve latency; outcome reports classify cancel separately.

### RPT-V02 — Overdue / delays

- **Purpose:** pending steps with `due_at < now`, plus escalated steps.  
- **Sources:** `purchase_request_approval_steps` where status pending; SLA policy for context only.  
- **Columns:** PR number, level, due_at, hours overdue, escalated_at?, pool size hint without PII dump.

### RPT-V03 — Outcomes

- **Purpose:** counts by status in period: approved, rejected, returned (event), cancelled (incl. cancel_approved), submitted/under_review stocks.  
- **Sources:** PR status + status transition audits where helpful.  
- **Financial:** optional sum of priced estimates for approved (partial totals labeled).

### RPT-V04 — Compliance / control exceptions

- **Purpose:** control exceptions in range.  
- **Sources:** domain/security audit events (routing blocked, SoD/final-approver empty, authority exceeded, concurrent decision storms optional, cancel_approved, FR not cleared attempts).  
- **No** bank ciphertext; no tokens.  
- **Rows:** event_type, occurred_at, actor_id (if any), entity PR id/number if authorized, stable error codes.

**Acceptance examples:** fixture tenants with known durations/statuses → expected JSON aggregates within ±1s rounding; cross-tenant empty.

---

## 9. CSV export (OWNER V-D8 — EC-V07)

### 9.1 Authorization & audit

- Gate: `purchase_requests.view_control_reports` (**ENG:** no separate export permission unless Owner later adds one — **not OPEN**; explicit route + Policy method `exportControlReports`).  
- Audit every successful export: `CONTROL_REPORT_EXPORTED` with report_id, filters hash, row_count, actor, correlation_id.  
- Failed auth: 401/403; no file body.

### 9.2 Format

- UTF-8 **with BOM** (`EF BB BF`) for Excel Arabic compatibility (**ENG**).  
- Delimiter `,`; RFC4180 quoting; CRLF or LF documented as LF.  
- Formula-injection: prefix cells starting with `=`,`+`,`-`,`@` with leading `'`.  
- Stable header row per report (versioned in docs; bump only with changelog).  
- Empty result: headers only + audit row_count=0.  
- Same tenant/OU/season filters as JSON; same visibility (today: full tenant for permitted users).

### 9.3 Resource strategy

- **ENG:** synchronous streamed response for ≤ **10,000** rows; if filter would exceed → **422** `REPORT_EXPORT_TOO_LARGE` advising narrower dates (no queued jobs in Phase V MVP unless later authorized).  
- Max date span 366 days.  
- Memory: cursor/chunk queries; no loading full tenant into RAM.

### 9.4 Sensitive data

No IBAN/account/tokens; mask as Resources already do; compliance export uses event codes not secret payloads.

---

## 10. Arabic RTL UI

- Nav: مجموعات تخطيط الطلبات `/app/purchase-demand-groups` (`consolidate_planning` or `view` for entry; mutate CTAs gated).  
- تقارير اعتماد المشتريات `/app/purchase-control-reports` — four tabs + **تصدير CSV** per tab (`view_control_reports`).  
- Banner on groups: لا يُنشئ طلب عرض أسعار أو أمر شراء.  
- Mixed-scope warning banners per EC-V02.  
- Export progress/error toasts; empty CSV explained.

---

## 11. Audit / security events (ENG)

`DEMAND_GROUP_CREATED|UPDATED|TRANSITIONED|PR_LINKED|PR_UNLINKED`, `CONTROL_REPORT_VIEWED`, `CONTROL_REPORT_EXPORTED`.  
Correlation id mandatory; append-only.

---

## 12. Concurrency summary

| Scenario | Control |
|---|---|
| Dual link same PR | Active membership UNIQUE → one 409 |
| Link vs cancel_approved | Status check + txn; unlink idempotent |
| Link vs engagement | Lock group; re-read engagement |
| Dual transition | Row lock on group; validate from-status |
| Dual export | Read-only; independent |

---

## 13. Failure / recovery

- Partial link failure rolls back txn (no orphan active without history).  
- Cancel succeeds even if unlink fails **only if** compensated — **ENG binding:** unlink in **same** txn as cancel so both commit/fail together (preferred). If technical separation unavoidable, cancel remains source of truth and reconciler removes stale active rows — document in WP03 tests. **Preferred:** same transaction.  
- Report timeout → 503/504 safe message; no partial CSV without audit.

---

## 14. Phase IV / S05 boundaries

| Rule | |
|---|---|
| Grouping changes PR approval status? | **No** |
| Requires approved PR? | **Yes** |
| Writes engagement fields? | **No** |
| Creates RFQ/PO? | **No** |
| Weakens cancel_approved / engagement lock? | **No** |

---

## 15. Acceptance criteria (design targets)

| ID | Criterion |
|---|---|
| AC-V01 | Group CRUD tenant-scoped; 401/403/404/422 |
| AC-V02 | Only approved PRs link |
| AC-V03 | Cross-tenant → 404 |
| AC-V04 | Exclusive active membership under concurrent link |
| AC-V05 | Link/unlink does not mutate PR status/approvals |
| AC-V06 | cancel_approved auto-unlinks; history retained; totals exclude |
| AC-V07 | Engaged member blocks membership edits; no Phase V engagement writes |
| AC-V08 | Lifecycle transitions per matrix |
| AC-V09 | Mixed season/OU warnings; optional null scope |
| AC-V10 | RPT-V01…V04 correct on fixtures; unpriced not zeroed |
| AC-V11 | CSV four routes; auth; BOM; injection prefix; size limit; audit |
| AC-V12 | V-D7 read/mutate split; no sensitive perm implication |
| AC-V13 | Phase III/IV regression; zero RFQ/PO endpoints |
| AC-V14 | Arabic RTL pages + export UX |

---

## 16. Engineering closure checklist

| EC | Status |
|---|---|
| EC-V01 Exclusive membership | **CLOSED** — history + active uniqueness table |
| EC-V02 Optional/mixed scope | **CLOSED** |
| EC-V03 Lifecycle + engagement freeze | **CLOSED** |
| EC-V04 Cancel auto-unlink | **CLOSED** — same-txn preferred |
| EC-V05 Read visibility | **CLOSED** — aligns III-D5; forward-compatible redaction documented |
| EC-V06 Four reports | **CLOSED** |
| EC-V07 CSV export | **CLOSED** — V-D8=A; perm=`view_control_reports` |

### OPEN DESIGN DECISIONS

**None** that block final design acceptance under Owner V-D1…V-D8.

*(Non-blocking note: optional future Owner choice to split `export_control_reports` permission — not required by V-D8 text.)*

---

## 17. Residual risks

1. Large-tenant report/export performance — mitigated by date cap + row cap.  
2. Cancel/unlink same-txn coupling must be carefully reviewed in WP03 to avoid Phase IV regressions.  
3. S05 engagement writer not yet implemented — freeze rules tested with fixture timestamps.  
4. Phase II-C / Git release still open for overall S04 sprint closure.  
5. C: disk tight on some workstations (~4 GB).

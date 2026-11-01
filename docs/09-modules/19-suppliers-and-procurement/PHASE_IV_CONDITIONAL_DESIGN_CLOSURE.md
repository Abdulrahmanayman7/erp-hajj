# S04 Phase IV — Conditional Design Acceptance — Engineering Closure (EC-01…EC-04)

> **Status:** `ENGINEERING CLOSURE COMPLETE` — design subsequently **FINAL DESIGN OWNER ACCEPTED** (2026-11-01)  
> **Date:** 2026-11-01  
> **Owner decisions (unchanged):** [S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md) (**IV-D1–IV-D15**)  
> **Design acceptance:** [S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md)  
> **Design pack:** [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](PHASE_IV_APPROVAL_ENGINE_DESIGN.md) (§19 authoritative detail)  
> **WP01 plan:** [PHASE_IV_WP01_IMPLEMENTATION_PLAN.md](PHASE_IV_WP01_IMPLEMENTATION_PLAN.md) — **implementation NOT AUTHORIZED**  
> **Coding / migrations:** **NOT AUTHORIZED**  
> **Not claimed:** Phase IV functionally implemented

## 1. Purpose

The Owner conditionally accepted the Phase IV Approval Engine detailed design subject to closing four engineering design questions. This document records the proposed engineering contracts and traceability. It does **not** invent Owner product decisions and does **not** authorize implementation.

## 2. Traceability matrix

| EC | Title | Binding Owner decisions | Selected contract (summary) | Closure |
|---|---|---|---|---|
| **EC-01** | Routing trigger & reliability | **IV-D1=A**, **IV-D6=A**, **IV-D8=A** | After-commit queued `RoutePurchaseRequestApprovalJob` + `TenantAware` + unique instances + bounded retry + reconciler; **no** transactional outbox table | **CLOSED** |
| **EC-02** | Delegation depth & cycles | **IV-D4=B**, **IV-D5=A**, **IV-D10=C**, **IV-D11=C** | **Depth-1 only**; no re-delegation; `from≠to`; no overlapping active pairs; acting-user SoD; ceiling ≤ delegator | **CLOSED** |
| **EC-03** | Unpriced path & fail-closed | **IV-D2=B**, **IV-D3=A**, **IV-D6=A** | Match only `applies_when_unpriced` rules; never null→0; never priced-subtotal bypass; authority `covers_unpriced`; publish requires ≥1 unpriced rule; else stay `submitted` | **CLOSED** |
| **EC-04** | Admin notification recipients | **IV-D4=B**, **IV-D6=A**, **IV-D10=C**, **IV-D12=A**, **IV-D14=A** | Admin = active tenant holders of `purchase_requests.manage_approval_config` via existing resolver; pool separate; empty admins → durable audit/block, no auto-approve | **CLOSED** |

**OWNER DECISION REQUIRED:** none for EC-01…EC-04.

## 3. Infrastructure basis (read-only verification)

| Capability | Existing support | Used by |
|---|---|---|
| Queued jobs + tenant restore | `TenantAware` + `RestoreTenantContext` | EC-01, EC-04 fanout |
| After-commit side effects | `NotificationDispatcher` `DB::afterCommit` | EC-01 dispatch pattern |
| Permission-based recipients | `NotificationRecipientResolver::usersWithPermission` | EC-04 |
| In-app dedupe | `(tenant_id, dedupe_key)` unique (ADR-0013) | EC-04 |
| Transactional outbox table | **Not present** | Intentionally **not** introduced (EC-01) |

## 4. Contracts (concise)

### EC-01

Submit remains III (`submitted`). Routing runs after commit on a tenant-aware queue job. Idempotency via unique `(tenant_id, purchase_request_id, version_no)` on approval instances. Configuration blocks are durable IV-D6 (job succeeds). Technical failures retry then mark `PR_ROUTING_TECHNICAL_FAILURE` + `failed_jobs`. Scheduler reconciler + admin `POST …/route` recover stranded rows. No auto-approve.

### EC-02

One-level delegations only. Self-delegation = delegate **own direct** authority to another user. Delegate must hold `approve` independently. SoD uses acting user. Immediate revoke/expiry. No chains, no self-recipient, no overlapping active identical pairs.

### EC-03

Unpriced/mixed PRs route only through tenant `applies_when_unpriced` rules. Authority needs `covers_unpriced` (plus amount check if rule `min_amount` set). No global bands. Missing path → `submitted` + block code + admin notify. Reprice+resubmit may leave unpriced path. FR still mandatory before final approve.

### EC-04

Pool vs Admin recipient classes. Admin permission = `manage_approval_config` only (no role names). Reuse ADR-0013 delivery semantics. Zero admins → `PR_ADMIN_RECIPIENTS_EMPTY` audit + durable PR visibility; never cross-tenant or auto-approve.

## 5. Design-only schema / API / worker impacts

| Area | Proposed when coding authorized |
|---|---|
| Schema | `covers_unpriced` on authorities; routing block columns; instance unique key; delegation CHECKs; **no** outbox table |
| Workers | `RoutePurchaseRequestApprovalJob`; reconciler command; SLA scanner (notify-only) |
| API | Publish validation for unpriced rule; admin route retry; delegation create rules |
| Notifications | New curated types for PR approval/admin events |
| UI | Block banners; retry CTA; delegation depth-1 UX copy |

## 6. Consistency verification

- [x] IV-D1…IV-D15 text unchanged in Owner register  
- [x] EC-01…EC-04 have explicit resolutions  
- [x] No contradiction with approved product behavior  
- [x] Phase III submit + FR model preserved; IV-D9 `under_review` FR still a deferred coding extension  
- [x] No new global financial bands or SLA defaults  
- [x] No Phase V / S05 scope  
- [x] Documentation only in this task  

## 7. Residual risks

1. Queue outage delays `submitted`→`under_review` until reconciler/manual retry (visible).  
2. Tenants without `manage_approval_config` holders rely on durable PR/audit only for admin alerts.  
3. Tenants without `covers_unpriced` grants cannot route unpriced PRs until configured (intentional).  

## 8. Owner design acceptance (recorded)

Final Owner design acceptance was granted **2026-11-01** — [S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md).

**IV-WP01 implementation remains NOT AUTHORIZED** until a separate Owner coding authorization (**IV-D15**).

# S04 G2 Phase II-B — Supplier Banking Design

> **Status:** `DESIGN — OWNER APPROVED (2026-11-01) — IMPLEMENTATION NOT AUTHORIZED`  
> **Owner design approval:** [S04-PHASE-II-B-DESIGN-APPROVAL-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-DESIGN-APPROVAL-2026-11-01.md)  
> **Owner decisions:** 2026-11-01 — [S04-PHASE-II-B-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-OWNER-DECISIONS-2026-11-01.md) (**IIB-D1–IIB-D12 APPROVED**)  
> **Doc closure (M1–M3):** [S04-PHASE-II-B-DOC-CLOSURE-M1-M3-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-DOC-CLOSURE-M1-M3-2026-11-01.md)  
> **Independent re-gate:** **PASS — READY FOR OWNER DESIGN APPROVAL**  
> **Last updated:** 2026-11-01  
> **Builds on:** ADR-0022 **110**, ADR-0023 §2.5, II-D2, Phase I contacts patterns, Phase II-A Documents (optional evidence only)  
> **Excludes:** II-C evaluations, Phase III PR, S05 RFQ/PO, payments, accounting  
> **Coding / migrations / commit / push / merge / deploy:** **NOT AUTHORIZED**  
> **Phase II-B implementation status:** **COMPLETE — FUNCTIONALLY CLOSED — OWNER ACCEPTED** (2026-11-01; WP-01–WP-05) — [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md). Functional closure ≠ commit/push/merge/deploy authorization.

Legend: **(Owner)** = IIB-D* / II-D2 / ADR binding; **(Eng)** = technical proposal consistent with Owner decisions.

---

## 0. Binding Owner decisions (2026-11-01)

| # | Choice | Rule |
|---|---|---|
| **IIB-D1** | A | Account holder name is **mandatory**. |
| **IIB-D2** | A | Bank name is **free text** for MVP; **no** bank master catalog. |
| **IIB-D3** | B | Require **at least one** of IBAN **or** local account number. Prefer IBAN for Saudi accounts; **do not** silently make IBAN mandatory. |
| **IIB-D4** | A | Creator **or any contributing editor** of an account **version** cannot verify that same version (hard SoD). |
| **IIB-D5** | B | Verification evidence Document is **optional**. |
| **IIB-D6** | A | Duplicate IBAN within the same **tenant** is **prohibited**. |
| **IIB-D7** | B | Support status **`revoked`** for post-verification invalidation. |
| **IIB-D8** | B | Verified identifiers + verified history are **immutable**; changes require a **new pending version** via supersession workflow. |
| **IIB-D9** | C | Only **verified** accounts may be primary; **reject** or **revoke** clears primary. |
| **IIB-D10** | A | **SAR only** for the initial release. |
| **IIB-D11** | A | During supersession, **retain** old verified primary; pending replacement never auto-primary; verify alone does **not** transfer primary; transfer only via explicit authorized action; revoke clears primary; **no** auto-promotion. |
| **IIB-D12** | A | Full identifiers **masked by default**; full-value access **only** via dedicated audited reveal with `view_banking` + mandatory reason. |

### Binding non-banking constraint

| # | Rule |
|---|---|
| **II-D2** | A verified bank account is **NOT** required for supplier activation. Banking must not alter `EligibilityService`, activation rules, II-A document requirements, Contracts, or Inventory. |

---

## 1. Business scope and exclusions

### In scope (II-B)

- Tenant-isolated supplier bank-account versions (create, edit pending, verify, reject, revoke, supersede).  
- Primary designation (verified-only).  
- Encryption at rest, masking by default, sensitive RBAC.  
- Optional Document evidence for verification (ADR-0010).  
- Arabic RTL UI on supplier details banking tab.  
- Audit without plaintext identifiers.

### Out of scope

- Payments, settlements, payroll, accounting ledgers.  
- External bank / IBAN registry APIs (unless later Owner CR).  
- Bank master catalog (**IIB-D2=A**).  
- Multi-currency (**IIB-D10=A** → SAR only).  
- Coupling banking into eligibility or activation (**II-D2**).  
- II-C evaluations, Phase III PR, S05.

---

## 2. Domain entities

### 2.1 Concepts

| Concept | Meaning |
|---|---|
| **Account family** | Stable logical bank account for a supplier (`account_key`), spanning versions |
| **Account version** | One immutable-after-verify row (`supplier_bank_accounts`) with status |
| **Current version** | Family head for edit/supersede UX (`current_slot = 0`); superseded versions keep `current_slot = id` |
| **Primary** | At most one **verified, non-revoked** version row per supplier may be primary — **independent of `current_slot`** (**IIB-D11**) |
| **Status** | `pending` \| `verified` \| `rejected` \| `revoked` — version lifecycle |
| **Editors** | All users who created/edited the pending version (SoD set for verify) |

### 2.2 Proposed table: `supplier_bank_accounts` (tenant-owned)

| Column | Notes |
|---|---|
| `id` | BIGINT PK |
| `tenant_id` | FK tenants; indexed; never from request |
| `supplier_id` | FK suppliers (same tenant); RESTRICT |
| `account_key` | VARCHAR(64) — stable family id within supplier (UUID string **(Eng)**) |
| `version_no` | UNSIGNED INT — monotonic per `(tenant_id, supplier_id, account_key)` |
| `label` | VARCHAR nullable — user label |
| `account_holder_name` | VARCHAR **required** (**IIB-D1**) |
| `bank_name` | VARCHAR **required** free text (**IIB-D2**) |
| `iban_ciphertext` | TEXT NULL — app-encrypted IBAN |
| `account_ciphertext` | TEXT NULL — app-encrypted local account number |
| `iban_last4` | CHAR(4) NULL |
| `account_last4` | CHAR(4) NULL |
| `iban_fingerprint` | CHAR(64) NULL — keyed HMAC of normalized IBAN; stored on **every** version for history (**not** alone used for uniqueness — see §2.4) |
| `currency` | CHAR(3) **fixed `SAR`** (**IIB-D10**); reject other values |
| `status` | `pending` \| `verified` \| `rejected` \| `revoked` |
| `current_slot` | BIGINT — `0` = family head (latest pending/replacement UX); superseded use `id` (**Eng**) |
| `is_primary` | BOOLEAN — **orthogonal to `current_slot`** (**IIB-D11**); only `status=verified` may be true (**IIB-D9**) |
| `primary_flag` | Generated `CASE WHEN is_primary = 1 THEN 1 ELSE NULL END`; `UNIQUE (supplier_id, primary_flag)` |
| `supersedes_id` | Self FK NULL — prior version this pending replaces |
| `evidence_document_id` | FK documents NULL — optional (**IIB-D5**) |
| `created_by` | FK users |
| `last_edited_by` | FK users NULL |
| `verified_by` / `verified_at` | NULL until verify |
| `rejected_by` / `rejected_at` / `rejection_reason` | on reject |
| `revoked_by` / `revoked_at` / `revocation_reason` | on revoke |
| timestamps | |

**Uniques / indexes on `supplier_bank_accounts` (proposed):**

1. `UNIQUE (tenant_id, supplier_id, account_key, version_no)`  
2. `UNIQUE (tenant_id, supplier_id, account_key, current_slot)` — one current head per family  
3. `UNIQUE (supplier_id, primary_flag)` generated — at most one primary per supplier  
4. Indexes: `(tenant_id, supplier_id)`, `(tenant_id, status)`, `(evidence_document_id)`, `(tenant_id, iban_fingerprint)` non-unique for lookups  

**Do not** put `UNIQUE (tenant_id, iban_fingerprint)` on version rows — that is **C1** (blocks same-IBAN supersession while prior remains `verified`). Tenant-wide IBAN exclusivity is enforced via **§2.4 claims table**.

### 2.3 Proposed table: `supplier_bank_account_editors` (tenant-owned)

Additive SoD history (**IIB-D4**), parallel to II-A M5:

| Column | Notes |
|---|---|
| `tenant_id`, `bank_account_id`, `user_id` | FKs |
| `role` | `creator` \| `editor` |
| timestamps | |
| **UNIQUE** `(bank_account_id, user_id)` | |

Verify rejects if actor appears in editors for that version.

### 2.4 IBAN claim model (**IIB-D6** + supersession + **IIB-D11**) — C1 remediation

#### Why a naive per-row generated claim fails

Gate-suggested sketch:

```sql
iban_claim = CASE
  WHEN current_slot = 0 AND status IN ('pending','verified')
  THEN iban_fingerprint ELSE NULL END
-- UNIQUE (tenant_id, iban_claim)
```

| Scenario | Result |
|---|---|
| Same-IBAN supersession while prior is **primary** | Prior (`current_slot≠0`, `is_primary=1`, still `verified`) releases claim under this formula; new pending takes claim — OK for uniqueness, **but** if we instead OR-in `is_primary` to protect the old IBAN, **both** prior primary and new current head would claim the **same** fingerprint → unique violation. |
| Different-IBAN supersession while prior is **primary** | Prior releases claim under `current_slot=0`-only formula → **another supplier can claim the old primary’s IBAN** while it remains operationally primary — **violates IIB-D6 spirit / D11 usability**. |

**Conclusion:** A single generated column on `supplier_bank_accounts` cannot simultaneously (a) allow same-family same-IBAN supersession with retained primary and (b) stop foreign families from claiming an IBAN still held by a verified primary. **C1 is not closed by adopting that expression.**

#### Chosen model: tenant IBAN claims table **(Eng)**

```text
supplier_bank_iban_claims  (tenant-owned)
─────────────────────────────────────────
tenant_id              FK → tenants
iban_fingerprint       CHAR(64)  -- normalized HMAC
account_key            VARCHAR(64)  -- owning family within supplier
supplier_id            FK → suppliers (same tenant)
holder_bank_account_id FK → supplier_bank_accounts.id  -- version that last asserted the claim
UNIQUE (tenant_id, iban_fingerprint)
INDEX (tenant_id, account_key)
INDEX (tenant_id, supplier_id)
```

**Claim meaning:** At most one **account family** per tenant may **own** a given IBAN fingerprint. Ownership is independent of which version is `current_slot=0` and independent of which version is primary.

#### Ownership integrity (**M2**)

Before any claim INSERT/UPDATE (and when setting `holder_bank_account_id`), application **must** assert in the same transaction:

1. `claim.tenant_id` = authenticated tenant = `supplier.tenant_id` = `holder.tenant_id`.  
2. `claim.supplier_id` = `holder.supplier_id` = path supplier.  
3. `claim.account_key` = `holder.account_key` (no cross-family reassignment).  
4. `claim.iban_fingerprint` = fingerprint derived from the holder version’s current IBAN (when the write asserts that IBAN).  
5. `holder_bank_account_id` references a row that still exists and belongs to that family/supplier/tenant.

**DB constraints (feasible):**

| Constraint | Role |
|---|---|
| `UNIQUE (tenant_id, iban_fingerprint)` | Hard: one live claim per IBAN per tenant |
| `FK holder_bank_account_id → supplier_bank_accounts(id)` | Hard: holder must exist (prefer `ON DELETE RESTRICT` while claim points at it; release deletes claim first) |
| `FK supplier_id → suppliers(id)` | Hard: supplier exists |
| Composite “holder belongs to claim family” | **Not fully expressible** in MySQL without triggers; **mandatory application check** under lock |

**Forbidden:** transferring a claim to another `account_key` via a stale `holder_bank_account_id`; leaving orphan claim rows after safe release; releasing while any pending/verified family version still requires the fingerprint.

**Acquire / refresh claim** (same DB transaction as **any** write that introduces or changes a version’s IBAN — create, supersede, **pending PATCH**, and verify-path writes that keep an IBAN):

1. `SELECT … FOR UPDATE` supplier, then existing claim row for `(tenant_id, fingerprint)` if any (fingerprint lock order ascending when multiple).  
2. If claim exists and `account_key` ≠ this family → `422 SUPPLIER_BANK_IBAN_DUPLICATE`.  
3. If claim missing → `INSERT` claim for this family; set `holder_bank_account_id` to the writing version (after ownership checks).  
4. If claim exists and same `account_key` → `UPDATE` holder pointer only (same-family same-IBAN); **never** change `account_key`/`supplier_id` on an existing claim.  

**Release claim** (only when safe) — **hard removal (**M2**)**:

Release fingerprint `F` for family `K` iff **no** row remains in `supplier_bank_accounts` with:

`tenant_id` + `account_key=K` + `iban_fingerprint=F` + `status IN ('pending','verified')`.

When safe: **`DELETE` the claim row** for `(tenant_id, F)` (hard removal / equivalent hard clear — **not** soft-delete, **not** nulling fingerprint while leaving a row that still occupies the unique slot). Historical `rejected`/`revoked` version rows **keep** their `iban_fingerprint` columns for integrity; they simply do not sustain a claim.

Run release checks after reject / revoke / **pending PATCH that changes away from F** (and after any future hard-delete — not in MVP). On any failure mid-transaction, **roll back** version writes **and** claim INSERT/UPDATE/DELETE together.

#### Pending PATCH IBAN maintenance (**M1**)

`PATCH …/bank-accounts/{id}` is allowed only for `status=pending`.

| PATCH content | Claims / ciphertext behavior |
|---|---|
| Changes IBAN (or replaces IBAN after normalize differs) | Normalize → compute tenant-bound keyed fingerprint → acquire/validate new claim (reject if owned by another family) → update `iban_ciphertext` + `iban_last4` (+ fingerprint on version) → release **previous** fingerprint claim **only if** no pending/verified version in the **same** family still requires the old fingerprint → all in one txn with approved lock order |
| Changes only non-identifier metadata (label, holder name, bank_name, local account without IBAN change, evidence pointer, etc.) | **Must not** acquire/release IBAN claims; **must not** alter `iban_fingerprint` / IBAN ciphertext |
| Clears IBAN while retaining local account (still ≥1 identifier per **IIB-D3**) | Treat as “change away from F”: release F if safe; clear IBAN ciphertext/last4/fingerprint on **this** pending version only; preserve other versions’ history |
| Attempts identifier change on verified/rejected/revoked | `422 SUPPLIER_BANK_IMMUTABLE` / `SUPPLIER_BANK_INVALID_STATUS` — no claim mutation |

Preserve historical fingerprints on other versions. Return stable codes: `SUPPLIER_BANK_IBAN_DUPLICATE`, `SUPPLIER_BANK_IDENTIFIER_REQUIRED`, `SUPPLIER_BANK_INVALID_STATUS`, `SUPPLIER_BANK_IMMUTABLE`.

#### Lifecycle proofs

| Event | Claims behavior | Primary / D11 |
|---|---|---|
| Pending create (new family, IBAN F) | Insert claim `(F → K)` | `is_primary=false` |
| Pending PATCH IBAN F1→F2 (only live F1 in family) | Acquire F2; **DELETE** claim F1 | Unchanged |
| Pending PATCH metadata only | Claims unchanged | Unchanged |
| Verify | Claim unchanged | No primary change |
| Reject pending (only live F in family) | **DELETE** claim F | n/a |
| Same-IBAN supersession (V1 verified±primary, V2 pending, both F) | Claim stays `(F → K)`; refresh holder → V2 | V1 may remain primary (**IIB-D11**) |
| Different-IBAN supersession (V1 primary F1, V2 pending F2) | Keep claim F1 for K; acquire claim F2 for K | V1 stays primary; F1 **not** free for other suppliers |
| Set-primary V2 | Claims unchanged | Explicit demote V1 / promote V2 |
| Revoke V1 (F1) while V2 holds F2 | **DELETE** F1 if no other pending/verified F1 in K; F2 remains | Clear V1 primary; no auto-promote |
| Concurrent other supplier claims F | Unique on claims + row lock → duplicate error | — |
| Concurrent release vs acquire | Claim row lock + unique; winner consistent; loser 422 | — |

**SQL uniqueness (proposed):**

```sql
UNIQUE KEY uq_bank_iban_claim_tenant_fp (tenant_id, iban_fingerprint)
```

on `supplier_bank_iban_claims` only — **not** on historical version fingerprints.

#### Rejected alternatives

| Alternative | Why rejected |
|---|---|
| `UNIQUE (tenant_id, iban_fingerprint)` on versions | Blocks same-IBAN supersession (C1) |
| Claim = current head only | Lets foreign family steal IBAN of superseded verified primary (D11 conflict) |
| Claim = current head OR primary | Same-IBAN supersession with retained primary → dual claim collision |
| Soft-delete / leave empty claim row | Occupies unique slot → blocks legitimate reuse (**M2**) |
| Transfer claim `account_key` via stale holder | Cross-family hijack (**M2**) |

---

## 3. Lifecycle and transitions

### 3.1 Status matrix (account **version**)

| From \ To | pending | verified | rejected | revoked |
|---|---|---|---|---|
| *(new)* | Create | — | — | — |
| pending | *(edit in place)* | Verify | Reject | — |
| verified | — | — | — | Revoke |
| rejected | — | — | — | — (terminal for version) |
| revoked | — | — | — | — (terminal for version) |

**Supersession (IIB-D8 + IIB-D11):** From a **verified** source version (Eng: supersede-from-verified only; rejected → new family — open eng Q). Creating a replacement:

1. Insert new row: `status=pending`, `version_no+1`, `supersedes_id=<prior>`, same `account_key`, **`is_primary=false`**.  
2. Move prior off family head: `prior.current_slot = prior.id`.  
3. New row `current_slot = 0`.  
4. **Do not** auto-revoke prior. Prior stays `verified`.  
5. **If prior was primary:** leave `prior.is_primary=true` (**IIB-D11=A**). Primary remains on the superseded (non-current) verified row.  
6. **Do not** auto-promote pending or later-verified replacement.

### 3.2 Distinctions — separate axes

| Axis | Values / rule |
|---|---|
| **Status** | pending / verified / rejected / revoked |
| **Family current** | `current_slot=0` (UX head for edit/history) vs historical `current_slot=id` |
| **Primary** | Independent boolean on a **verified** row; **may** sit on a non-current (`current_slot≠0`) verified version during replacement (**IIB-D11**) |
| **Verification** | Transition to `verified` — does **not** imply primary |

### 3.3 Why primary ≠ current_slot (design choice for IIB-D11)

**Problem:** If primary required `current_slot=0`, superseding a primary would force clearing or moving primary when the old row leaves the head — contradicting **IIB-D11=A**.

**Chosen representation (Eng, consistent with Owner):**

- `current_slot` = which version is the family’s **edit head**.  
- `is_primary` / `primary_flag` = which **verified** row is the supplier’s operational primary — **no dependency on `current_slot`**.  
- Unique `primary_flag` still enforces ≤1 primary per supplier.  
- List UX: show family head **and** highlight primary even when primary is a historical verified version (badge “أساسي — إصدار سابق قيد الاستبدال”).

**Tradeoffs:** UI must show primary that is not the family head; “resolve primary account for ops” queries `is_primary=true AND status=verified`, not `current_slot=0`. Avoids inventing a second pointer table.

### 3.4 Primary rules (**IIB-D9 + IIB-D11**)

| Action | Primary effect |
|---|---|
| Supersede verified primary | **Retain** primary on old verified row; new pending never primary |
| Verify replacement | **No** primary change |
| `set-primary` on verified target (any `current_slot`) | Demote others; set target primary (explicit transfer) |
| `clear-primary` | Clear flag; **no** auto-promote |
| Reject pending | Clear primary on that row if any (pending should never be primary) |
| Revoke verified (incl. old primary) | Clear its primary; **no** auto-promote |

`set-primary` requires `status=verified` only — **not** `current_slot=0`.

### 3.5 Sequence (canonical)

1. V1 verified + primary, `current_slot=0`.  
2. Supersede → V2 pending current; V1 historical **still primary**.  
3. Verify V2 → V2 verified, still **not** primary; V1 still primary.  
4. Explicit `set-primary` on V2 → V1 demoted, V2 primary.  
5. Or revoke V1 → V1 not primary; supplier may have **no** primary until explicit set.

---

## 4. Encryption, fingerprint, masking

### 4.1 Encryption at rest **(Eng)**

- Encrypt IBAN and local account with Laravel application key (`APP_KEY`) via dedicated cast/service (same class of protection as tenant mail password).  
- Persist `*_last4` at write time from plaintext **before** encrypt; never recompute from ciphertext in UI paths that lack decrypt permission.  
- **Key rotation / recovery:** ops runbook remains **ENGINEERING PROPOSAL** (ADR-0023 open item). Design requires: documented rotation procedure before production; decrypt-fail → stable error without leaking ciphertext.

### 4.2 IBAN normalization + fingerprint (**IIB-D6**)

**(Eng)** locked algorithm:

1. Trim; uppercase; strip spaces and non-alphanumerics.  
2. Structural validation (country length pattern); full IBAN checksum **recommended** for `SA`.  
3. `iban_fingerprint = HMAC-SHA256(pepper, tenant_id || ':' || normalized_iban)` hex (tenant bound into message).  
4. Pepper: `BANKING_IBAN_PEPPER` / `config/banking.php` — never log; distinct from `APP_KEY`.  
5. Persist fingerprint on **every** version row; enforce tenant exclusivity via **`supplier_bank_iban_claims`** (§2.4), not a unique index on version fingerprints.  
6. **Reuse after release (E3):** another family may acquire fingerprint `F` only after claim release (no remaining pending/verified row with `F` in the prior owning family).

### 4.3 Masking and reveal (**IIB-D12=A**)

| Surface | Behavior |
|---|---|
| List / show | **Always masked** (last4 only); never plaintext even if actor has `view_banking` |
| Reveal | Dedicated `POST …/reveal` only (**IIB-D12**) |
| Logs / exceptions / audit / telemetry / URLs | Never plaintext or ciphertext IBAN/account |
| Frontend | No auto-reveal on load; no persistent cache of revealed values |

#### Reveal contract (proposed)

- **Permission:** `suppliers.view_banking` (sensitive explicit-only).  
- **Body:** `{ "reason": "<string>" }` — **E1:** trim; require length ≥ **10**; else `422 SUPPLIER_BANK_REVEAL_REASON_REQUIRED` (+ deny audit).  
- **Authz before decrypt:** permission + tenant + row; fail → **404** (cross-tenant/missing) or **403** (no perm) with deny audit.  
- **Success:** decrypt once; `Cache-Control: no-store, private`.  
- **Audit:** `_REVEALED` / `_REVEAL_DENIED` — actor, supplier_id, bank_account_id, account_key, version_no, reason, outcome, correlation ID — **never** plaintext/ciphertext.  
- **Revoked / historical (E4):** reveal **allowed** via this endpoint only (masked list/show unchanged); still requires `view_banking` + valid reason.  
- **Rate limit:** dedicated limiter on reveal **(Eng)**.

### 4.4 Rate limiting

Reveal + verify/reject: platform rate-limiter patterns **(Eng)**.

### 4.5 Locked engineering defaults (E1–E5)

| ID | Default | Notes |
|---|---|---|
| **E1** | Reveal reason: trim; **min 10** chars; reject empty/short; never put decrypted IDs in audit | Consistent with IIB-D12 |
| **E2** | Revoke requires **`manage_banking`** only; tenant checks + audit; does **not** bypass IIB-D4 (revoke ≠ verify) | |
| **E3** | IBAN claim via §2.4; create/supersede/**pending PATCH**; same-family refresh holder; safe release **DELETE**s claim row; historical fingerprints preserved | Does not weaken IIB-D6/D11 |
| **E4** | Reveal of revoked/historical versions allowed only through audited reveal | Ordinary list/show stay masked |
| **E5** | Supersede **requires complete** `iban` and/or `account_number` (+ holder/bank_name as create); **no** silent ciphertext copy from prior; metadata (`label`) may copy if omitted | Replacement always `pending`, `is_primary=false` |

---

## 5. RBAC and SoD

### 5.1 Permissions (sensitive explicit-only — **D4**)

| Permission | Behavior |
|---|---|
| `suppliers.view` | Banking tab **masked** metadata only — **no** decrypt |
| `suppliers.view_banking` | **Only** authorize dedicated reveal (**IIB-D12** / **E1**/**E4**); does **not** unmask list/show |
| `suppliers.manage_banking` | Create/edit pending; supersede (**E5**); set/clear primary; optional evidence; **revoke (E2)** |
| `suppliers.verify_banking` | Verify or reject **pending** only (SoD **IIB-D4**) |

All three banking verbs: add to `PermissionCatalog::sensitiveExplicitOnlyPermissions()` — **never** default role templates (including Tenant Owner template).

### 5.2 SoD (**IIB-D4**)

- On create: insert editor row `creator`.  
- On every successful pending PATCH: upsert editor `editor` for actor.  
- On verify/reject: if `actor_id` ∈ editors for that version → `403` + `SUPPLIER_BANK_SOD_VIOLATION`.  
- Intermediate editors included (full set).

### 5.3 Cross-tenant

Missing / foreign tenant bank id → **404** (never 403 for cross-tenant).

---

## 6. Documents integration (**IIB-D5**)

- Optional `evidence_document_id` on the **pending/verified** version.  
- Document must be same tenant; prefer `linkable_type=supplier` + `linkable_id=supplier_id` (**Eng**) — **no new morph required** for MVP proposal.  
- Alternative **(Eng proposal only):** morph alias `supplier_bank_account` — **not assumed**; if needed, flag at engineering gate.  
- Evidence is **not** an II-A eligibility requirement and must not feed `EligibilityService`.  
- Delete Document while referenced as banking evidence → block with stable code (propose `DOCUMENT_USED_AS_BANK_EVIDENCE` or reuse pattern) — **(Eng)** lock at gate.  
- Preserve ADR-0010 immutability of file bytes.

---

## 7. II-A / lifecycle compatibility (mandatory)

| Must not change | Rule |
|---|---|
| `EligibilityService` | Banking ignored |
| Activation | `under_review → active` eligibility unchanged; **no** bank prerequisite (**II-D2**) |
| II-A document rules | Unchanged |
| Supplier status auto-change | Bank revoke ≠ supplier suspend (**II-D3** spirit) |
| Contracts / Inventory | No bank coupling |
| Payments | Forbidden |

Regression tests: IIA-T9-class activation without any bank account.

---

## 8. API contracts — **DESIGN APPROVED — NOT IMPLEMENTATION-AUTHORIZED**

Base: `/api/v1/suppliers/{supplier}` · Sanctum · Form Requests · Resources · Policies · correlation ID.

See also [API.md §13](API.md). Summary:

| Method | Path | Perm | Purpose |
|---|---|---|---|
| GET | `/bank-accounts` | `suppliers.view` | List (heads + optional history) — **always masked** |
| POST | `/bank-accounts` | `manage_banking` | Create pending (new family) |
| GET | `/bank-accounts/{id}` | `suppliers.view` | Show — **always masked** |
| PATCH | `/bank-accounts/{id}` | `manage_banking` | Edit **pending** only; IBAN change → claim acquire/release (**M1**); metadata-only must not release claims |
| POST | `/bank-accounts/{id}/supersede` | `manage_banking` | Full identifier body (**E5**); retain old primary (**IIB-D11**); refresh/acquire IBAN claims (§2.4) |
| POST | `/bank-accounts/{id}/verify` | `verify_banking` | pending → verified; **no** primary transfer |
| POST | `/bank-accounts/{id}/reject` | `verify_banking` | pending → rejected; **DELETE** claim if safe (**E3**/**M2**); clear primary if set |
| POST | `/bank-accounts/{id}/revoke` | `manage_banking` (**E2**) | verified → revoked; clear primary; **DELETE** claim if safe; no auto-promote |
| POST | `/bank-accounts/{id}/set-primary` | `manage_banking` | Explicit transfer to **verified** target (any slot) |
| POST | `/bank-accounts/{id}/clear-primary` | `manage_banking` | Clear; no auto-promote |
| POST | `/bank-accounts/{id}/reveal` | `view_banking` | Reason ≥10 (**E1**); audited (**IIB-D12**) |

### Stable error codes (proposed)

`SUPPLIER_BANK_NOT_FOUND`, `SUPPLIER_BANK_INVALID_STATUS`, `SUPPLIER_BANK_SOD_VIOLATION`, `SUPPLIER_BANK_IBAN_DUPLICATE`, `SUPPLIER_BANK_IDENTIFIER_REQUIRED`, `SUPPLIER_BANK_CURRENCY_UNSUPPORTED`, `SUPPLIER_BANK_PRIMARY_NOT_VERIFIED`, `SUPPLIER_BANK_IMMUTABLE`, `SUPPLIER_BANK_EVIDENCE_INVALID`, `SUPPLIER_BANK_REVEAL_REASON_REQUIRED`, `DOCUMENT_USED_AS_BANK_EVIDENCE`

### Concurrency / idempotency / lock order

**Lock order (deadlock avoidance):** `suppliers.id` → `supplier_bank_iban_claims` rows for involved fingerprints (by fingerprint asc) → family `supplier_bank_accounts` by `id` asc → editors. Retry once on deadlock **(Eng)**.

| Operation | Atomic requirements |
|---|---|
| Create / supersede | Lock supplier; acquire/refresh claims; insert/update versions; editors; never copy ciphertext (**E5**) |
| Pending PATCH | Lock supplier; if IBAN changes: acquire new + safe **DELETE** of old claim; update ciphertext/last4; if metadata-only: no claim mutation; upsert editor |
| Verify / reject | Lock version; SoD check under lock; reject may **DELETE** claim if safe |
| Revoke | Lock version; clear primary; status→revoked; **DELETE** claim if safe (**E2**/**E3**/**M2**) |
| Set-primary | Lock supplier; demote all primaries; promote target verified |

Optional `Idempotency-Key` on verify/reject/revoke **(Eng)**. Races: claims unique + `primary_flag` unique → 422/409.

### Audit events (proposed)

`SUPPLIER_BANK_ACCOUNT_CREATED`, `_UPDATED`, `_SUPERSEDED`, `_VERIFIED`, `_REJECTED`, `_REVOKED`, `_PRIMARY_SET`, `_PRIMARY_CLEARED`, `_REVEALED`, `_REVEAL_DENIED` — payloads: ids, last4, status, account_key, version_no, reason (reveal) — **never** plaintext/ciphertext IBAN/account.

---

## 9. Frontend Arabic RTL — **DESIGN APPROVED — NOT IMPLEMENTED**

Replace locked banking tab on `SupplierDetailsPage` with:

1. Masked list: family heads + primary badge even when primary is a superseded verified version (**IIB-D11**).  
2. History drawer per `account_key`.  
3. Create / edit pending form (no prefilled full IBAN from server show).  
4. **إظهار البيانات البنكية الكاملة** — gated by explicit `suppliers.view_banking` (`can('suppliers.view_banking')`); hidden when permission absent. Opens the **dedicated audited reveal modal** (**M3** / **E1** / **B7** / AC-B15 / AC-B22):  
   - Mandatory non-empty reason; trim; **minimum length 10** characters.  
   - Arabic RTL validation message when blank or shorter than 10 (e.g. «سبب الإظهار مطلوب (١٠ أحرف على الأقل)»).  
   - **No** reveal API request until client-side validation passes.  
   - Server-side validation remains **authoritative** (`422 SUPPLIER_BANK_REVEAL_REASON_REQUIRED`); API §13.5.  
   - List/show remain **always masked**; this action never unmasks ordinary list/show.  
   - Show plaintext ephemerally; clear on navigate/close.  
   - **No** persistent storage of reason or revealed identifiers (`localStorage` / `sessionStorage` / durable store).  
   - **No** automatic reveal on load/focus.  
   - **No** plaintext bank identifiers in frontend logs/console in production paths.  
5. Actions: verify/reject / supersede / revoke / set-primary gated by `can()`; **إظهار البيانات البنكية الكاملة** gated by `view_banking` as above.  
6. After verify of replacement, UI must **not** imply primary moved — require explicit «تعيين كأساسي».  
7. Arabic messages for D3/D4/D6/D9/D11/D12/E1.

**Do not implement in this documentation task.**

---

## 10. Formal engineering controls B1–B8 (**OWNER DESIGN APPROVED — NOT IMPLEMENTATION-AUTHORIZED**)

> Prior draft “B1–B9” naming is **retired**. Former B9 (audited reveal) maps into **B7**. Identifier rules fold into **B2**/**B3**. Sensitive explicit-only grants fold into **B1**/**B7**.

| # | Control | Invariant | Enforcement | DB | API / policy | Errors | Tests / evidence |
|---|---|---|---|---|---|---|---|
| **B1** | Tenant isolation & ownership | No cross-tenant bank access; nested under supplier; claim ownership checks (**M2**) | TenantOwned; never trust body `tenant_id` | FK `tenant_id`, `supplier_id`, holder FK | Policies; 404 cross-tenant | `SUPPLIER_BANK_NOT_FOUND` | Cross-tenant CRUD/reveal/claim |
| **B2** | Encryption & secret mgmt | IBAN/account ciphertext at rest; no plaintext logs/FE logs | APP_KEY cast/service; pepper separate | ciphertext + last4 cols | Decrypt only on reveal | Stable decrypt fail | Redaction; no log leakage |
| **B3** | Fingerprint & duplicate prevention | One **claim** per fingerprint per tenant; history keeps fingerprints; PATCH IBAN maintains claims (**M1**); safe release **DELETE**s claim (**M2**) | Normalize+HMAC; claims table §2.4; **E3** | `UNIQUE (tenant_id, iban_fingerprint)` on **claims** | Create/supersede/**PATCH**/reject/revoke | `SUPPLIER_BANK_IBAN_DUPLICATE` | Same/diff-IBAN; PATCH; cross-family; MySQL dual claim |
| **B4** | Hard SoD | Creator/any editor ≠ verifier for that version | Editors table; lock before check (**IIB-D4**) | editors unique | verify/reject | `SUPPLIER_BANK_SOD_VIOLATION` | SoD + concurrent edit/verify |
| **B5** | Immutable verified & supersession | No in-place identifier mutate when verified; supersede **E5** | Status guards; new pending version | `current_slot` unique; claims refresh | supersede body required | `SUPPLIER_BANK_IMMUTABLE` | Supersede feature + MySQL |
| **B6** | Verified-only primary & concurrency | ≤1 primary; D11 retain; explicit transfer; revoke clears; no auto-promote | Txn demote/promote; **E2** | `primary_flag` generated unique | set/clear/revoke | `SUPPLIER_BANK_PRIMARY_NOT_VERIFIED` | D11 sequence; MySQL primary races |
| **B7** | Audited reveal & redaction | Masked list/show; reveal + reason ≥10; audit success/deny; UI modal (**M3**) | **E1**/**E4**; `view_banking` | n/a | `POST …/reveal`; `Cache-Control: no-store` | `SUPPLIER_BANK_REVEAL_REASON_REQUIRED` | Reveal matrix; FE blank/short/valid |
| **B8** | II-A / II-D2 regression | Banking ∉ eligibility/activation | No EligibilityService / transition coupling | n/a | Unchanged activation | — | IIA-T9-class activation w/o banks |

Identifier rules (**IIB-D1/D2/D3/D10**): Form Request validation under create/supersede — covered by **B2**/**B5** acceptance tests.

---

## 11. Acceptance criteria (design-level)

| ID | Criterion |
|---|---|
| AC-B01 | Create pending with holder + bank_name + (IBAN∨account); currency SAR |
| AC-B02 | Pending editable by manage; verified immutable → supersede (**E5**) |
| AC-B03 | Verify/reject SoD enforced for all editors |
| AC-B04 | Cross-family active IBAN claim rejected; same-family same-IBAN supersession allowed |
| AC-B05 | List/show always masked; no secrets in audit/logs |
| AC-B06 | Primary only verified; reject/revoke clears primary; no auto-promote |
| AC-B07 | Optional evidence Document same tenant |
| AC-B08 | Cross-tenant bank id / reveal → 404 |
| AC-B09 | Activation without banks still succeeds (**II-D2** / **B8**) |
| AC-B10 | Arabic RTL banking UI permission-aware |
| AC-B11 | MySQL: primary flip + dual IBAN claim |
| AC-B12 | Supersede verified primary → old remains primary; pending never primary (**IIB-D11**) |
| AC-B13 | Verify replacement alone does **not** transfer primary |
| AC-B14 | Explicit set-primary after verify; concurrent transfers keep one primary |
| AC-B15 | Reveal: perm + reason≥10; success/deny audited; no FE persistence |
| AC-B16 | Different-IBAN supersession: old primary IBAN remains claimed (foreign create fails) |
| AC-B17 | Claim reuse only after safe release (**DELETE** claim when last pending/verified fingerprint gone) |
| AC-B18 | Reveal revoked account via reveal only (**E4**) |
| AC-B19 | Revoke requires `manage_banking` (**E2**) |
| AC-B20 | Pending PATCH changing IBAN: acquire new claim, reject foreign ownership, update ciphertext/last4, safe-release old; metadata-only PATCH leaves claims untouched (**M1**) |
| AC-B21 | Claim ownership checks reject cross-family/stale holder; orphan claims absent after release; failed txn rolls back version+claim (**M2**) |
| AC-B22 | FE reveal modal: blank/short (under 10) blocked client-side with Arabic message; valid ≥10 may call API; server still authoritative (**M3**) |

---

## 12. Test matrix (do not execute in design phase)

### Pest feature (SQLite OK)

AC-B01–B10, B12–B15, B16–B22; SoD; mask/reveal; II-D2; audit redaction; claim acquire/release/PATCH unit-level where feasible.

### MySQL mandatory concurrency

1. Dual-writer `set-primary`.  
2. Dual-writer create/claim same IBAN (different suppliers).  
3. Supersede vs verify race.  
4. Supersede-while-primary + concurrent foreign claim of old IBAN (must fail while primary/verified holds claim).  
5. Same-IBAN supersession under load (claim holder refresh).  
6. Concurrent revoke of primary + set-primary.  
7. Concurrent pending PATCH IBAN change vs foreign create of new IBAN (**M1**).  
8. Concurrent claim release (reject/revoke/PATCH) vs foreign acquire of same fingerprint (**M2**).

### Vitest / frontend

Validation; E1 reason length blank/short/valid (**AC-B22**); permission visibility; reveal modal; no persistence of reason/identifiers; no plaintext in FE logs; primary badge on non-current verified.

---

## 13. Remaining open engineering questions (non-blocking)

1. IBAN checksum library depth.  
2. Document morph `supplier_bank_account` vs supplier-linked only.  
3. APP_KEY / pepper rotation runbook detail (ops).  
4. Rejected version: editable vs new family only.  
5. List default: current-only vs include historical.  
6. Optional future Owner: forbid reveal of `revoked`; local-account cross-supplier uniqueness.

---

## 14. Unresolved Owner decisions

**None required** for IIB-D1–IIB-D12 or for closing **C1** under the claims-table model. Optional future Owner items listed in §13.

---

## 15. Engineering prerequisites (post design approval)

1. ~~Owner design approval of this pack (controls **B1–B8**, §2.4, **E1–E5**)~~ **DONE** (2026-11-01).  
2. ~~Independent engineering re-gate PASS~~ **DONE**.  
3. ~~Engineering documentation closure M1–M3~~ **VERIFIED** (2026-11-01) — [S04-PHASE-II-B-DOC-CLOSURE-M1-M3-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-DOC-CLOSURE-M1-M3-2026-11-01.md).  
4. Explicit Owner **coding** authorization (**NOT GRANTED**).  
5. APP_KEY + banking pepper; rotation runbook before production (ops — not design).

---

## 16. Governance

| Item | Status |
|---|---|
| Phase I | COMPLETE — OWNER ACCEPTED |
| Phase II-A | COMPLETE — OWNER ACCEPTED |
| Phase II-B Design | **OWNER APPROVED** (2026-11-01) |
| Phase II-B Documentation Closure | **VERIFIED** (2026-11-01) |
| Phase II-B Implementation | **COMPLETE — FUNCTIONALLY CLOSED — OWNER ACCEPTED** (2026-11-01) |
| Commit / push / merge / deploy | **NOT AUTHORIZED** (separate Owner act) |
| Phase II-C | NOT_STARTED |
| S04 | IN PROGRESS |
| S05 | NOT_STARTED |

**STOP.** Design approval does **not** authorize implementation. Await separate Owner coding authorization.

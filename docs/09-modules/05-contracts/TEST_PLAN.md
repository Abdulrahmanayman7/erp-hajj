# Contracts — Test Plan

> **Status:** Specified for Sprint 009 implementation
> **Last updated:** 2026-08-09

Tooling: **Pest** (backend), **Vitest** (frontend). Cross-tenant / policy failures block merge.

---

## Backend (Pest)

### A. Create / update / list

| # | Case |
|---|---|
| C01 | Create draft success; number `CTR-000001` |
| C02 | Sequential creates increment; per-tenant independent sequences |
| C03 | Payload cannot set `contract_number` / `tenant_id` / `status` |
| C04 | Required title, category, counterparty, start_date |
| C05 | Invalid date range rejected |
| C06 | Inactive category rejected on create |
| C07 | Foreign category / employee / org → 422 |
| C08 | PATCH only in draft; later statuses → `CONTRACT_NOT_EDITABLE` |
| C09 | List search by number/title/counterparty |
| C10 | Filters: status, category, employee, org, expiring_soon, date ranges |
| C11 | Default sort newest first |
| C12 | Pagination meta |

### B. Numbering

| # | Case |
|---|---|
| C20 | Sequence FOR UPDATE path; no duplicate under transactional allocation |
| C21 | Immutable number on update attempt |

### C. Lifecycle

| # | Case |
|---|---|
| C30 | Happy path: draft → review → approve → sign → execute → close |
| C31 | Each invalid skip-ahead transition rejected |
| C32 | Return in_review → draft requires comment |
| C33 | Cancel from draft/in_review/approved; blocked from signed/executing |
| C34 | Renew from executing creates successor draft + source renewed |
| C35 | Renew from non-executing rejected |
| C36 | Transition history rows match each action (from/to/actor/comment/timestamp/tenant) |
| C37 | Permission missing → 403 per action |
| C38 | Sign stores manual attestation only (transition+audit; no signature blob) |
| C39 | Transition rows are append-only (no update/delete API) |

### C2. Renewal safety

| # | Case |
|---|---|
| C34a | Second renew on same source → `CONTRACT_ALREADY_RENEWED` |
| C34b | Concurrent renew attempts: only one successor; unique on `renewed_from_contract_id` |
| C34c | Simulated failure after lock before commit: source remains `executing` |
| C34d | Successor gets new number; dates not copied; history not copied; copied business fields present |
| C34e | Successor starts `draft` with `renewed_from_contract_id` set |

### D. Expiry

| # | Case |
|---|---|
| C40 | Scheduler marks executing with past end_date → expired |
| C41 | Idempotent second run |
| C42 | Open-ended never expires |
| C43 | expiring_soon filter respects config days |
| C44 | Expiry transition has `actor_user_id` null + correlation/audit source=scheduler |

### E. Delete

| # | Case |
|---|---|
| C50 | Delete draft never left draft → 200 |
| C51 | Delete after submit-review → `CONTRACT_DELETE_FORBIDDEN` |
| C52 | No soft-delete column required |

### F. Categories

| # | Case |
|---|---|
| C60 | CRUD + activate/deactivate |
| C61 | Duplicate name/code |
| C62 | Code immutable |
| C63 | Delete in-use → `CONTRACT_CATEGORY_IN_USE` |
| C64 | Tenant isolation |
| C65 | Deactivate does not change existing contracts’ category_id |
| C66 | Create rejects inactive category; draft may keep historical inactive unchanged |
| C67 | Unused category hard delete succeeds |

### G. Tenancy (mandatory)

| # | Case |
|---|---|
| C70 | List excludes other tenant |
| C71 | Show/update/transition/delete foreign → **404** |
| C72 | Cross-tenant employee/org/category refs blocked |

### H. RBAC / Audit

| # | Case |
|---|---|
| C80 | 401 unauthenticated |
| C81 | 403 without permission |
| C82 | Audit events for create/update/each transition/delete/expire |
| C83 | No secrets in audit context |

### I. Integration side-effects

| # | Case |
|---|---|
| C90 | Org unit delete blocked when contracts reference |
| C91 | Employee delete/restrict behavior when contracts reference |

---

## Frontend (Vitest)

| # | Case |
|---|---|
| F01 | List loading / empty / error |
| F02 | Table columns render |
| F03 | Filters + expiring toggle |
| F04 | Create drawer validation |
| F05 | Create success invalidates query |
| F06 | Edit disabled / hidden when not draft |
| F07 | Transition buttons visibility by status+permission |
| F08 | Confirm dialogs for cancel/close/renew; comment required UX |
| F09 | Details timeline rendering from transitions |
| F10 | Category manager: activate/deactivate/delete-unused; active-only selector |
| F11 | API error code → Arabic (`CONTRACT_INVALID_STATUS_TRANSITION`, `CONTRACT_ALREADY_RENEWED`) |
| F12 | Sidebar label العقود with `contracts.view` |
| F13 | Responsive smoke (classes/layout helpers as elsewhere) |
| F14 | Sign confirm copy states manual attestation (not e-sign) |
| F15 | Renew button hidden/disabled when already renewed |

### Explicitly out of Vitest scope for 009

- Real file upload
- Notification toasts from scheduler
- E2E browser automation (Playwright future)

---

## Performance checks (manual / light Pest)

- Paginated list without N+1 (eager loads)
- Expiring filter uses indexed `(tenant_id, status, end_date)` path
- Precise query invalidation on frontend

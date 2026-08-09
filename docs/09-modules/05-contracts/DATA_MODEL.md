# Contracts — Data Model

> **Status:** Implemented (Sprint 009) — migrations applied
> **Last updated:** 2026-08-09

Binding: [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md), [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md), [ADR-0006](../../10-decisions/ADR-0006-CONTRACT-CATEGORIES-CATALOG.md).

## Migration order (implemented)

1. `contract_categories`
2. `contract_number_sequences`
3. `contracts`
4. `contract_status_transitions`

No User / Employee / Organization schema changes required beyond new FKs from `contracts`.

---

## `contract_categories` (tenant-owned)

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AI | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | — | FK → `tenants` RESTRICT |
| `name` | VARCHAR(150) | NO | — | Display name |
| `code` | VARCHAR(50) | YES | `NULL` | Optional; tenant-unique when set; immutable after create |
| `is_active` | BOOLEAN | NO | `true` | MVP lifecycle: active / inactive only |
| `created_at` / `updated_at` | TIMESTAMP | YES | — | |

**Uniques:** `(tenant_id, name)`; `(tenant_id, code)` (NULLs allowed multiple times in MySQL).

**Indexes:** `(tenant_id, is_active)`.

**Lifecycle:** activate / deactivate only. Hard delete **unused only**; referenced → deactivate. No SoftDeletes / no versioning. Deactivation never rewrites `contracts.contract_category_id`.

---

## `contract_number_sequences`

| Column | Type | Notes |
|---|---|---|
| `tenant_id` | BIGINT UNSIGNED PK | FK → `tenants` RESTRICT |
| `next_value` | BIGINT UNSIGNED | Default `1` |
| timestamps | | |

Concurrency: `SELECT … FOR UPDATE` then increment (mirror Employees).

---

## `contracts` (tenant-owned)

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AI | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | — | FK → `tenants` RESTRICT |
| `contract_number` | VARCHAR(32) | NO | — | Server-generated; immutable |
| `title` | VARCHAR(200) | NO | — | |
| `contract_category_id` | BIGINT UNSIGNED | NO | — | FK → `contract_categories` RESTRICT |
| `status` | VARCHAR(20) | NO | `draft` | See BUSINESS_RULES |
| `counterparty_name` | VARCHAR(200) | NO | — | Other party display |
| `counterparty_kind` | VARCHAR(20) | NO | `organization` | `person` \| `organization` \| `other` |
| `employee_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `employees` RESTRICT |
| `organization_unit_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `organization_units` RESTRICT |
| `start_date` | DATE | NO | — | |
| `end_date` | DATE | YES | `NULL` | Open-ended when null |
| `value` | DECIMAL(15,2) | YES | `NULL` | Informational |
| `currency` | CHAR(3) | NO | `SAR` | ISO 4217 |
| `notes` | TEXT | YES | `NULL` | Non-sensitive operational notes |
| `created_by` | BIGINT UNSIGNED | NO | — | FK → `users` RESTRICT |
| `renewed_from_contract_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `contracts` SET NULL; **at most one** successor per source |
| `created_at` / `updated_at` | TIMESTAMP | YES | — | |

### Explicitly excluded

Payment schedules, tax fields, e-sign provider ids / signature blobs, document blobs, supplier_id (future), national IDs, payroll fields.

### Foreign keys / ON DELETE

| FK | On delete |
|---|---|
| `tenant_id` | RESTRICT |
| `contract_category_id` | RESTRICT |
| `employee_id` | RESTRICT |
| `organization_unit_id` | RESTRICT |
| `created_by` | RESTRICT |
| `renewed_from_contract_id` | SET NULL |

### Uniques / indexes

| Constraint / index | Columns |
|---|---|
| Unique | (`tenant_id`, `contract_number`) |
| Unique | (`renewed_from_contract_id`) — nullable unique: enforces **one direct renewal** per source |
| Index | (`tenant_id`, `status`) |
| Index | (`tenant_id`, `contract_category_id`) |
| Index | (`tenant_id`, `employee_id`) |
| Index | (`tenant_id`, `organization_unit_id`) |
| Index | (`tenant_id`, `end_date`) |
| Index | (`tenant_id`, `start_date`) |
| Index | (`tenant_id`, `title`) | search support |

### Model contracts

- `Contract`, `ContractCategory` implement `TenantOwned` + `UsesTenantScope`.
- Immutable: `tenant_id`, `contract_number`, `created_by` (after create), category `code` when set.
- Relations: `category()`, `employee()`, `organizationUnit()`, `creator()`, `renewedFrom()`, `renewals()`, `statusTransitions()`.

---

## `contract_status_transitions` (tenant-owned, append-only)

**Authoritative lifecycle history** for the contract (see BUSINESS_RULES §7.2). Not soft-deleted; not updated.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | FK RESTRICT — tenancy context |
| `contract_id` | BIGINT UNSIGNED | NO | FK → `contracts` RESTRICT |
| `from_status` | VARCHAR(20) | YES | Previous status; `NULL` only for create → `draft` |
| `to_status` | VARCHAR(20) | NO | New status |
| `actor_user_id` | BIGINT UNSIGNED | YES | Performed-by user; `NULL` for scheduler expiry |
| `comment` | TEXT | YES | Reason/notes; required for return/cancel |
| `correlation_id` | VARCHAR(64) | YES | Request correlation ID when available ([AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md)) |
| `created_at` | TIMESTAMP | NO | Transition timestamp; **no** `updated_at` |

**Indexes:** (`tenant_id`, `contract_id`, `created_at`).

No updates/deletes via API. Sign attestation is a normal `approved → signed` row (manual record only — no signature payload column).

---

## Config (`config/contracts.php`)

| Key | Purpose | Default |
|---|---|---|
| `number_prefix` | Number prefix | `CTR-` |
| `number_pad` | Zero pad width | `6` |
| `default_currency` | Create default | `SAR` |
| `expiring_soon_days` | List/badge window | `30` |

---

## Future Documents link

Documents sprint should attach files to contracts without altering core columns — e.g. polymorphic `documentables` (`documentable_type=Contract`, `documentable_id`) or `documents.contract_id`. Sprint 009 leaves a documented extension point only.

## Rollout impact

- Org unit / employee / category hard-delete becomes blocked while referenced.
- Scheduler: register `contracts:expire` (name TBD at implementation) daily.
- Re-run RBAC catalog sync + system role provisioning after deploy.

# Assets and Custodies — Data Model

> **Status:** Implemented (Sprint 015)
> **Last updated:** 2026-08-11
> ADR: [ADR-0012](../../10-decisions/ADR-0012-ASSET-CUSTODY-AND-OWNERSHIP.md)

## Tables (migration order)

1. `asset_number_sequences`
2. `asset_categories`
3. `assets` (without `current_custody_id` FK initially, or nullable without FK until custodies exist)
4. `asset_status_transitions`
5. `asset_custody_number_sequences`
6. `asset_custodies`
7. Alter `assets` add nullable `current_custody_id` FK → `asset_custodies` `nullOnDelete` (cache only)

No SoftDeletes on transitions/custodies. No `inventory_item_id`. No separate `current_employee_id`.

---

## 1. `asset_number_sequences`

| Column | Type | Notes |
|---|---|---|
| `tenant_id` | FK → tenants | PK |
| `next_number` | unsigned bigint | default 1 |
| timestamps | | |

Allocation: `SELECT … FOR UPDATE`; format `AST-` + pad 6.

---

## 2. `asset_categories`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | TenantOwned |
| `name` | string(120) | no | |
| `description` | text | yes | |
| `is_active` | boolean | no | default true |
| timestamps | | no | |

UNIQUE (`tenant_id`, `name`). INDEX (`tenant_id`, `is_active`).
Items FK: **RESTRICT** on delete.

---

## 3. `assets`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | TenantOwned |
| `asset_number` | string(20) | no | Immutable `AST-######` |
| `name` | string(255) | no | |
| `description` | text | yes | |
| `category_id` | FK → asset_categories | yes | RESTRICT |
| `serial_number` | string(120) | yes | UNIQUE (`tenant_id`, `serial_number`) where not null |
| `barcode` | string(64) | yes | UNIQUE (`tenant_id`, `barcode`) where not null |
| `status` | string(32) | no | enum values in BUSINESS_RULES |
| `condition` | string(32) | yes | `good`\|`fair`\|`damaged`\|`unknown` |
| `warehouse_id` | FK → warehouses | yes | nullOnDelete; home/storage |
| `organization_unit_id` | FK → organization_units | yes | nullOnDelete |
| `purchase_value` | decimal(18,2) | yes | Informational only |
| `acquisition_date` | date | yes | |
| `current_custody_id` | FK → asset_custodies | yes | nullOnDelete; **cache** of active custody |
| `notes` | text | yes | |
| `created_by` | FK → users | no | RESTRICT |
| timestamps | | no | |

UNIQUE (`tenant_id`, `asset_number`).
Indexes: (`tenant_id`, `status`), (`tenant_id`, `category_id`), (`tenant_id`, `warehouse_id`), (`tenant_id`, `organization_unit_id`), (`tenant_id`, `name`), (`tenant_id`, `current_custody_id`).

Contracts: `Asset` implements `TenantOwned` + `UsesTenantScope`.
Mass-assignment: never fill `tenant_id`, `asset_number`, `status` (except via actions), `current_custody_id` from generic client PATCH.

---

## 4. `asset_status_transitions`

Append-only authoritative status history (mirrors contracts/tasks).

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | |
| `asset_id` | FK → assets | no | RESTRICT |
| `from_status` | string(32) | yes | null on create |
| `to_status` | string(32) | no | |
| `reason` | string(1000) | yes | Required for retire/lost |
| `performed_by` | FK → users | no | RESTRICT |
| `correlation_id` | string(64) | yes | |
| `custody_id` | FK → asset_custodies | yes | nullOnDelete; set when transition caused by assign/return |
| timestamps | | no | `created_at` only meaningful; `updated_at` optional unused |

Indexes: (`tenant_id`, `asset_id`, `created_at`), (`tenant_id`, `to_status`).
**No UPDATE/DELETE API.**

---

## 5. `asset_custody_number_sequences`

Same pattern; format `CUS-######`.

---

## 6. `asset_custodies`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | |
| `custody_number` | string(20) | no | Immutable `CUS-######` |
| `asset_id` | FK → assets | no | RESTRICT |
| `employee_id` | FK → employees | no | RESTRICT |
| `status` | string(32) | no | `active` \| `returned` |
| `assigned_at` | timestamp | no | Server default now (MVP: not client-backdated) |
| `expected_return_at` | timestamp | yes | |
| `returned_at` | timestamp | yes | Set on return |
| `assigned_by` | FK → users | no | RESTRICT |
| `returned_by` | FK → users | yes | RESTRICT |
| `condition_at_assignment` | string(32) | yes | |
| `condition_at_return` | string(32) | yes | |
| `assignment_notes` | string(1000) | yes | |
| `return_notes` | string(1000) | yes | |
| `correlation_id` | string(64) | yes | |
| timestamps | | no | |

UNIQUE (`tenant_id`, `custody_number`).
INDEX (`tenant_id`, `asset_id`, `status`), (`tenant_id`, `employee_id`, `status`), (`tenant_id`, `assigned_at`), (`tenant_id`, `expected_return_at`).

### Single active custody (MySQL)

MySQL lacks partial unique indexes. Enforcement:

1. **Application (binding):** lock Asset `FOR UPDATE`; assert no other `asset_custodies` row with same `asset_id` and `status=active`; then insert.
2. **Cache:** `assets.current_custody_id` points to the active custody when `status=in_use`, else null.
3. Optional defensive check constraint is not portable; do not rely on triggers in MVP.

Returned rows are never updated (except deferred correction — out of MVP). Assign creates `active`; return sets fields and `status=returned` in one update **only while active** (allowed mutation path). After `returned`, model `updating` must reject further changes → `ASSET_IMMUTABLE`.

---

## Explicitly absent

- `inventory_item_id`
- `current_employee_id`
- `home_warehouse_id` vs `current_warehouse_id` split (single `warehouse_id` = home/storage)
- Maintenance work-order tables
- Depreciation tables
- SoftDeletes on history

## Morph map (Documents — at assets implementation)

```
asset → Asset
custody → AssetCustody
```

## Config

`config/assets.php`: number prefixes/pads; status enums; condition allow-list; `purchase_value` max decimals = 2.

## Migration notes

- Applied: `2026_08_11_150000`–`150006` (`asset_number_sequences`, `asset_categories`, `assets`, `asset_status_transitions`, `asset_custody_number_sequences`, `asset_custodies`, `assets.current_custody_id` FK).
- Depends on: tenants, users, organization_units, employees, warehouses (nullable FK).
- No DB FK to Documents (morph application-level); aliases `asset` / `custody` registered.

# S04 Phase IV — WP01-A Schema & Models Acceptance Evidence

> **Status:** `IMPLEMENTED — AWAITING OWNER REVIEW` (Gate A)  
> **Not claimed:** Owner functional acceptance · WP01-B…F · WP02+ · commit/push/merge/deploy  
> **Date:** 2026-11-01  
> **Branch:** `feature/s04-phase-i-supplier-registry` @ `09a902a0a08aa31a8686ce3824e6ee25f922a112` (+ uncommitted WP01-A work)  
> **Authorization:** Owner limited to **WP01-A only**  
> **Database for all writes/tests:** `erp_hajj_testing` only (MariaDB 10.4.27)

---

## 1. Environment / isolation

| Check | Result |
|---|---|
| Effective `SELECT DATABASE()` before migrate | `erp_hajj_testing` |
| Host | `127.0.0.1` / server `DESKTOP-I7N8ENS` |
| Engine | MariaDB **10.4.27** |
| `erp_hajj` | **not** migrated/modified |
| Disk (approx) | C: ~4.9 GB free · D: ~19.4 GB free |
| Git ops | **None** (no commit/push/checkout) |

Identity helper: `backend/tmp_wp01a_db_identity.php` (GATE=PASS before migrate and before Pest).

---

## 2. Delivered artifacts

### Migrations (4)

1. `2026_11_01_240001_create_purchase_approval_matrices_table.php`  
2. `2026_11_01_240002_create_purchase_approval_matrix_rules_table.php`  
3. `2026_11_01_240003_create_purchase_approval_matrix_levels_table.php`  
4. `2026_11_01_240004_create_purchase_approver_authorities_table.php`  

**Not created:** optional PR `matrix_id` FK (deferred per Owner WP01-A instruction).

### Models / enum

- `Enums/PurchaseApprovalMatrixStatus.php` (`draft` \| `published` \| `retired`)  
- `Models/PurchaseApprovalMatrix.php`  
- `Models/PurchaseApprovalMatrixRule.php`  
- `Models/PurchaseApprovalMatrixLevel.php`  
- `Models/PurchaseApproverAuthority.php`  

### Tests

- `tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp01aApprovalConfigSchemaTest.php`

---

## 3. Migration results

```text
2026_11_01_240001 … DONE
2026_11_01_240002 … DONE
2026_11_01_240003 … DONE
2026_11_01_240004 … DONE
POST_MIGRATE_DB=erp_hajj_testing
tables: matrices/rules/levels/authorities = YES
purchase_requests = YES (unchanged; matrix_id FK count = 0)
```

---

## 4. Tests executed

```text
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp01aApprovalConfigSchemaTest.php
Tests: 4 passed (24 assertions)
```

Coverage: table/column presence (`applies_when_unpriced`, `covers_unpriced`); persist + relationships; unique `(matrix_id, level_order)`; MissingTenantContext fail-closed; cross-tenant query isolation.

---

## 5. Explicitly not done (out of WP01-A)

- Matrix/rule/level/authority Actions, APIs, publish, validators  
- PermissionCatalog changes / grants  
- Delegations, SLA, instances/steps, routing, UI, notifications  
- WP01-B…F, WP02–WP08, Phase V, S05  

---

## 6. Confirmation

- No production DB writes.  
- No Git-changing operations.  
- No `migrate:fresh` / `db:wipe`.  
- Gate A ready for Owner review — **not** self-declared Owner functional acceptance.

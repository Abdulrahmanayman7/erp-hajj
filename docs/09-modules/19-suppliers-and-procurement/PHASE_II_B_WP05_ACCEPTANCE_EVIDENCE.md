# S04 Phase II-B — WP-05 Acceptance Evidence Matrix

> **Status:** WP-05 **OWNER ACCEPTED** (2026-11-01). Phase II-B **COMPLETE — FUNCTIONALLY CLOSED**.  
> **Closure authority:** [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md) · [S04-PHASE-II-B-FUNCTIONAL-CLOSURE-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-FUNCTIONAL-CLOSURE-2026-11-01.md)  
> **Release/deploy:** **NOT AUTHORIZED** by this evidence file alone.

**Branch:** `feature/s04-phase-i-supplier-registry` @ `09a902a0a08aa31a8686ce3824e6ee25f922a112`  
**Authorization / Owner closure date:** 2026-11-01

## Database isolation

| Check | Result |
|---|---|
| `phpunit.mysql.xml` forces `DB_DATABASE=erp_hajj_testing` | PASS |
| Concurrency workers refuse non-`erp_hajj_testing` | PASS |
| Default app DB `erp_hajj` has 0 `supplier_bank*` tables | PASS |
| Deadlock workers echo runtime `db=erp_hajj_testing` | PASS |

## Requirement → evidence

| ID | Requirement | Test / command | Result | Source |
|---|---|---|---|---|
| A1 | E2E lifecycle + historical primary + revoke/no fallback + historical reveal | `SupplierPhase2BWp05AcceptanceTest` WP05-A1 | PASS | WP-05 |
| A2 | Permission matrix + hard SoD via API | WP05-A2 (+ WP03-T3/T4) | PASS | WP-05 + WP-03 |
| A3 | C1 claim uniqueness / supersession retains claim | WP05-A3 (+ WP02-T3/T6/T7) | PASS | WP-05 + WP-02 |
| A4 | Cross-tenant / wrong-supplier 404 | WP03-T5 | PASS | WP-03 |
| A5 | Masked API / no ciphertext | WP03-T2 | PASS | WP-03 |
| A6 | Reveal audit no raw reason; denial without decrypt | WP03-T6/T7 | PASS | WP-03 |
| A7 | II-D2 activation independence | WP05-A1 / WP03-T12 / WP02-T11 | PASS | WP-05 + prior |
| A8 | Concurrent primary uniqueness | `SupplierBankActionConcurrencyTest` | PASS | WP-02 |
| A9 | Concurrent IBAN claim single winner | `SupplierBankActionConcurrencyTest` | PASS | WP-02 |
| D1 | Genuine MySQL deadlock 1213/40001 + BankingTransaction retry | `SupplierBankDeadlockConcurrencyTest` WP05-D1 | PASS | WP-05 |
| D2 | Retry exhaustion after ATTEMPTS=2 | WP05-D2 | PASS | WP-05 |
| F1 | FE reveal race / stale reject | `useBankRevealSession.test.ts` | PASS | WP-04 R1 |
| F2 | FE permissions / masked RTL panel | `SupplierBankingPanel.test.ts` | PASS | WP-04 |
| F3 | `vue-tsc -b` | `npm run type-check` | PASS | WP-05 |
| F4 | Vite production build (separate step, 8GB heap) | `npx vite build` | PASS | WP-05 |
| F5 | Combined `npm run build` (tsc+vite) | Not claimed green (memory) | NOT VERIFIED as combined | Operational limit |

## Operational follow-ups (not functional blockers)

1. Combined frontend build memory sensitivity.
2. Potential sensitive request-body capture in infrastructure access logs.

## Commands executed (WP-05)

```bash
# MySQL erp_hajj_testing only
php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/SupplierBankDeadlockConcurrencyTest.php
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase2BWp05AcceptanceTest.php tests/Concurrency/SupplierBankActionConcurrencyTest.php

# SQLite focused
php vendor/bin/pest tests/Feature/SuppliersAndProcurement/SupplierPhase2BWp05AcceptanceTest.php tests/Feature/SuppliersAndProcurement/SupplierPhase2BWp03BankingApiTest.php tests/Feature/SuppliersAndProcurement/SupplierPhase2BWp02BankingActionsTest.php

# Frontend
npm test -- --run src/modules/suppliers/composables/useBankRevealSession.test.ts src/modules/suppliers/components/SupplierBankingPanel.test.ts src/modules/suppliers/api/bankAccountsApi.test.ts src/modules/suppliers/validation/bankingValidation.test.ts
npm run type-check
# $env:NODE_OPTIONS='--max-old-space-size=8192'; npx vite build
```

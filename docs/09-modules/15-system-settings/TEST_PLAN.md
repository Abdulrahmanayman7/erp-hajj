# System Settings — Test Plan

> **Status:** Specified (Sprint 020) — **no executable tests in this sprint**  
> **Last updated:** 2026-08-12

## Backend Pest (implementation sprint)

### ACCESS

| ID | Case | Expected |
|---|---|---|
| A1 | Unauthenticated GET/PATCH | 401 |
| A2 | Authenticated without `tenant_settings.view` | GET 403 |
| A3 | With `view`, without `update` | GET 200; PATCH 403 |
| A4 | With `update` | PATCH 200 on valid body |

### TENANCY

| ID | Case | Expected |
|---|---|---|
| T1 | Tenant A GET returns A values only | Pass |
| T2 | Tenant A cannot observe B (context isolation) | Pass |
| T3 | `tenant_id` in PATCH body ignored/rejected | No cross-tenant write |
| T4 | Suspended/pending tenant | Existing 403 lifecycle codes |

### GET

| ID | Case | Expected |
|---|---|---|
| G1 | Returns effective general + regional | Shape per API.md |
| G2 | Defaults present for seeded tenant | `timezone`, `locale=ar` |

### PATCH

| ID | Case | Expected |
|---|---|---|
| P1 | Partial update name only | Other fields preserved |
| P2 | Update timezone to valid IANA | Persisted on `tenants.timezone` |
| P3 | Invalid timezone | 422 `SETTINGS_INVALID_TIMEZONE` |
| P4 | Unknown field | 422 |
| P5 | PATCH `locale` | 422 immutable |
| P6 | Duplicate global `name` | 422 unique conflict |
| P7 | Clear nullable contact via null | Stored null |
| P8 | Reject HTML-heavy abuse on name (string rules) | 422 or sanitized reject — no HTML stored as markup execution surface |

### TIMEZONE CONSUMER SMOKE

| ID | Case | Expected |
|---|---|---|
| Z1 | After timezone change, Dashboard/me reflects new zone | Pass (feature or unit on clock) |

### AUDIT

| ID | Case | Expected |
|---|---|---|
| U1 | Successful PATCH → one `TENANT_SETTINGS_UPDATED` | Pass |
| U2 | Before/after only changed fields; no secrets | Pass |
| U3 | GET does not write audit | Pass |
| U4 | Validation failure → no Tenant mutation, no audit row | Pass |
| U5 | Audit failure semantics per ADR-0015 | Pass |

### RBAC TEMPLATES

| ID | Case | Expected |
|---|---|---|
| R1 | Owner can update | Pass |
| R2 | GM can view, cannot update (default seed) | Pass |
| R3 | Auditor cannot view by default | Pass |

### RESOLVER / KV SAFETY (if KV writer exists)

| ID | Case | Expected |
|---|---|---|
| K1 | Unknown catalog key rejected | Pass |
| K2 | No duplicate write of `timezone` into `tenant_settings` | Pass |

## Frontend Vitest (implementation sprint)

| ID | Case |
|---|---|
| F1 | GET populates form |
| F2 | Dirty state + Save enabled |
| F3 | Save calls PATCH; success toast; cache invalidation (`settings` + `me`) |
| F4 | Validation errors mapped to fields |
| F5 | View-only mode hides Save / no editable controls |
| F6 | Timezone select emits IANA value |
| F7 | Loading / error / retry |
| F8 | Permission gating (route + sidebar item) |
| F9 | RTL / basic responsive smoke (component assertions as practical) |

## Explicitly not required this module

- Parallel concurrency stress on Settings.
- Browser E2E (Playwright still TBD).
- Making threshold config tenant-specific tests (out of scope).

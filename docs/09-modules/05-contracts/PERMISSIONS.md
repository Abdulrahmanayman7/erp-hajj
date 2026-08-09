# Contracts — Permissions

> **Status:** Implemented (Sprint 009) — seeded
> **Last updated:** 2026-08-09

## Seeded with Sprint 009 implementation

| Permission | Purpose |
|---|---|
| `contracts.view` | List/show contracts; list categories |
| `contracts.create` | Create draft contracts |
| `contracts.update` | Update draft fields; manage categories (CRUD/activate) |
| `contracts.review` | Submit to review; return to draft |
| `contracts.approve` | Approve (`in_review` → `approved`) |
| `contracts.sign` | Sign (`approved` → `signed`) |
| `contracts.execute` | Start execution (`signed` → `executing`) |
| `contracts.close` | Close (`executing` → `closed`) |
| `contracts.renew` | Renew (source → `renewed` + successor draft) |
| `contracts.cancel` | Cancel pre-signature contracts |
| `contracts.delete` | Hard-delete eligible drafts only |

## Not seeded

| Permission | Reason |
|---|---|
| `contracts.attach_documents` | Documents sprint |
| `contract_categories.*` | Folded into `contracts.view` / `contracts.update` for MVP |

**Historical catalog note:** Master [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md) already listed transition permissions; Sprint 009 adds `contracts.cancel` and clarifies `contracts.delete` semantics. Remove obsolete assumptions that every post-approve delete is soft-delete-only — hard delete is draft-only.

## Default role template guidance

| System role | Suggested grants |
|---|---|
| Tenant Owner | All `contracts.*` (via `allNames()`) |
| General Manager | All `contracts.*` |
| Department Manager | `view`, `create`, `update`, `review` |
| Supervisor | none (or `view` only if product wants — **default none**) |
| Employee / read_only | none |
| Auditor | `contracts.view` |

**TBD at seed PR:** Exact GM/DeptMgr maps may add `approve` for GM only — recommendation above is safe default.

## Policy authority

- `ContractPolicy`, `ContractCategoryPolicy` (or category gated inside ContractPolicy abilities) — permission checks only; never role-name checks.
- No row-level org scoping in Sprint 009.
- Frontend `can()` is UX only.

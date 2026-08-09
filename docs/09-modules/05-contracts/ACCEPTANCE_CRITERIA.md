# Contracts — Acceptance Criteria

> **Status:** Specified (Sprint 009)
> **Last updated:** 2026-08-09

## Functional

- [ ] Tenant-scoped contract CRUD; create always starts as `draft` with `CTR-######` number.
- [ ] `contract_number` immutable; `tenant_id` / number never accepted from client.
- [ ] Category required; active/inactive lifecycle; unused hard delete; in-use → deactivate only; deactivate does not rewrite contracts.
- [ ] Counterparty name + kind required; optional employee and organization unit with tenant-safe validation.
- [ ] Date validation: `end_date >= start_date` when both set; open-ended allowed.
- [ ] Value stored as decimal; currency defaults to SAR; informational only.
- [ ] Lifecycle only via action endpoints; invalid transitions → `CONTRACT_INVALID_STATUS_TRANSITION`.
- [ ] `contract_status_transitions` is authoritative history (tenant, from/to, actor, comment, timestamp, correlation ID when available).
- [ ] Sign is manual attestation only (who/when via transition + audit — no e-sign).
- [ ] Return-to-draft and cancel require comment.
- [ ] Renew is transactional + one-child (`CONTRACT_ALREADY_RENEWED`); dates/history not copied; source not renewed if insert fails.
- [ ] Scheduler expires `executing` past `end_date`; idempotent; audited.
- [ ] Expiring-soon filter/badge uses config window.
- [ ] Hard delete only for never-transitioned drafts; otherwise forbidden.
- [ ] No file attachments in Sprint 009 UI/API.
- [ ] Arabic RTL list + drawer + details timeline; permission-aware actions.

## Security / tenancy

- [ ] Cross-tenant list/show/update/transition/delete → **404**.
- [ ] Foreign employee/org/category ids cannot write.
- [ ] Status cannot be forced via PATCH.
- [ ] Every transition permission enforced by Policy (`403 AUTHORIZATION_DENIED`).

## Quality

- [ ] Pest matrix in TEST_PLAN green (incl. mandatory cross-tenant).
- [ ] Vitest matrix green; `tsc` / build pass.
- [ ] Module docs marked Implemented only after gates pass.

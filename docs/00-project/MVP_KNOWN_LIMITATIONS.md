# MVP Known Limitations

> **Status:** Release communication — Sprint 019
> **Last updated:** 2026-08-12

These items are **approved deferrals** or incomplete MVP-scope slices. They are not accidental omissions unless noted.

## Incomplete within MVP scope

| Item | Notes |
|---|---|
| System settings API/UI (`tenant_settings`) | **Specified** (Sprint 020 / ADR-0016); permissions + table + Tenant columns exist; **API/UI implementation pending**. |
| Platform tenant admin HTTP APIs | Platform Super Admin exceptional access remains narrowly scoped; full `/platform/tenants` product UI TBD. |

## Explicitly out of MVP (do not build in hardening)

From [OUT_OF_SCOPE.md](../00-project/OUT_OF_SCOPE.md) and module ADRs:

- Procurement, finance/accounting, payroll
- External notification channels (email/SMS/WhatsApp/push)
- WebSockets / realtime transport
- Document malware scanning / antivirus product
- Document versioning, OCR, confidentiality levels, public URLs
- Audit export, pruning, hash-chain, observers
- Multi-UOM conversions, lot/serial, multi-step inventory transfers
- Asset depreciation, maintenance work-orders, custody void/correction liability workflows
- MFA/SSO (auth future)
- Mobile apps, pilgrims, transportation/GPS, BI/AI, dynamic report builders

## Operational limitations (honest)

- Production restore drill may still be **pending** until staging/prod infrastructure exists — see [BACKUP_AND_RECOVERY.md](../08-deployment/BACKUP_AND_RECOVERY.md).
- CI Pest currently runs primarily on SQLite; MySQL-specific drift remains a residual risk mitigated by disposable MySQL migrate checks when available.
- True parallel concurrency stress is limited; many race controls are covered by deterministic lock tests, not multi-process load tests.
- No formal WCAG certification; accessibility is a baseline only.
- No enterprise million-row performance claim without load testing.

## Related

- [MVP_SCOPE.md](../00-project/MVP_SCOPE.md)
- [OUT_OF_SCOPE.md](../00-project/OUT_OF_SCOPE.md)
- [MVP_RELEASE_CHECKLIST.md](../08-deployment/MVP_RELEASE_CHECKLIST.md)

# MVP Known Limitations

> **Status:** Release communication — RC 0.1.0 UAT validation (2026-08-13)
> **Last updated:** 2026-08-13

These items are **approved deferrals** or incomplete MVP-scope slices. They are not accidental omissions unless noted.

## Incomplete within MVP scope

| Item | Notes |
|---|---|
| Platform tenant admin HTTP APIs | Platform Super Admin exceptional access remains narrowly scoped; full `/platform/tenants` product UI TBD. |
| Interactive browser UAT sign-off | Automated Pest/Vitest + disposable MariaDB migrate/seed/scanners executed on `release/0.1.0-uat`; Chrome/Firefox/Edge + RTL/responsive human UAT still required before calling v0.1.0 release-ready. |

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

# MVP Known Limitations

> **Status:** Release communication — P1 closure attempt `release/0.1.0-final-validation` (2026-08-13)
> **Last updated:** 2026-08-13

These items are **approved deferrals** or incomplete release gates. They are not accidental omissions unless noted.

## Incomplete within MVP scope / release gates

| Item | Notes |
|---|---|
| Platform tenant admin HTTP APIs | Full `/platform/tenants` product UI TBD. |
| Staging / real-host smoke | **P1 open** — no staging credentials in this environment. |
| Host PHP upload limits | **P1 open** — local XAMPP values do not satisfy the host gate. |
| Firefox UAT | **P1 open** — system Firefox present; Playwright headless launch failed. |
| Chrome UAT completeness | **Partial** — most routes OK; some navigations hit host `ERR_INSUFFICIENT_RESOURCES`. |
| Full human ops UAT | Governance/inventory/assets/notifications deep UI chains not fully signed PASS. |
| Sustained queue fanout | Worker can run locally; representative multi-job fanout on staging still required. |
| Product/Owner sign-off | **P1 open** — must be human. |

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

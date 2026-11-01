# S04 Phase II-B — Owner Decisions (IIB-D1–IIB-D12)

> **Status:** `APPROVED — OWNER DECISIONS` (documentation)  
> **Owner decision date:** 2026-11-01  
> **Design pack:** [PHASE_II_B_SUPPLIER_BANKING_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_II_B_SUPPLIER_BANKING_DESIGN.md)  
> **Design approval:** [S04-PHASE-II-B-DESIGN-APPROVAL-2026-11-01.md](S04-PHASE-II-B-DESIGN-APPROVAL-2026-11-01.md) — **DESIGN OWNER APPROVED**; implementation **NOT AUTHORIZED**  
> **Does not:** authorize coding, migrations, seeding, commit, push, merge, deploy, II-C, PR engine, or S05.  
> **Does not:** reopen ADR-0022 **101–130**, G1 **D1–D5**, II-D1–II-D4, or Phase II-A acceptance.

## 1. Context

Project Owner authorized documentation-only Phase II-B design after the read-only readiness audit, then approved **IIB-D11** and **IIB-D12** to close the remaining banking business questions. All twelve decisions below are **binding** for the II-B design pack.

## 2. Approved decisions (verbatim)

| ID | Choice | Decision text |
|---|---|---|
| **IIB-D1** | **A** | Account holder name is mandatory. |
| **IIB-D2** | **A** | Bank name is free text for MVP; no bank master catalog. |
| **IIB-D3** | **B** | Require at least one of IBAN or local account number. Prefer IBAN for Saudi accounts, but do not silently make IBAN mandatory. |
| **IIB-D4** | **A** | An account creator or editor cannot verify that same account version. Enforce hard separation of duties, including intermediate editors. |
| **IIB-D5** | **B** | Verification evidence Document is optional. |
| **IIB-D6** | **A** | Duplicate IBAN within the same tenant is prohibited. |
| **IIB-D7** | **B** | Support `revoked` for post-verification invalidation. |
| **IIB-D8** | **B** | Verified bank-account identifiers and their verified history are immutable. Changes require a new pending version superseding the previous version through an explicitly designed workflow. |
| **IIB-D9** | **C** | Only verified accounts may be primary. Rejecting or revoking an account must clear its primary designation. |
| **IIB-D10** | **A** | SAR only for the initial release. |
| **IIB-D11** | **A** | Preserve the old verified primary account during replacement; transfer primary only through a separate explicit action after verification. |
| **IIB-D12** | **A** | Full bank identifiers may be revealed only through a separate audited action with explicit permission and mandatory reason. |

### IIB-D11=A — operational rules (binding)

When a verified primary bank account is superseded by a new pending version:

1. The old verified account **remains primary**.  
2. The new pending version must **not** become primary automatically.  
3. The old account must **not** be automatically revoked merely because a replacement was created.  
4. The old primary remains usable as primary while it remains verified and not revoked, subject to the approved current/historical account model.  
5. Once the replacement is verified, primary designation may transfer **only** through a separate, explicit, authorized action.  
6. Verification of the replacement alone must **not** silently transfer primary.  
7. Revoking the old primary must clear its primary designation.  
8. **No** automatic fallback or auto-promotion to another account.

### IIB-D12=A — operational rules (binding)

1. Full IBAN/local account identifiers are **masked by default**.  
2. Full-value access only via a **dedicated reveal** operation requiring: explicit sensitive permission; authenticated tenant-authorized actor; mandatory non-empty reason; successful authorization before decryption.  
3. Audit every reveal attempt (including denied attempts where technically feasible): actor, supplier, bank-account reference, timestamp, correlation ID, reason, outcome.  
4. **No** plaintext IBAN/account in audit records, logs, exceptions, telemetry, or URLs.  
5. **No** full identifiers in ordinary list/show API responses.  
6. **No** automatic reveal in frontend forms or page initialization.  
7. **No** persistent frontend caching of revealed values.

## 3. Traceability

| Decision | Primarily constrains |
|---|---|
| IIB-D1–D3, D10 | Schema / validation (ADR-0022 **110**, ADR-0023 §2.5) |
| IIB-D4 | SoD (parallel to II-A C3 / M5) |
| IIB-D5 | Documents integration (ADR-0010); **not** II-A eligibility |
| IIB-D6 | Tenant-wide uniqueness / fingerprint |
| IIB-D7–D9 | Lifecycle, supersession, primary rules |
| IIB-D8 | Immutability of verified versions |
| IIB-D11 | Primary independent of version-currentness during supersession |
| IIB-D12 | Dedicated audited reveal; masked list/show |

## 4. Preserved bindings

- **II-D2:** A verified bank account is **not** required for supplier activation.  
- No payments / accounting in S04.  
- Sensitive banking permissions remain explicit-assignment only (**D4**).

## 5. Open items

**Business decisions IIB-D1–IIB-D12:** all **APPROVED**.  

Engineering defaults **E1–E5** and controls **B1–B8** are Owner design-approved with the design pack. Doc-closure findings **M1–M3** are **VERIFIED CLOSED** (2026-11-01). Optional future Owner items (e.g. forbid reveal of `revoked`; local-account uniqueness) do **not** reopen D1–D12.

## 6. Governance after these decisions

| Item | Status |
|---|---|
| Phase I | COMPLETE — OWNER ACCEPTED |
| Phase II-A | COMPLETE — OWNER ACCEPTED |
| Phase II-B Design | **OWNER APPROVED** (2026-11-01) |
| Phase II-B Documentation Closure | **VERIFIED** (2026-11-01) |
| Phase II-B Implementation | Later **COMPLETE — FUNCTIONALLY CLOSED — OWNER ACCEPTED** (2026-11-01; WP-01–WP-05) — [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md) |
| Commit / push / merge / deploy | **NOT AUTHORIZED** |
| Phase II-C | NOT_STARTED |
| S04 overall | IN PROGRESS |
| S05 | NOT_STARTED |

## 7. References

- [PHASE_II_B_SUPPLIER_BANKING_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_II_B_SUPPLIER_BANKING_DESIGN.md)  
- [S04-PHASE-II-B-DESIGN-APPROVAL-2026-11-01.md](S04-PHASE-II-B-DESIGN-APPROVAL-2026-11-01.md)  
- [S04-PHASE-II-B-DOC-CLOSURE-M1-M3-2026-11-01.md](S04-PHASE-II-B-DOC-CLOSURE-M1-M3-2026-11-01.md)  
- [S04-PHASE-II-B-FUNCTIONAL-CLOSURE-2026-11-01.md](S04-PHASE-II-B-FUNCTIONAL-CLOSURE-2026-11-01.md)  
- [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md)  
- [PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md](../09-modules/19-suppliers-and-procurement/PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md)  
- [S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md](S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md) (II-D2)  
- [ADR-0022](ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) **110**  
- [ADR-0023](ADR-0023-S04-ENGINEERING-DESIGN.md) §2.5  
- [S04_PHASE_II_A_ACCEPTANCE_STATUS.md](../00-project/S04_PHASE_II_A_ACCEPTANCE_STATUS.md)
